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
    $client_name      = trim($_POST['client_name']      ?? '');
    $address          = trim($_POST['address']          ?? '');
    $contact_number   = trim($_POST['contact_number']   ?? '');
    $email            = trim($_POST['email']            ?? '');
    $account_number   = trim($_POST['account_number']   ?? '');
    $installation_date = $_POST['installation_date']   ?? '';
    $nap_assignment   = trim($_POST['nap_assignment']   ?? '');
    $prepaid_amount   = floatval($_POST['prepaid_amount'] ?? 0);
    $connection_type  = $_POST['connection_type']       ?? '';
    $plan_id          = intval($_POST['plan']           ?? 0);
    $service_type     = $_POST['service_type']          ?? '';
    $contract_duration = $_POST['contract_duration']   ?? '12';
    $priority         = $_POST['priority']              ?? 'Normal';

    if (empty($client_name) || empty($connection_type) || empty($plan_id) || empty($service_type) || empty($contract_duration)) {
        throw new Exception('All required fields must be filled out.');
    }

    // Duplicate check: same client + address created today (hard block, unless forced)
    if (empty($_POST['force_create'])) {
        $dup_check = $conn->prepare("SELECT ticket_number, status FROM installations WHERE client_name = ? AND address = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        if ($dup_check) {
            $dup_check->bind_param('ss', $client_name, $address);
            $dup_check->execute();
            $dup_result = $dup_check->get_result();
            if ($dup_result->num_rows > 0) {
                $existing = $dup_result->fetch_assoc();
                echo json_encode([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => 'A ticket for this client and address was already created today.',
                    'ticket'    => $existing['ticket_number'],
                    'status'    => $existing['status'],
                ]);
                exit;
            }
            $dup_check->close();
        }
    }

    // Pending warning: same client + address still Pending from a previous day
    $warn_check = $conn->prepare("SELECT ticket_number, status, DATE(created_at) as created_date FROM installations WHERE client_name = ? AND address = ? AND status = 'Pending' AND DATE(created_at) < CURDATE() LIMIT 1");
    if ($warn_check) {
        $warn_check->bind_param('ss', $client_name, $address);
        $warn_check->execute();
        $warn_result = $warn_check->get_result();
        if ($warn_result->num_rows > 0) {
            $warn = $warn_result->fetch_assoc();
            // Only warn if not explicitly bypassed
            if (empty($_POST['force_create'])) {
                echo json_encode([
                    'success'  => false,
                    'warning'  => true,
                    'message'  => 'This client already has a Pending ticket from a previous date. Do you still want to create a new one?',
                    'ticket'   => $warn['ticket_number'],
                    'status'   => $warn['status'],
                    'created'  => $warn['created_date'],
                ]);
                exit;
            }
        }
        $warn_check->close();
    }

    $plan_query = $conn->prepare("SELECT plan_name, monthly_fee FROM service_plans WHERE id = ? AND status = 'Active'");
    if (!$plan_query) throw new Exception('Database error: ' . $conn->error);
    $plan_query->bind_param('i', $plan_id);
    $plan_query->execute();
    $plan_result = $plan_query->get_result();
    if ($plan_result->num_rows === 0) throw new Exception('Invalid plan selected.');
    $plan_data = $plan_result->fetch_assoc();
    $plan_display = $plan_data['monthly_fee'];
    $plan_query->close();

    $valid_connection_types = ['Wireless', 'Fiber'];
    if (!in_array($connection_type, $valid_connection_types)) throw new Exception('Invalid connection type.');

    $valid_service_types = ['New Client', 'Migrate', 'Reconnection as New Client'];
    if (!in_array($service_type, $valid_service_types)) throw new Exception('Invalid service type.');

    if (!is_numeric($contract_duration)) throw new Exception('Invalid contract duration.');

    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) $priority = 'Normal';

    $sql = "INSERT INTO installations (
        client_name, address, contact_number, email, account_number,
        installation_date, nap_assignment, prepaid_amount, connection_type,
        plan, plan_id, service_type, contract_duration, priority
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Database error: ' . $conn->error);

    $stmt->bind_param(
        'sssssssdssssss',
        $client_name, $address, $contact_number, $email, $account_number,
        $installation_date, $nap_assignment, $prepaid_amount, $connection_type,
        $plan_display, $plan_id, $service_type, $contract_duration, $priority
    );

    if (!$stmt->execute()) throw new Exception('Error creating ticket: ' . $stmt->error);

    $installation_id = $stmt->insert_id;
    $stmt->close();

    $ticket_number = 'INST-' . str_pad($installation_id, 5, '0', STR_PAD_LEFT);
    $update_stmt = $conn->prepare("UPDATE installations SET ticket_number = ? WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param('si', $ticket_number, $installation_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    logActivity($conn, 'create_ticket', 'installation', $installation_id, 'Created installation ticket ' . $ticket_number . ' for ' . $client_name);
    notify_db_change($conn);
    echo json_encode(['success' => true, 'ticket_number' => $ticket_number]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
exit;
