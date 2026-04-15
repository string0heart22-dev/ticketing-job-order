<?php
// Setup script to add missing columns for NOC/CS clear functionality
require_once 'config.php';

header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$results = [];
$errors = [];

// Columns to add for NOC/CS clear functionality
$columns = [
    ['name' => 'noc_cleared_at', 'type' => 'TIMESTAMP NULL DEFAULT NULL'],
    ['name' => 'noc_cleared_by', 'type' => 'INT(11) DEFAULT NULL'],
    ['name' => 'cs_cleared_at', 'type' => 'TIMESTAMP NULL DEFAULT NULL'],
    ['name' => 'cs_cleared_by', 'type' => 'INT(11) DEFAULT NULL'],
    ['name' => 'nap_optical_reading', 'type' => 'VARCHAR(50) DEFAULT NULL'],
    ['name' => 'client_optical_reading', 'type' => 'VARCHAR(50) DEFAULT NULL'],
    ['name' => 'speed_test_mbps', 'type' => 'VARCHAR(50) DEFAULT NULL'],
];

foreach ($columns as $col) {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM installations LIKE '{$col['name']}'");
    if ($check && $check->num_rows > 0) {
        $results[] = "Column {$col['name']} already exists";
    } else {
        // Add column
        $sql = "ALTER TABLE installations ADD COLUMN {$col['name']} {$col['type']}";
        if ($conn->query($sql)) {
            $results[] = "Added column {$col['name']}";
        } else {
            $errors[] = "Failed to add {$col['name']}: " . $conn->error;
        }
    }
}

// Add indexes
$indexes = [
    ['name' => 'idx_noc_cleared_by', 'column' => 'noc_cleared_by'],
    ['name' => 'idx_cs_cleared_by', 'column' => 'cs_cleared_by'],
];

foreach ($indexes as $idx) {
    // Check if index exists
    $check = $conn->query("SHOW INDEX FROM installations WHERE Key_name = '{$idx['name']}'");
    if ($check && $check->num_rows > 0) {
        $results[] = "Index {$idx['name']} already exists";
    } else {
        // Add index
        $sql = "ALTER TABLE installations ADD INDEX {$idx['name']} ({$idx['column']})";
        if ($conn->query($sql)) {
            $results[] = "Added index {$idx['name']}";
        } else {
            $errors[] = "Failed to add index {$idx['name']}: " . $conn->error;
        }
    }
}

// Create installation_images table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS installation_images (
  id INT(11) NOT NULL AUTO_INCREMENT,
  installation_id INT(11) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  image_name VARCHAR(255) NOT NULL,
  image_size INT(11) NOT NULL,
  image_type VARCHAR(50) NOT NULL,
  description TEXT DEFAULT NULL,
  uploaded_by INT(11) DEFAULT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_installation_id (installation_id),
  KEY idx_uploaded_by (uploaded_by),
  KEY idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($create_table_sql)) {
    $results[] = "Table installation_images ready";
} else {
    $errors[] = "Failed to create installation_images table: " . $conn->error;
}

$conn->close();

if (empty($errors)) {
    echo json_encode(['success' => true, 'message' => 'All database columns are ready', 'details' => $results]);
} else {
    echo json_encode(['success' => false, 'message' => 'Some operations failed', 'results' => $results, 'errors' => $errors]);
}
?>
