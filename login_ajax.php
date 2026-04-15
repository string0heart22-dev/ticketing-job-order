<?php
require_once 'session_init.php';
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_POST['login'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0 || !password_verify($password, ($user = $result->fetch_assoc())['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit();
}

$_SESSION['userID']        = $user['userID'];
$_SESSION['name']          = $user['name'];
$_SESSION['username']      = $user['name']; // For request.php compatibility
$_SESSION['email']         = $user['email'];
$_SESSION['role']          = $user['role'];
$_SESSION['LAST_ACTIVITY'] = time();

// Build base path so it works on both XAMPP (subfolder) and Hostinger (root)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if ($user['role'] === 'admin') {
    $redirect = $base . '/htmlpage.php';
} else {
    $redirect = $base . '/PAGEFORUSER/htmlpage.php';
}

echo json_encode(['success' => true, 'redirect' => $redirect]);
