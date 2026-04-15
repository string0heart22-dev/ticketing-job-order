<?php
/**
 * Status History Tracking Functions
 * Functions to record status changes in the history tables
 */

/**
 * Record a status change in the appropriate history table
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @param int $ticket_id - The ID of the ticket
 * @param string $ticket_number - The ticket number
 * @param string $old_status - Previous status (null for initial)
 * @param string $new_status - New status
 * @param int $changed_by - User ID who made the change
 * @param string $notes - Optional notes about the change
 * @return bool - Success/failure
 */
function recordStatusChange($ticket_type, $ticket_id, $ticket_number, $old_status, $new_status, $changed_by = null, $notes = null) {
    global $conn;
    
    // Determine the correct table
    $table_map = [
        'installation' => 'installation_status_history',
        'maintenance' => 'maintenance_status_history',
        'pullout' => 'pullout_status_history'
    ];
    
    $id_field_map = [
        'installation' => 'installation_id',
        'maintenance' => 'maintenance_id',
        'pullout' => 'pullout_id'
    ];
    
    if (!isset($table_map[$ticket_type])) {
        error_log("Invalid ticket type: {$ticket_type}");
        return false;
    }
    
    $table = $table_map[$ticket_type];
    $id_field = $id_field_map[$ticket_type];
    
    // Check if table exists, create if needed
    $check_table = $conn->query("SHOW TABLES LIKE '{$table}'");
    if (!$check_table || $check_table->num_rows == 0) {
        // Table doesn't exist, create it
        createStatusHistoryTable($ticket_type);
    }
    
    // Calculate duration if there was a previous status
    $duration_seconds = null;
    if ($old_status) {
        $duration_sql = "SELECT TIMESTAMPDIFF(SECOND, changed_at, NOW()) as duration 
                        FROM {$table} 
                        WHERE {$id_field} = ? 
                        ORDER BY changed_at DESC 
                        LIMIT 1";
        $duration_stmt = $conn->prepare($duration_sql);
        if ($duration_stmt) {
            $duration_stmt->bind_param('i', $ticket_id);
            $duration_stmt->execute();
            $duration_result = $duration_stmt->get_result();
            if ($duration_row = $duration_result->fetch_assoc()) {
                $duration_seconds = $duration_row['duration'];
            }
            $duration_stmt->close();
        }
    }
    
    // Insert the status change record
    $sql = "INSERT INTO {$table} ({$id_field}, ticket_number, old_status, new_status, changed_by, notes, duration_seconds) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare status history insert: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('isssisi', $ticket_id, $ticket_number, $old_status, $new_status, $changed_by, $notes, $duration_seconds);
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to insert status history: " . $stmt->error);
    }
    
    $stmt->close();
    return $success;
}

/**
 * Create a status history table for the given ticket type
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @return bool - Success/failure
 */
