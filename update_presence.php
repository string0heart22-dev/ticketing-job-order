<?php
require_once 'session_init.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userID'])) {
    echo json_encode(['success' => false, 'reason' => 'no_session']);
    exit();
}

$col = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_seen DATETIME NULL DEFAULT NULL");
}

$uid = intval($_SESSION['userID']);
$stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE userID = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'uid' => $uid]);
