<?php
require_once 'auth_check.php';
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $ticket_type = $_POST['ticket_type'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (empty($name) || empty($address) || empty($contact_number) || 
        empty($ticket_type) || empty($priority) || empty($description)) {
        $_SESSION['error_message'] = 'All fields are required.';
        header('Location: tickets_installation.php');
        exit;
    }

    // Validate ticket type
    $valid_types = ['Maintenance', 'Pull Out', 'Request'];
    if (!in_array($ticket_type, $valid_types)) {
        $_SESSION['error_message'] = 'Invalid ticket type.';
        header('Location: tickets_installation.php');
        exit;
    }

    // Validate priority
    $valid_priorities = ['Less Priority', 'Normal', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        $_SESSION['error_message'] = 'Invalid priority level.';
        header('Location: tickets_installation.php');
        exit;
    }

    // Create tickets table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        contact_number VARCHAR(50) NOT NULL,
        ticket_type ENUM('Maintenance', 'Pull Out', 'Request') NOT NULL,
        priority ENUM('Less Priority', 'Normal', 'Urgent') NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        assigned_to INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(userID) ON DELETE SET NULL
    )";

    if (!$conn->query($create_table_sql)) {
        $_SESSION['error_message'] = 'Database error: Unable to create table.';
        header('Location: tickets_installation.php');
        exit;
    }

    // Insert ticket
    $sql = "INSERT INTO tickets (name, address, contact_number, ticket_type, priority, description) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error_message'] = 'Database error: ' . $conn->error;
        header('Location: tickets_installation.php');
        exit;
    }

    $stmt->bind_param('ssssss', $name, $address, $contact_number, $ticket_type, $priority, $description);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Ticket created successfully! Ticket #: TKT-' . str_pad($stmt->insert_id, 5, '0', STR_PAD_LEFT);
        header('Location: tickets_installation.php');
    } else {
        $_SESSION['error_message'] = 'Error creating ticket: ' . $stmt->error;
        header('Location: tickets_installation.php');
    }

    $stmt->close();
    $conn->close();
} else {
    header('Location: tickets_installation.php');
}
exit;
?>
