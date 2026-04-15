<?php
// Use centralized session initialization
require_once 'auth_check.php';
require_once 'session_init.php';
require_once 'config.php';
require_once 'status_history_functions.php';
require_once 'db_notify.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($ticket_id <= 0) {
        throw new Exception('Invalid ticket ID');
    }

    $valid_statuses = ['Pending', 'In Progress', 'Installed', 'Cancelled', 'Negative'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }

    // Get current status and ticket number for history tracking
    $old_status = getCurrentStatus('installation', $ticket_id);
    $ticket_number = getTicketNumber('installation', $ticket_id);
    $user_id = getCurrentUserId();

    // Check if status_comment column exists, add it if not
    $check_comment_col = $conn->query("SHOW COLUMNS FROM installations LIKE 'status_comment'");
    if ($check_comment_col && $check_comment_col->num_rows == 0) {
        $conn->query("ALTER TABLE installations ADD COLUMN status_comment TEXT NULL AFTER status");
    }
    
    // Get comment if provided
    $comment = $_POST['comment'] ?? null;
    $check_sql = "SHOW COLUMNS FROM installations LIKE 'end_time'";
    $result = $conn->query($check_sql);
    $has_end_time = ($result && $result->num_rows > 0);
    
    // Add end_time column if it doesn't exist
    if (!$has_end_time) {
        $conn->query("ALTER TABLE installations ADD COLUMN end_time TIMESTAMP NULL DEFAULT NULL AFTER status");
        $has_end_time = true;
    }

    // Define final statuses that should set end_time
    $final_statuses = ['Installed', 'Cancelled', 'Negative'];

    // If status is changed to Pending, unassign the ticket and clear times
    if ($status == 'Pending') {
        $sql = $has_end_time ? 
            "UPDATE installations SET status = ?, assigned_to = NULL, start_time = NULL, end_time = NULL, in_progress_time = NULL, installed_time = NULL, cancelled_time = NULL, negative_time = NULL WHERE id = ?" :
            "UPDATE installations SET status = ?, assigned_to = NULL, start_time = NULL, in_progress_time = NULL, installed_time = NULL, cancelled_time = NULL, negative_time = NULL WHERE id = ?";
    } elseif ($status == 'In Progress') {
        // Set start_time and in_progress_time when status changes to In Progress
        $sql = "UPDATE installations SET status = ?, start_time = COALESCE(start_time, NOW()), in_progress_time = NOW() WHERE id = ?";
    } elseif ($status == 'Installed') {
        // Set end_time and installed_time when status changes to Installed
        $sql = $has_end_time ? 
            "UPDATE installations SET status = ?, end_time = NOW(), installed_time = NOW() WHERE id = ?" :
            "UPDATE installations SET status = ?, installed_time = NOW() WHERE id = ?";
    } elseif ($status == 'Cancelled') {
        // Set end_time and cancelled_time when status changes to Cancelled
        $sql = $has_end_time ? 
            "UPDATE installations SET status = ?, end_time = NOW(), cancelled_time = NOW(), status_comment = ? WHERE id = ?" :
            "UPDATE installations SET status = ?, cancelled_time = NOW(), status_comment = ? WHERE id = ?";
    } elseif ($status == 'Negative') {
        // Set end_time and negative_time when status changes to Negative
        $sql = $has_end_time ? 
            "UPDATE installations SET status = ?, end_time = NOW(), negative_time = NOW(), status_comment = ? WHERE id = ?" :
            "UPDATE installations SET status = ?, negative_time = NOW(), status_comment = ? WHERE id = ?";
    } else {
        $sql = "UPDATE installations SET status = ? WHERE id = ?";
    }
    
    // Clear status_comment when moving away from Cancelled/Negative
    if (in_array($old_status, ['Cancelled', 'Negative']) && !in_array($status, ['Cancelled', 'Negative'])) {
        $sql = str_replace('WHERE id = ?', ', status_comment = NULL WHERE id = ?', $sql);
    }

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    // Bind parameters based on SQL
    if (strpos($sql, 'status_comment = ?') !== false) {
        $stmt->bind_param('ssi', $status, $comment, $ticket_id);
    } else {
        $stmt->bind_param('si', $status, $ticket_id);
    }

    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }

    // Record status change in history
    $notes = "Status changed from " . ($old_status ?? 'Initial') . " to {$status}";
    if (in_array($status, $final_statuses)) {
        $notes .= " (Final status - end time recorded)";
    }
    
    recordStatusChange('installation', $ticket_id, $ticket_number, $old_status, $status, $user_id, $notes);

    $message = 'Status updated successfully';
    if (in_array($status, $final_statuses) && $has_end_time) {
        $message .= ' and end time recorded';
    }

    notify_db_change($conn);
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
exit;
?>
