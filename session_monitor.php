<?php
/**
 * Session Monitor and Cleanup Tool
 * Use this to monitor active sessions and clean up expired ones
 */

require_once 'session_cleanup.php';
require_once 'config.php';

// Only allow access from localhost or admin users
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips)) {
    // Check if user is admin (if session exists)
    session_start();
    if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin access required.');
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .session-info { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .expired { background: #ffe6e6; }
        .active { background: #e6ffe6; }
        .button { padding: 8px 16px; margin: 5px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .danger { background: #dc3545; }
        .warning { background: #ffc107; color: black; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Session Monitor</h1>
    
    <?php
    // Handle cleanup actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cleanup_expired':
                cleanupExpiredSessions();
                echo "<div class='session-info active'>Expired sessions cleaned up successfully.</div>";
                break;
            case 'cleanup_all':
                // Clean up all sessions except current one
                $current_session_id = session_id();
                $session_save_path = session_save_path();
                if (empty($session_save_path)) {
                    $session_save_path = sys_get_temp_dir();
                }
                
                $session_files = glob($session_save_path . '/sess_*');
                $cleaned = 0;
                foreach ($session_files as $file) {
                    $file_session_id = str_replace($session_save_path . '/sess_', '', $file);
                    if ($file_session_id !== $current_session_id) {
                        if (@unlink($file)) {
                            $cleaned++;
                        }
                    }
                }
                echo "<div class='session-info active'>Cleaned up $cleaned session files.</div>";
                break;
        }
    }
    
    // Display session information
    echo "<h2>Current Session Info</h2>";
    echo "<div class='session-info active'>";
    echo "<strong>Session ID:</strong> " . session_id() . "<br>";
    echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "<br>";
    echo "<strong>Session Save Path:</strong> " . session_save_path() . "<br>";
    echo "<strong>Session Name:</strong> " . session_name() . "<br>";
    if (isset($_SESSION['userID'])) {
        echo "<strong>User ID:</strong> " . $_SESSION['userID'] . "<br>";
        echo "<strong>Last Activity:</strong> " . (isset($_SESSION['LAST_ACTIVITY']) ? date('Y-m-d H:i:s', $_SESSION['LAST_ACTIVITY']) : 'Not set') . "<br>";
        echo "<strong>Session Created:</strong> " . (isset($_SESSION['CREATED']) ? date('Y-m-d H:i:s', $_SESSION['CREATED']) : 'Not set') . "<br>";
    }
    echo "</div>";
    
    // Display session files
    echo "<h2>Session Files</h2>";
    $session_save_path = session_save_path();
    if (empty($session_save_path)) {
        $session_save_path = sys_get_temp_dir();
    }
    
    $session_files = glob($session_save_path . '/sess_*');
    $timeout_duration = 7200; // 2 hours
    $current_time = time();
    
    if (empty($session_files)) {
        echo "<div class='session-info'>No session files found.</div>";
    } else {
        echo "<table>";
        echo "<tr><th>Session ID</th><th>File Size</th><th>Last Modified</th><th>Age (minutes)</th><th>Status</th></tr>";
        
        foreach ($session_files as $file) {
            $session_id = basename($file, '.sess');
            $session_id = str_replace('sess_', '', $session_id);
            $file_size = filesize($file);
            $file_time = filemtime($file);
            $age_minutes = round(($current_time - $file_time) / 60, 1);
            $is_expired = ($current_time - $file_time) > $timeout_duration;
            
            $status_class = $is_expired ? 'expired' : 'active';
            $status_text = $is_expired ? 'Expired' : 'Active';
            
            echo "<tr class='$status_class'>";
            echo "<td>" . htmlspecialchars($session_id) . "</td>";
            echo "<td>" . $file_size . " bytes</td>";
            echo "<td>" . date('Y-m-d H:i:s', $file_time) . "</td>";
            echo "<td>" . $age_minutes . "</td>";
            echo "<td>" . $status_text . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Cleanup actions
    echo "<h2>Cleanup Actions</h2>";
    echo "<form method='post' style='display: inline;'>";
    echo "<input type='hidden' name='action' value='cleanup_expired'>";
    echo "<button type='submit' class='button warning'>Clean Up Expired Sessions</button>";
    echo "</form>";
    
    echo "<form method='post' style='display: inline;'>";
    echo "<input type='hidden' name='action' value='cleanup_all'>";
    echo "<button type='submit' class='button danger' onclick='return confirm(\"This will log out all users except you. Continue?\")'>Clean Up All Sessions</button>";
    echo "</form>";
    
    // Session configuration info
    echo "<h2>Session Configuration</h2>";
    echo "<div class='session-info'>";
    echo "<strong>session.gc_maxlifetime:</strong> " . ini_get('session.gc_maxlifetime') . " seconds<br>";
    echo "<strong>session.cookie_lifetime:</strong> " . ini_get('session.cookie_lifetime') . " seconds<br>";
    echo "<strong>session.gc_probability:</strong> " . ini_get('session.gc_probability') . "<br>";
    echo "<strong>session.gc_divisor:</strong> " . ini_get('session.gc_divisor') . "<br>";
    echo "<strong>Cleanup probability:</strong> " . (ini_get('session.gc_probability') / ini_get('session.gc_divisor') * 100) . "%<br>";
    echo "</div>";
    ?>
    
    <h2>Recommendations</h2>
    <div class="session-info">
        <ul>
            <li>Run expired session cleanup regularly (consider adding to cron job)</li>
            <li>Monitor session file growth to prevent disk space issues</li>
            <li>Ensure proper logout procedures are followed by users</li>
            <li>Consider implementing database-based session storage for better control</li>
        </ul>
    </div>
    
    <p><a href="javascript:location.reload()">Refresh</a> | <a href="Login.php">Back to Login</a></p>
</body>
</html>