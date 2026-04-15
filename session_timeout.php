<?php
// Session timeout checker - 2 hours
if (!isset($_SESSION)) {
    // Set cookie params before starting session
    session_set_cookie_params(7200); // 2 hours
    session_start();
}

// Set timeout duration (2 hours = 7200 seconds)
$timeout_duration = 7200;

// Check if the session has a last activity timestamp
if (isset($_SESSION['LAST_ACTIVITY'])) {
    // Calculate how long it's been since last activity
    $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
    
    // If more than 2 hours have passed, destroy session and redirect to login
    if ($elapsed_time > $timeout_duration) {
        // Session expired
        session_unset();
        session_destroy();
        
        // Redirect to login with timeout message
        header("Location: Login.php?timeout=1");
        exit();
    }
}

// Update last activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();
?>
