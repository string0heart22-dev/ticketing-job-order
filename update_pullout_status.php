<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once "config.php";
require_once 'status_history_functions.php';
require_once 'db_notify.php';
require_once 'log_activity.php';

// Log to a file for debugging
$log_file = 'pullout_status_debug.log';
$log_data = date('Y-m-d H:i:s') . " - Request received\n";
$log_data .= "POST data: " . print_r($_POST, true) . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    $log_data = "Ticket ID: $ticket_id, Status: $status\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    
    if ($ticket_id <= 0) {
        throw new Exception('Invalid ticket ID');
    }

    $allowed_statuses = ['Pending', 'In Progress', 'Closed', 'Negative', 'Reconnected'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid status: ' . $status);
    }
    
    // Get current status and ticket number for history tracking
    $old_status = getCurrentStatus('pullout', $ticket_id);
    $ticket_number = getTicketNumber('pullout', $ticket_id);
    $user_id = getCurrentUserId();

    // Check if end_time and completed_time columns exist
    $check_sql = "SHOW COLUMNS FROM pullout_tickets LIKE 'end_time'";
    $result = $conn->query($check_sql);
    $has_end_time = ($result && $result->num_rows > 0);
    
    $check_sql2 = "SHOW COLUMNS FROM pullout_tickets LIKE 'completed_time'";
    $result2 = $conn->query($check_sql2);
    $has_completed_time = ($result2 && $result2->num_rows > 0);
    
    // Add end_time column if it doesn't exist
    if (!$has_end_time) {
        $conn->query("ALTER TABLE pullout_tickets ADD COLUMN end_time TIMESTAMP NULL DEFAULT NULL AFTER status");
        $has_end_time = true;
    }
    
    // Add completed_time column if it doesn't exist
    if (!$has_completed_time) {
        $conn->query("ALTER TABLE pullout_tickets ADD COLUMN completed_time DATETIME NULL AFTER created_at");
        $has_completed_time = true;
    }
    
    // Define final statuses that should set end_time
    $final_statuses = ['Closed', 'Negative', 'Reconnected'];
    
    // If status is changed to Pending, unassign the ticket and clear times
    if ($status == 'Pending') {
        $sql = $has_end_time ? 
            "UPDATE pullout_tickets SET status = ?, assigned_to = NULL, start_time = NULL, end_time = NULL, in_progress_time = NULL, closed_time = NULL, negative_time = NULL, reconnected_time = NULL WHERE id = ?" :
            "UPDATE pullout_tickets SET status = ?, assigned_to = NULL, start_time = NULL, in_progress_time = NULL, closed_time = NULL, negative_time = NULL, reconnected_time = NULL WHERE id = ?";
    } elseif ($status == 'In Progress') {
        // Set start_time and in_progress_time when status changes to In Progress
        $sql = "UPDATE pullout_tickets SET status = ?, start_time = COALESCE(start_time, NOW()), in_progress_time = NOW() WHERE id = ?";
    } elseif ($status == 'Closed') {
        // Set end_time, closed_time, and completed_time when status changes to Closed
        $sql = "UPDATE pullout_tickets SET status = ?, end_time = NOW(), closed_time = NOW(), completed_time = NOW() WHERE id = ?";
    } elseif ($status == 'Negative') {
        // Set end_time, negative_time, and completed_time when status changes to Negative
        $sql = "UPDATE pullout_tickets SET status = ?, end_time = NOW(), negative_time = NOW(), completed_time = NOW() WHERE id = ?";
    } elseif ($status == 'Reconnected') {
        // Set end_time, reconnected_time, and completed_time when status changes to Reconnected
        $sql = "UPDATE pullout_tickets SET status = ?, end_time = NOW(), reconnected_time = NOW(), completed_time = NOW() WHERE id = ?";
    } else {
        $sql = "UPDATE pullout_tickets SET status = ? WHERE id = ?";
    }
    
    $log_data = "SQL: $sql\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $status, $ticket_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $log_data = "Affected rows: $affected_rows\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);

    // Record status change in history
    $notes = "Status changed from " . ($old_status ?? 'Initial') . " to {$status}";
    if (in_array($status, $final_statuses)) {
        $notes .= " (Final status - end time recorded)";
    }
    
    recordStatusChange('pullout', $ticket_id, $ticket_number, $old_status, $status, $user_id, $notes);
    logActivity($conn, 'update_status', 'pullout', $ticket_id, 'Updated pullout ticket #' . $ticket_id . ' status to ' . $status);

    $message = 'Status updated';
    if (in_array($status, $final_statuses) && $has_end_time) {
        $message .= ' and end time recorded';
    }

    notify_db_change($conn);
    echo json_encode(['success' => true, 'message' => $message, 'affected_rows' => $affected_rows]);
    
} catch (Exception $e) {
    $log_data = "Error: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}

$log_data = "---END REQUEST---\n\n";
file_put_contents($log_file, $log_data, FILE_APPEND);
?>
