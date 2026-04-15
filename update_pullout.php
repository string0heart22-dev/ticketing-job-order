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
    $priority = $_POST['priority'] ?? 'Normal';
    $reason = trim($_POST['reason'] ?? '');

    // Validate required fields
    if ($ticket_id <= 0 || empty($client_name) || empty($priority) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Validate priority
    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        $priority = 'Normal';
    }

    // Update pullout ticket
    $sql = "UPDATE pullout_tickets SET 
            client_name = ?, 
            address = ?, 
            contact_number = ?, 
            priority = ?, 
            reason = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sssssi', 
        $client_name, $address, $contact_number, $priority, $reason, $ticket_id
    );

    if ($stmt->execute()) {
        notify_db_change($conn);
        echo json_encode(['success' => true, 'message' => 'Pullout ticket updated successfully']);
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
