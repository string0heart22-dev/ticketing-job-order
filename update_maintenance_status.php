<?php
require_once 'session_init.php';
require_once 'config.php';
require_once 'db_notify.php';
require_once 'status_history_functions.php';
require_once 'log_activity.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($ticket_id <= 0) throw new Exception('Invalid ticket ID');

    $valid_statuses = ['Pending', 'In Progress', 'Completed', 'Cancelled', 'On Hold', 'Closed'];
    if (!in_array($status, $valid_statuses)) throw new Exception('Invalid status');

    $old_status = getCurrentStatus('maintenance', $ticket_id);
    $ticket_number = getTicketNumber('maintenance', $ticket_id);
    $user_id = getCurrentUserId();

    $check_sql = "SHOW COLUMNS FROM maintenances LIKE 'end_time'";
    $result = $conn->query($check_sql);
    $has_end_time = ($result && $result->num_rows > 0);
    if (!$has_end_time) {
        $conn->query("ALTER TABLE maintenances ADD COLUMN end_time TIMESTAMP NULL DEFAULT NULL AFTER status");
        $has_end_time = true;
    }

    $final_statuses = ['Completed', 'Cancelled', 'On Hold', 'Closed'];

    if ($status == 'Completed') {
        $sql = $has_end_time
            ? "UPDATE maintenances SET status = ?, completion_date = NOW(), end_time = NOW(), completed_time = NOW() WHERE id = ?"
            : "UPDATE maintenances SET status = ?, completion_date = NOW(), completed_time = NOW() WHERE id = ?";
    } elseif ($status == 'Pending') {
        $sql = $has_end_time
            ? "UPDATE maintenances SET status = ?, assigned_to = NULL, start_time = NULL, end_time = NULL, in_progress_time = NULL, completed_time = NULL, cancelled_time = NULL, on_hold_time = NULL WHERE id = ?"
            : "UPDATE maintenances SET status = ?, assigned_to = NULL, start_time = NULL, in_progress_time = NULL, completed_time = NULL, cancelled_time = NULL, on_hold_time = NULL WHERE id = ?";
    } elseif ($status == 'In Progress') {
        $sql = "UPDATE maintenances SET status = ?, start_time = COALESCE(start_time, NOW()), in_progress_time = NOW() WHERE id = ?";
    } elseif ($status == 'Cancelled') {
        $sql = $has_end_time
            ? "UPDATE maintenances SET status = ?, end_time = NOW(), cancelled_time = NOW() WHERE id = ?"
            : "UPDATE maintenances SET status = ?, cancelled_time = NOW() WHERE id = ?";
    } elseif ($status == 'On Hold') {
        $sql = $has_end_time
            ? "UPDATE maintenances SET status = ?, end_time = NOW(), on_hold_time = NOW() WHERE id = ?"
            : "UPDATE maintenances SET status = ?, on_hold_time = NOW() WHERE id = ?";
    } elseif ($status == 'Closed') {
        $sql = $has_end_time
            ? "UPDATE maintenances SET status = ?, end_time = NOW() WHERE id = ?"
            : "UPDATE maintenances SET status = ? WHERE id = ?";
    } else {
        $sql = "UPDATE maintenances SET status = ? WHERE id = ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Database prepare error: ' . $conn->error);
    $stmt->bind_param('si', $status, $ticket_id);
    if (!$stmt->execute()) throw new Exception('Database execute error: ' . $stmt->error);

    $notes = "Status changed from " . ($old_status ?? 'Initial') . " to {$status}";
    if ($status == 'Completed') $notes .= " (Completed - completion date and end time recorded)";
    elseif (in_array($status, $final_statuses)) $notes .= " (Final status - end time recorded)";

    recordStatusChange('maintenance', $ticket_id, $ticket_number, $old_status, $status, $user_id, $notes);
    logActivity($conn, 'update_status', 'maintenance', $ticket_id, 'Updated maintenance ticket #' . $ticket_id . ' status to ' . $status);

    $message = 'Status updated successfully';
    if (in_array($status, $final_statuses) && $has_end_time) $message .= ' and end time recorded';

    notify_db_change($conn);
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
exit;