function createStatusHistoryTable($ticket_type) {
    global $conn;
    
    $table_definitions = [
        'installation' => [
            'table' => 'installation_status_history',
            'id_field' => 'installation_id',
            'foreign_table' => 'installations'
        ],
        'maintenance' => [
            'table' => 'maintenance_status_history',
            'id_field' => 'maintenance_id',
            'foreign_table' => 'maintenances'
        ],
        'pullout' => [
            'table' => 'pullout_status_history',
            'id_field' => 'pullout_id',
            'foreign_table' => 'pullout_tickets'
        ]
    ];
    
    if (!isset($table_definitions[$ticket_type])) {
        return false;
    }
    
    $def = $table_definitions[$ticket_type];
    
    $sql = "CREATE TABLE IF NOT EXISTS `{$def['table']}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `{$def['id_field']}` INT(11) NOT NULL,
        `ticket_number` VARCHAR(50) NOT NULL,
        `old_status` VARCHAR(50) DEFAULT NULL,
        `new_status` VARCHAR(50) NOT NULL,
        `changed_by` INT(10) UNSIGNED DEFAULT NULL,
        `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `notes` TEXT DEFAULT NULL,
        `duration_seconds` INT DEFAULT NULL,
        INDEX `idx_{$def['id_field']}` (`{$def['id_field']}`),
        INDEX `idx_ticket_number` (`ticket_number`),
        INDEX `idx_new_status` (`new_status`),
        INDEX `idx_changed_at` (`changed_at`),
        FOREIGN KEY (`{$def['id_field']}`) REFERENCES `{$def['foreign_table']}`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`userID`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return $conn->query($sql);
}

/**
 * Get the current status of a ticket
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @param int $ticket_id - The ID of the ticket
 * @return string|null - Current status or null if not found
 */
function getCurrentStatus($ticket_type, $ticket_id) {
    global $conn;
    
    $table_map = [
        'installation' => 'installations',
        'maintenance' => 'maintenances',
        'pullout' => 'pullout_tickets'
    ];
    
    if (!isset($table_map[$ticket_type])) {
        return null;
    }
    
    $table = $table_map[$ticket_type];
    $sql = "SELECT status FROM {$table} WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status = null;
    if ($row = $result->fetch_assoc()) {
        $status = $row['status'];
    }
    
    $stmt->close();
    return $status;
}

/**
 * Get the ticket number for a given ticket ID
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @param int $ticket_id - The ID of the ticket
 * @return string|null - Ticket number or null if not found
 */
function getTicketNumber($ticket_type, $ticket_id) {
    global $conn;
    
    $table_map = [
        'installation' => 'installations',
        'maintenance' => 'maintenances',
        'pullout' => 'pullout_tickets'
    ];
    
    if (!isset($table_map[$ticket_type])) {
        return null;
    }
    
    $table = $table_map[$ticket_type];
    $sql = "SELECT ticket_number FROM {$table} WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ticket_number = null;
    if ($row = $result->fetch_assoc()) {
        $ticket_number = $row['ticket_number'];
    }
    
    $stmt->close();
    return $ticket_number;
}

/**
 * Get status change history for a ticket
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @param int $ticket_id - The ID of the ticket
 * @return array - Array of status change records
 */
function getStatusHistory($ticket_type, $ticket_id) {
    global $conn;
    
    $table_map = [
        'installation' => 'installation_status_history',
        'maintenance' => 'maintenance_status_history',
        'pullout' => 'pullout_status_history'
    ];
    
    $id_field_map = [
        'installation' => 'installation_id',
        'maintenance' => 'maintenance_id',
        'pullout' => 'pullout_id'
    ];
    
    if (!isset($table_map[$ticket_type])) {
        return [];
    }
    
    $table = $table_map[$ticket_type];
    $id_field = $id_field_map[$ticket_type];
    
    $sql = "SELECT 
                h.*,
                u.name as changed_by_name
            FROM {$table} h
            LEFT JOIN users u ON h.changed_by = u.userID
            WHERE h.{$id_field} = ?
            ORDER BY h.changed_at ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    return $history;
}

/**
 * Get work duration metrics for a ticket
 * 
 * @param string $ticket_type - 'installation', 'maintenance', or 'pullout'
 * @param int $ticket_id - The ID of the ticket
 * @return array - Metrics including in_progress_time, final_time, duration_minutes
 */
function getWorkDurationMetrics($ticket_type, $ticket_id) {
    global $conn;
    
    $table_map = [
        'installation' => 'installation_status_history',
        'maintenance' => 'maintenance_status_history',
        'pullout' => 'pullout_status_history'
    ];
    
    $id_field_map = [
        'installation' => 'installation_id',
        'maintenance' => 'maintenance_id',
        'pullout' => 'pullout_id'
    ];
    
    $final_statuses_map = [
        'installation' => ['Installed', 'Cancelled', 'Negative'],
        'maintenance' => ['Completed', 'Cancelled', 'On Hold', 'Closed'],
        'pullout' => ['Closed', 'Negative', 'Reconnected']
    ];
    
    if (!isset($table_map[$ticket_type])) {
        return null;
    }
    
    $table = $table_map[$ticket_type];
    $id_field = $id_field_map[$ticket_type];
    $final_statuses = $final_statuses_map[$ticket_type];
    $final_statuses_str = "'" . implode("', '", $final_statuses) . "'";
    
    $sql = "SELECT 
                (SELECT changed_at 
                 FROM {$table} 
                 WHERE {$id_field} = ? AND new_status = 'In Progress' 
                 ORDER BY changed_at ASC LIMIT 1) as in_progress_time,
                
                (SELECT changed_at 
                 FROM {$table} 
                 WHERE {$id_field} = ? AND new_status IN ({$final_statuses_str}) 
                 ORDER BY changed_at DESC LIMIT 1) as final_time";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('ii', $ticket_id, $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $metrics = null;
    if ($row = $result->fetch_assoc()) {
        $metrics = $row;
        
        // Calculate duration in minutes
        if ($row['in_progress_time'] && $row['final_time']) {
            $in_progress = new DateTime($row['in_progress_time']);
            $final = new DateTime($row['final_time']);
            $interval = $in_progress->diff($final);
            $metrics['duration_minutes'] = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        } else {
            $metrics['duration_minutes'] = null;
        }
    }
    
    $stmt->close();
    return $metrics;
}

/**
 * Helper function to get user ID from session
 * 
 * @return int|null - User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['userID'] ?? null;
}
?>