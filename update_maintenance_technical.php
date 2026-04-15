<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance_id = intval($_POST['maintenance_id'] ?? 0);
    $service_type = trim($_POST['service_type'] ?? '');
    $fiber_core_count = trim($_POST['fiber_core_count'] ?? '');
    $optical_reading = trim($_POST['optical_reading'] ?? '');
    $speed_test = trim($_POST['speed_test'] ?? '');
    $ping = trim($_POST['ping'] ?? '');
    $work_done = trim($_POST['work_done'] ?? '');
    $problem_cause = trim($_POST['problem_cause'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $accepts_member = trim($_POST['accepts_member'] ?? '');

    if ($maintenance_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid maintenance ID']);
        exit;
    }

    // Validate service type
    $valid_service_types = ['Client', 'Distribution Line', 'Main Line', 'Transport Line', 'Server', 'Tower'];
    if (!empty($service_type) && !in_array($service_type, $valid_service_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid service type']);
        exit;
    }

    // Check if accepts_member column exists
    $check_col = $conn->query("SHOW COLUMNS FROM maintenances LIKE 'accepts_member'");
    $has_accepts_member = ($check_col && $check_col->num_rows > 0);

    // Update technical data
    if ($has_accepts_member) {
        $sql = "UPDATE maintenances SET 
                service_type = ?,
                fiber_core_count = ?,
                optical_reading = ?,
                speed_test = ?,
                ping = ?,
                work_done = ?,
                problem_cause = ?,
                comment = ?,
                accepts_member = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('sssssssssi', $service_type, $fiber_core_count, $optical_reading, $speed_test, $ping, $work_done, $problem_cause, $comment, $accepts_member, $maintenance_id);
    } else {
        $sql = "UPDATE maintenances SET 
                service_type = ?,
                fiber_core_count = ?,
                optical_reading = ?,
                speed_test = ?,
                ping = ?,
                work_done = ?,
                problem_cause = ?,
                comment = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('ssssssssi', $service_type, $fiber_core_count, $optical_reading, $speed_test, $ping, $work_done, $problem_cause, $comment, $maintenance_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Technical data updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
