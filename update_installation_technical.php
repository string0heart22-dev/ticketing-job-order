<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $nap_assignment = trim($_POST['nap_assignment'] ?? '');
    $nap_optical_reading = trim($_POST['nap_optical_reading'] ?? '');
    $client_optical_reading = trim($_POST['client_optical_reading'] ?? '');
    $speed_test_mbps = trim($_POST['speed_test_mbps'] ?? '');
    $accepts_member = trim($_POST['accepts_member'] ?? '');

    if ($installation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid installation ID']);
        exit;
    }

    // Check if accepts_member column exists
    $check_col = $conn->query("SHOW COLUMNS FROM installations LIKE 'accepts_member'");
    $has_accepts_member = ($check_col && $check_col->num_rows > 0);

    // Update technical data
    if ($has_accepts_member) {
        $sql = "UPDATE installations SET 
                nap_assignment = ?,
                nap_optical_reading = ?,
                client_optical_reading = ?,
                speed_test_mbps = ?,
                accepts_member = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('sssssi', $nap_assignment, $nap_optical_reading, $client_optical_reading, $speed_test_mbps, $accepts_member, $installation_id);
    } else {
        $sql = "UPDATE installations SET 
                nap_assignment = ?,
                nap_optical_reading = ?,
                client_optical_reading = ?,
                speed_test_mbps = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('ssssi', $nap_assignment, $nap_optical_reading, $client_optical_reading, $speed_test_mbps, $installation_id);
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
