<?php
/**
 * Session Cleanup Utility
 * Provides comprehensive session management and cleanup functions
 */

/**
 * Performs complete session cleanup including cookies and server-side data
 * @param bool $redirect_to_login Whether to redirect to login page after cleanup
 * @param string $message Message to display on login page
 */
function performCompleteSessionCleanup($redirect_to_login = false, $message = '') {
    // Clear all session data
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/', '', false, true);
    }
    
    // Clear any other authentication cookies
    $auth_cookies = ['remember_me', 'user_token', 'auth_token', 'stay_logged_in'];
    foreach ($auth_cookies as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 3600, '/', '', false, true);
        }
    }
    
    // Clear any cached session files (if using file-based sessions)
    clearSessionFiles();
    
    if ($redirect_to_login) {
        // Start new session for message
        session_start();
        if ($message) {
            $_SESSION['cleanup_message'] = $message;
        }
        
        header("Location: Login.php?cleanup=1");
        exit();
    }
}

/**
 * Clear session files from the server (if using file-based sessions)
 */
function clearSessionFiles() {
    $session_save_path = session_save_path();
    if (empty($session_save_path)) {
        $session_save_path = sys_get_temp_dir();
    }
    
    $session_id = session_id();
    if ($session_id) {
        $session_file = $session_save_path . '/sess_' . $session_id;
        if (file_exists($session_file)) {
            @unlink($session_file);
        }
    }
}

/**
 * Check if session is valid and not expired
 * @return bool True if session is valid, false otherwise
 */
function isSessionValid() {
    if (!isset($_SESSION['userID'])) {
        return false;
    }
    
    // No timeout check - sessions never expire due to inactivity
    return true;
}

/**
 * Force logout all sessions for a specific user
 * @param int $user_id User ID to logout
 */
function forceLogoutUser($user_id) {
    // This would require a database table to track active sessions
    // For now, we'll just clear the current session if it matches
    if (isset($_SESSION['userID']) && $_SESSION['userID'] == $user_id) {
        performCompleteSessionCleanup(true, 'Your session has been terminated by an administrator.');
    }
}

/**
 * Regenerate session ID for security
 */
function regenerateSessionId() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

/**
 * Clean up expired sessions (can be called by cron job)
 */
function cleanupExpiredSessions() {
    $session_save_path = session_save_path();
    if (empty($session_save_path)) {
        $session_save_path = sys_get_temp_dir();
    }
    
    $timeout_duration = 86400 * 30; // 30 days (very long timeout - only clean up very old files)
    $current_time = time();
    
    $session_files = glob($session_save_path . '/sess_*');
    foreach ($session_files as $file) {
        if (file_exists($file)) {
            $file_time = filemtime($file);
            if (($current_time - $file_time) > $timeout_duration) {
                @unlink($file);
            }
        }
    }
}
?>