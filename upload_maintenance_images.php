<?php
require_once 'auth_check.php';
session_start();
require_once 'config.php';
require_once 'db_notify.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$maintenance_id = intval($_POST['maintenance_id'] ?? 0);

if ($maintenance_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid maintenance ID']);
    exit;
}

// Create maintenance_images table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS maintenance_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (maintenance_id) REFERENCES maintenances(id) ON DELETE CASCADE
)";
$conn->query($create_table_sql);

// Central images directory - works on both local and Hostinger
$upload_dir = __DIR__ . '/IMAGES/maintenance/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Check if files were uploaded
if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No images selected']);
    exit;
}

$uploaded_count = 0;
$errors = [];

// Process each uploaded file
foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading ' . $_FILES['images']['name'][$key];
        continue;
    }
    
    // Validate file type
    $file_type = $_FILES['images']['type'][$key];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
    
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = $_FILES['images']['name'][$key] . ' is not a valid image type';
        continue;
    }
    
    // Validate file size (max 5MB)
    if ($_FILES['images']['size'][$key] > 5 * 1024 * 1024) {
        $errors[] = $_FILES['images']['name'][$key] . ' is too large (max 5MB)';
        continue;
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
    $filename = 'maint_' . $maintenance_id . '_' . time() . '_' . $key . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($tmp_name, $filepath)) {
        // Save web-accessible path to database
        $web_path = '/IMAGES/maintenance/' . $filename;
        
        // Save to database
        $sql = "INSERT INTO maintenance_images (maintenance_id, image_path) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $maintenance_id, $web_path);
        
        if ($stmt->execute()) {
            $uploaded_count++;
        } else {
            $errors[] = 'Database error for ' . $_FILES['images']['name'][$key];
            unlink($filepath); // Delete file if database insert fails
        }
        $stmt->close();
    } else {
        $errors[] = 'Failed to save ' . $_FILES['images']['name'][$key];
    }
}

if ($uploaded_count > 0) {
    $message = "$uploaded_count image(s) uploaded successfully";
    if (count($errors) > 0) {
        $message .= '. Some files failed: ' . implode(', ', $errors);
    }
    notify_db_change($conn);
    $conn->close();
    echo json_encode(['success' => true, 'message' => $message, 'count' => $uploaded_count]);
} else {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'No images were uploaded. ' . implode(', ', $errors)]);
}
?>
