<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
    $plan_name = mysqli_real_escape_string($conn, $_POST['plan_name']);
    $speed = mysqli_real_escape_string($conn, $_POST['speed']);
    $monthly_fee = floatval($_POST['monthly_fee']);
    $contract_duration = intval($_POST['contract_duration']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Validate required fields
    if (empty($plan_name) || empty($speed) || $monthly_fee <= 0 || $contract_duration <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    if ($plan_id > 0) {
        // Update existing plan
        $query = "UPDATE service_plans SET 
                  plan_name = '$plan_name',
                  speed = '$speed',
                  monthly_fee = $monthly_fee,
                  contract_duration = $contract_duration,
                  description = '$description',
                  status = '$status'
                  WHERE id = $plan_id";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Service plan updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating plan: ' . mysqli_error($conn)]);
        }
    } else {
        // Insert new plan
        $query = "INSERT INTO service_plans (plan_name, speed, monthly_fee, contract_duration, description, status) 
                  VALUES ('$plan_name', '$speed', $monthly_fee, $contract_duration, '$description', '$status')";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Service plan added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding plan: ' . mysqli_error($conn)]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

mysqli_close($conn);
?>
