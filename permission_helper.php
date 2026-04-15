<?php
// Permission helper - include this at the top of pages after session_init.php
// It sets data attributes on the body tag for permission checking

function getCurrentPagePermissionKey() {
    $path = $_SERVER['PHP_SELF'];
    $filename = basename($path, '.php');
    
    $pageMap = [
        'tickets_installation' => 'tickets_installation',
        'tickets' => 'tickets',
        'tickets_maintenance' => 'tickets_maintenance',
        'tickets_pullout' => 'tickets_pullout',
        'service_areas' => 'service_areas',
        'service_plans' => 'service_plans',
        'inventory' => 'inventory',
        'reports' => 'reports',
        'USERs' => 'users',
        'installation_form' => 'installation_form',
        'olt' => 'olt',
        'customer_database' => 'customer_database'
    ];
    
    return $pageMap[$filename] ?? $filename;
}

function getPagePermissions() {
    global $conn;
    $page = getCurrentPagePermissionKey();

    // Check permissions from database for current user
    if (isset($_SESSION['userID']) && isset($conn)) {
        $userID = $_SESSION['userID'];
        $accessCol = "can_$page";
        $deleteCol = "can_delete_$page";

        $sql = "SELECT $accessCol, $deleteCol FROM users WHERE userID = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $canAccess = $row[$accessCol] ?? 1;
                $canDelete = $row[$deleteCol] ?? 1;
                $stmt->close();

                // Update session with fresh permissions
                $_SESSION[$accessCol] = $canAccess;
                $_SESSION[$deleteCol] = $canDelete;

                return [
                    'can_access' => $canAccess,
                    'can_delete' => $canDelete
                ];
            }
            $stmt->close();
        }
    }
    
    // Fallback to session if database check fails
    $canAccess = $_SESSION["can_$page"] ?? 1;
    $canDelete = $_SESSION["can_delete_$page"] ?? 1;
    
    return [
        'can_access' => $canAccess,
        'can_delete' => $canDelete
    ];
}

function getPermissionBodyAttributes() {
    $perms = getPagePermissions();
    return "data-can-access='{$perms['can_access']}' data-can-delete='{$perms['can_delete']}' data-page='" . getCurrentPagePermissionKey() . "'";
}

function checkPageAccess() {
    $perms = getPagePermissions();
    if ($perms['can_access'] != 1) {
        // User doesn't have access permission
        header('HTTP/1.1 403 Forbidden');
        echo '<!DOCTYPE html>
<html>
<head><title>Access Denied</title></head>
<body style="font-family:sans-serif;text-align:center;padding:50px;">
    <h1 style="color:#c0392b;">Access Denied</h1>
    <p>You do not have permission to access this page.</p>
    <p><a href="htmlpage.php">Go to Dashboard</a></p>
</body>
</html>';
        exit;
    }
}
?>
