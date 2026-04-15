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
    $issue_type = $_POST['issue_type'] ?? '';
    $priority = $_POST['priority'] ?? 'Normal';
    $issue_description = trim($_POST['issue_description'] ?? '');

    // Validate required fields
    if ($ticket_id <= 0 || empty($client_name) || empty($issue_type) || empty($priority) || empty($issue_description)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Validate issue type
    $valid_issue_types = ['No Connection','Slow Speed','Intermittent Connection','Equipment Issue','Line Cut','Signal Weak','Server Upgrade','Server Maintenance','Request','Laser Out','Low Optical Reading','Pull Out','Other'];
    if (!in_array($issue_type, $valid_issue_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue type']);
        exit;
    }

    // Validate priority
    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        $priority = 'Normal';
    }

    // Update maintenance ticket
    $sql = "UPDATE maintenances SET 
            client_name = ?, 
            address = ?, 
            contact_number = ?, 
            issue_type = ?, 
            priority = ?, 
            description = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('ssssssi', 
        $client_name, $address, $contact_number, $issue_type, $priority, $issue_description, $ticket_id
    );

    if ($stmt->execute()) {
        notify_db_change($conn);
        echo json_encode(['success' => true, 'message' => 'Maintenance ticket updated successfully']);
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
