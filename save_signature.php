<?php
session_start();
require_once 'config.php';
require_once 'db_notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$installation_id = intval($_POST['installation_id'] ?? 0);
$signature_data = $_POST['signature_data'] ?? '';

if ($installation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid installation ID']);
    exit;
}

if (empty($signature_data)) {
    echo json_encode(['success' => false, 'message' => 'No signature data provided']);
    exit;
}

// Update the installation record with signature
$sql = "UPDATE installations SET client_signature = ?, signature_date = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('si', $signature_data, $installation_id);

if ($stmt->execute()) {
    notify_db_change($conn);
    echo json_encode([
        'success' => true,
        'message' => 'Signature saved successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save signature: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
