<?php
require_once 'auth_check.php';
session_start();
require_once 'config.php';
require_once 'db_notify.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $installation_date = $_POST['installation_date'] ?? '';
    $nap_assignment = trim($_POST['nap_assignment'] ?? '');
    $connection_type = $_POST['connection_type'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $plan_id = intval($_POST['plan'] ?? 0); // Now receiving plan_id
    $contract_duration = $_POST['contract_duration'] ?? '12';
    $priority = $_POST['priority'] ?? 'Normal';
    $prepaid_amount = floatval($_POST['prepaid_amount'] ?? 0);

    // Validate required fields (address, contact_number, installation_date, nap_assignment, account_number, and prepaid_amount are now optional)
    if ($ticket_id <= 0 || empty($client_name) || empty($connection_type) || empty($service_type) || empty($plan_id) || empty($contract_duration)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Fetch plan details from service_plans table
    $plan_query = $conn->prepare("SELECT plan_name, monthly_fee FROM service_plans WHERE id = ? AND status = 'Active'");
    $plan_query->bind_param('i', $plan_id);
    $plan_query->execute();
    $plan_result = $plan_query->get_result();
    
    if ($plan_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan selected']);
        exit;
    }
    
    $plan_data = $plan_result->fetch_assoc();
    $plan_display = $plan_data['monthly_fee']; // Store monthly fee for backward compatibility
    $plan_query->close();

    // Validate connection type
    $valid_connection_types = ['Wireless', 'Fiber'];
    if (!in_array($connection_type, $valid_connection_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid connection type']);
        exit;
    }

    // Validate service type
    $valid_service_types = ['New Client', 'Migrate', 'Reconnection as New Client'];
    if (!in_array($service_type, $valid_service_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid service type']);
        exit;
    }

    // Validate contract duration (now dynamic based on plans)
    if (empty($contract_duration) || !is_numeric($contract_duration)) {
        echo json_encode(['success' => false, 'message' => 'Invalid contract duration']);
        exit;
    }

    // Validate priority
    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        $priority = 'Normal';
    }

    // Update ticket
    $sql = "UPDATE installations SET 
            client_name = ?, 
            address = ?, 
            contact_number = ?, 
            email = ?, 
            account_number = ?, 
            installation_date = ?, 
            nap_assignment = ?, 
            connection_type = ?, 
            service_type = ?, 
            plan = ?,
            plan_id = ?,
            contract_duration = ?,
            priority = ?,
            prepaid_amount = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('ssssssssssissdi', 
        $client_name, $address, $contact_number, $email, $account_number, 
        $installation_date, $nap_assignment, $connection_type, $service_type, 
        $plan_display, $plan_id, $contract_duration, $priority, $prepaid_amount, $ticket_id
    );

    if ($stmt->execute()) {
        notify_db_change($conn);
    echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update ticket: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
