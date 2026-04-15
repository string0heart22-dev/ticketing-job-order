<?php
require_once 'auth_check.php';
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purok_zone = trim($_POST['purok_zone']);
    $barangay = trim($_POST['barangay']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $zip_code = trim($_POST['zip_code']);
    
    // Validate required fields
    if (empty($purok_zone) || empty($barangay) || empty($city) || empty($province) || empty($zip_code)) {
        $_SESSION['error_message'] = 'All fields are required.';
        header('Location: service_areas.php');
        exit;
    }
    
    // Insert service area
    $sql = "INSERT INTO service_areas (purok_zone, barangay, city, province, zip_code) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $purok_zone, $barangay, $city, $province, $zip_code);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Service area added successfully!';
    } else {
        $_SESSION['error_message'] = 'Error adding service area: ' . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
    
    header('Location: service_areas.php');
    exit;
}
?>
