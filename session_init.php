<?php
/**
 * Centralized Session Initialization
 * Include this file at the top of every page instead of calling session_start() directly
 * This ensures consistent session configuration across all pages
 */

// Include session cleanup utilities
require_once 'session_cleanup.php';

// Prevent multiple session starts
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings BEFORE starting session
    
    // Disable automatic garbage collection to prevent session deletion
    ini_set('session.gc_probability', 0);
    
    // Set a very long session lifetime (24 hours) instead of 0
    ini_set('session.gc_maxlifetime', 86400);
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,               // Session cookie (expires when browser closes)
        'path' => '/',                 // Available across entire site
        'domain' => '',                // Current domain
        'secure' => false,             // Set to true if using HTTPS
        'httponly' => true,            // Prevent JavaScript access to session cookie
        'samesite' => 'Lax'           // CSRF protection
    ]);
    
    // Start the session
    session_start();
    
    // Set creation time if not set
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    }
}

// Update last activity timestamp (but don't check for timeout)
if (isset($_SESSION['userID'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Performs complete session cleanup including cookies
 * @deprecated Use performCompleteSessionCleanup() from session_cleanup.php instead
 */
function performCompleteLogout() {
    performCompleteSessionCleanup();
}
?>
