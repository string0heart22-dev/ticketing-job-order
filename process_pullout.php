<?php
require_once 'auth_check.php';
require_once 'session_init.php';
require_once 'config.php';
require_once 'db_notify.php';
require_once 'log_activity.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $client_name = trim($_POST['client_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (empty($client_name) || empty($priority) || empty($reason)) {
        throw new Exception('Client Name, Priority, and Reason are required.');
    }

    // Duplicate check: same client + address created today (hard block, unless forced)
    if (empty($_POST['force_create'])) {
        $dup_check = $conn->prepare("SELECT ticket_number, status FROM pullout_tickets WHERE client_name = ? AND address = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        if ($dup_check) {
            $dup_check->bind_param('ss', $client_name, $address);
            $dup_check->execute();
            $dup_result = $dup_check->get_result();
            if ($dup_result->num_rows > 0) {
                $existing = $dup_result->fetch_assoc();
                echo json_encode([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => 'A pullout ticket for this client and address was already created today.',
                    'ticket'    => $existing['ticket_number'],
                    'status'    => $existing['status'],
                ]);
                exit;
            }
            $dup_check->close();
        }
    }

    // Pending warning: same client + address still Pending from a previous day
    $warn_check = $conn->prepare("SELECT ticket_number, status, DATE(created_at) as created_date FROM pullout_tickets WHERE client_name = ? AND address = ? AND status = 'Pending' AND DATE(created_at) < CURDATE() LIMIT 1");
    if ($warn_check) {
        $warn_check->bind_param('ss', $client_name, $address);
        $warn_check->execute();
        $warn_result = $warn_check->get_result();
        if ($warn_result->num_rows > 0) {
            $warn = $warn_result->fetch_assoc();
            if (empty($_POST['force_create'])) {
                echo json_encode([
                    'success'  => false,
                    'warning'  => true,
                    'message'  => 'This client already has a Pending pullout ticket from a previous date. Do you still want to create a new one?',
                    'ticket'   => $warn['ticket_number'],
                    'status'   => $warn['status'],
                    'created'  => $warn['created_date'],
                ]);
                exit;
            }
        }
        $warn_check->close();
    }

    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        throw new Exception('Invalid priority.');
    }

    $sql = "INSERT INTO pullout_tickets (client_name, address, contact_number, reason, priority, status) VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Database prepare error: ' . $conn->error);

    $stmt->bind_param('sssss', $client_name, $address, $contact_number, $reason, $priority);

    if ($stmt->execute()) {
        $ticket_id = $conn->insert_id;
        $ticket_number = 'PULL-' . str_pad($ticket_id, 5, '0', STR_PAD_LEFT);

        $update_stmt = $conn->prepare("UPDATE pullout_tickets SET ticket_number = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param('si', $ticket_number, $ticket_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        logActivity($conn, 'create_ticket', 'pullout', $ticket_id, 'Created pullout ticket ' . $ticket_number . ' for ' . $client_name);
        notify_db_change($conn);
        echo json_encode(['success' => true, 'ticket_number' => $ticket_number]);
    } else {
        throw new Exception('Error creating ticket: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
