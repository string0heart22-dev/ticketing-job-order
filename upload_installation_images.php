<?php
// Version: 2.1 - Updated 2026-03-21
require_once 'auth_check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_write_close(); // release session lock immediately - we only need to read userID
require_once 'config.php';
require_once 'db_notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $user_id = $_SESSION['userID'] ?? null;

    if ($installation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid installation ID']);
        exit;
    }

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'No images uploaded']);
        exit;
    }

    // Central images directory - works on both local and Hostinger
    $upload_dir = __DIR__ . '/IMAGES/installations/';

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Check permissions.']);
            exit;
        }
    }

    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable. Check permissions.']);
        exit;
    }

    $uploaded_count = 0;
    $errors = [];
    $uploaded_image_ids = []; // Track IDs of uploaded images

    $files = $_FILES['images'];
    $file_count = count($files['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error_msg = match($files['error'][$i]) {
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
                default => 'Unknown upload error'
            };
            $errors[] = $files['name'][$i] . ": " . $error_msg;
            continue;
        }

        // Validate file type by extension (more reliable than MIME type)
        $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];

        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = $files['name'][$i] . ": Invalid file type (only JPG, PNG, GIF, WEBP allowed)";
            continue;
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024;
        if ($files['size'][$i] > $max_size) {
            $errors[] = $files['name'][$i] . ": File too large (max 5MB)";
            continue;
        }

        // Generate unique filename
        $filename = 'installation_' . $installation_id . '_' . time() . '_' . $i . '.' . $extension;
        $filepath = $upload_dir . $filename;

        // Move uploaded file
        if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
            // Save web-accessible path to database
            $web_path = '/IMAGES/installations/' . $filename;

            // Insert into database
            $sql = "INSERT INTO installation_images (installation_id, image_path, image_name, image_size, image_type, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $file_type = $files['type'][$i];
                $stmt->bind_param('issisi', $installation_id, $web_path, $files['name'][$i], $files['size'][$i], $file_type, $user_id);

                if ($stmt->execute()) {
                    $uploaded_count++;
                    $uploaded_image_ids[] = $stmt->insert_id; // Track the ID
                } else {
                    $errors[] = $files['name'][$i] . ": Database error - " . $stmt->error;
                    if (file_exists($filepath)) unlink($filepath);
                }

                $stmt->close();
            } else {
                $errors[] = $files['name'][$i] . ": Failed to prepare database statement";
                if (file_exists($filepath)) unlink($filepath);
            }
        } else {
            $errors[] = $files['name'][$i] . ": Failed to move uploaded file";
        }
    }

    if ($uploaded_count > 0) {
        $message = "$uploaded_count image(s) uploaded successfully";
        if (count($errors) > 0) {
            $message .= ". Some files failed: " . implode(', ', $errors);
        }
        
        // Fetch the newly uploaded images by their specific IDs
        $uploaded_images = [];
        if (count($uploaded_image_ids) > 0) {
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($uploaded_image_ids), '?'));
            $fetch_sql = "SELECT * FROM installation_images WHERE id IN ($placeholders) ORDER BY id DESC";
            $fetch_stmt = $conn->prepare($fetch_sql);
            if ($fetch_stmt) {
                // Bind all the IDs dynamically
                $types = str_repeat('i', count($uploaded_image_ids));
                $fetch_stmt->bind_param($types, ...$uploaded_image_ids);
                $fetch_stmt->execute();
                $fetch_result = $fetch_stmt->get_result();
                while ($img = $fetch_result->fetch_assoc()) {
                    $uploaded_images[] = $img;
                }
                $fetch_stmt->close();
            }
        }
        
        // Update installation timestamp to trigger sync detection
        $update_sql = "UPDATE installations SET updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param('i', $installation_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        notify_db_change($conn);
        $conn->close();
        echo json_encode([
            'success' => true, 
            'message' => $message, 
            'uploaded_count' => $uploaded_count,
            'images' => $uploaded_images
        ]);
    } else {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'No images uploaded. Errors: ' . implode(', ', $errors)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>
