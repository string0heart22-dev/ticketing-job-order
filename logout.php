<?php
// Include session cleanup utilities
require_once 'session_cleanup.php';

// Include session initialization for proper session management
require_once 'session_init.php';

// Perform complete logout
performCompleteSessionCleanup(true, 'You have been successfully logged out.');
?>
