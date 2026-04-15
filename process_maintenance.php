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
    $client_name    = trim($_POST['client_name']    ?? '');
    $address        = trim($_POST['address']        ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email']          ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $issue_type     = $_POST['issue_type']          ?? '';
    $priority       = $_POST['priority']            ?? '';
    $description    = trim($_POST['description']    ?? '');
    $scheduled_date = $_POST['scheduled_date']      ?? null;

    if (empty($client_name) || empty($issue_type) || empty($priority) || empty($description)) {
        throw new Exception('All required fields must be filled.');
    }

    // Duplicate check: same client + address created today (hard block, unless forced)
    if (empty($_POST['force_create'])) {
        $dup_check = $conn->prepare("SELECT ticket_number, status FROM maintenances WHERE client_name = ? AND address = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        if ($dup_check) {
            $dup_check->bind_param('ss', $client_name, $address);
            $dup_check->execute();
            $dup_result = $dup_check->get_result();
            if ($dup_result->num_rows > 0) {
                $existing = $dup_result->fetch_assoc();
                echo json_encode([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => 'A maintenance ticket for this client and address was already created today.',
                    'ticket'    => $existing['ticket_number'],
                    'status'    => $existing['status'],
                ]);
                exit;
            }
            $dup_check->close();
        }
    }

    // Pending warning: same client + address still Pending from a previous day
    $warn_check = $conn->prepare("SELECT ticket_number, status, DATE(created_at) as created_date FROM maintenances WHERE client_name = ? AND address = ? AND status = 'Pending' AND DATE(created_at) < CURDATE() LIMIT 1");
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
                    'message'  => 'This client already has a Pending maintenance ticket from a previous date. Do you still want to create a new one?',
                    'ticket'   => $warn['ticket_number'],
                    'status'   => $warn['status'],
                    'created'  => $warn['created_date'],
                ]);
                exit;
            }
        }
        $warn_check->close();
    }

    $valid_issue_types = ['No Connection','Slow Speed','Intermittent Connection','Equipment Issue','Line Cut','Signal Weak','Server Upgrade','Server Maintenance','Request','Laser Out','Low Optical Reading','Pull Out','Other'];
    if (!in_array($issue_type, $valid_issue_types)) throw new Exception('Invalid issue type.');

    $valid_priorities = ['Less Priority','Normal','Urgent'];
    if (!in_array($priority, $valid_priorities)) throw new Exception('Invalid priority level.');

    $ticket_query = $conn->query("SELECT MAX(id) as max_id FROM maintenances");
    $next_id = ($ticket_query->fetch_assoc()['max_id'] ?? 0) + 1;
    $ticket_number = 'MAINT-' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO maintenances (ticket_number, client_name, address, contact_number, email, account_number, issue_type, priority, description, scheduled_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error: ' . $conn->error);

    $stmt->bind_param('ssssssssss', $ticket_number, $client_name, $address, $contact_number, $email, $account_number, $issue_type, $priority, $description, $scheduled_date);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        logActivity($conn, 'create_ticket', 'maintenance', $new_id, 'Created maintenance ticket ' . $ticket_number . ' for ' . $client_name);
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
