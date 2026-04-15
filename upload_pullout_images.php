<?php
require_once 'auth_check.php';
session_start();
require_once 'config.php';
require_once 'db_notify.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $pullout_id = intval($_POST['pullout_id'] ?? 0);
    
    if ($pullout_id <= 0) {
        throw new Exception('Invalid pullout ID');
    }

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception('No images uploaded');
    }

    // Central images directory - works on both local and Hostinger
    $upload_dir = __DIR__ . '/IMAGES/pullout/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $uploaded_files = [];
    $files = $_FILES['images'];
    $file_count = count($files['name']);
    $errors = [];

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $files['name'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate image
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];
            if (!in_array($file_ext, $allowed_ext)) {
                $errors[] = "$file_name: Invalid file type (only JPG, PNG, GIF, WEBP allowed)";
                continue;
            }

            // Validate file size (max 5MB)
            if ($files['size'][$i] > 5 * 1024 * 1024) {
                $errors[] = "$file_name: File too large (max 5MB)";
                continue;
            }

            // Generate unique filename
            $new_filename = 'pullout_' . $pullout_id . '_' . time() . '_' . $i . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Save web-accessible path to database
                $web_path = '/IMAGES/pullout/' . $new_filename;
                
                // Save to database
                $sql = "INSERT INTO pullout_images (pullout_id, image_path) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $errors[] = "$file_name: Database prepare error";
                    continue;
                }
                
                $stmt->bind_param('is', $pullout_id, $web_path);
                
                if ($stmt->execute()) {
                    $uploaded_files[] = [
                        'id' => $stmt->insert_id,
                        'path' => $web_path,
                        'filename' => $new_filename
                    ];
                } else {
                    $errors[] = "$file_name: Database insert error";
                }
                $stmt->close();
            } else {
                $errors[] = "$file_name: Failed to move uploaded file";
            }
        } else {
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
            $errors[] = ($files['name'][$i] ?? 'Unknown file') . ": $error_msg";
        }
    }

    if (empty($uploaded_files)) {
        $error_message = 'No images were uploaded successfully.';
        if (!empty($errors)) {
            $error_message .= ' Errors: ' . implode('; ', $errors);
        }
        throw new Exception($error_message);
    }

    $response = [
        'success' => true,
        'message' => count($uploaded_files) . ' image(s) uploaded successfully',
        'files' => $uploaded_files
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    notify_db_change($conn);
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
