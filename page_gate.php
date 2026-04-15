<?php
/**
 * Page Gate — verify or change the Users page access password.
 * Stored as a hashed value in the DB settings table (key = 'users_page_password').
 * Default password: admin123
 */
require_once 'session_init.php';
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $_POST['action'] ?? '';

// Ensure settings table exists with a default password row
$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default password if not set
$row = $conn->query("SELECT `value` FROM app_settings WHERE `key` = 'users_page_password'")->fetch_assoc();
if (!$row) {
    $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('users_page_password', ?)");
    $ins->bind_param('s', $default_hash);
    $ins->execute();
    $ins->close();
    $row = ['value' => $default_hash];
}
$stored_hash = $row['value'];

// ── Verify password ──────────────────────────────────────────────────────────
if ($action === 'verify') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $stored_hash)) {
        $_SESSION['users_page_unlocked'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    }
    exit;
}

// ── Reset password ───────────────────────────────────────────────────────────
if ($action === 'reset') {
    $code = $_POST['code'] ?? '';
    $required_code = '&#(*$(#))99g';

    if ($code !== $required_code) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset code.']);
        exit;
    }

    $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE app_settings SET `value` = ? WHERE `key` = 'users_page_password'");
    $upd->bind_param('s', $default_hash);
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'message' => 'Password reset to admin123']);
    exit;
}

// ── Change password ──────────────────────────────────────────────────────────
if ($action === 'change') {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!password_verify($current, $stored_hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    if (strlen($new_pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }
    if ($new_pass !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE app_settings SET `value` = ? WHERE `key` = 'users_page_password'");
    $upd->bind_param('s', $new_hash);
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
