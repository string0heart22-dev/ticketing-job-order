<?php
/**
 * Activity Logger
 * Logs user actions to the activity_logs table.
 * Include this file wherever you need to log an action.
 */

require_once 'config.php';

/**
 * Creates the activity_logs table if it doesn't exist.
 */
function ensureLogsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT          DEFAULT NULL,
        user_name   VARCHAR(255) DEFAULT 'Unknown',
        user_role   VARCHAR(50)  DEFAULT 'unknown',
        action      VARCHAR(100) NOT NULL,
        target_type VARCHAR(100) DEFAULT NULL,
        target_id   INT          DEFAULT NULL,
        details     TEXT         DEFAULT NULL,
        ip_address  VARCHAR(45)  DEFAULT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

/**
 * Log a user activity.
 *
 * @param mysqli $conn       Active DB connection
 * @param string $action     e.g. 'delete_ticket', 'assign_ticket', 'claim_ticket', 'create_ticket', 'clear_noc'
 * @param string $targetType e.g. 'installation', 'maintenance', 'pullout', 'user', 'service_area', 'service_plan'
 * @param int    $targetId   The ID of the affected record
 * @param string $details    Human-readable description of what happened
 */
function logActivity($conn, $action, $targetType = null, $targetId = null, $details = null) {
    ensureLogsTable($conn);

    // Resolve session — works whether session is open or write-closed
    $userId   = $_SESSION['userID']   ?? null;
    $userName = $_SESSION['name']     ?? ($_SESSION['username'] ?? 'Unknown');
    $userRole = $_SESSION['role']     ?? 'unknown';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, user_name, user_role, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;

    $stmt->bind_param('issssiis',
        $userId, $userName, $userRole,
        $action, $targetType, $targetId,
        $details, $ip
    );
    $stmt->execute();
    $stmt->close();
}
