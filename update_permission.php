<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userID = $_POST['userID'] ?? '';
$page = $_POST['page'] ?? '';
$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if (empty($userID) || empty($page) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$validPages = ['tickets_installation', 'tickets', 'tickets_maintenance', 'tickets_pullout', 'service_areas', 'service_plans', 'inventory', 'reports', 'users', 'installation_form', 'olt'];
$validTypes = ['access', 'delete'];

if (!in_array($page, $validPages) || !in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid page or type']);
    exit;
}

// Add column if it doesn't exist
$column = $type === 'access' ? "can_$page" : "can_delete_$page";
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS $column TINYINT(1) DEFAULT 1");

$sql = "UPDATE users SET $column = ? WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $value, $userID);

if ($stmt->execute()) {
    // Update session if the current user is being edited
    if (isset($_SESSION['userID']) && $_SESSION['userID'] == $userID) {
        $sessionKey = $type === 'access' ? "can_$page" : "can_delete_$page";
        $_SESSION[$sessionKey] = $value;
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
