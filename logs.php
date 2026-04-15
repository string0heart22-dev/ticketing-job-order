<?php
require_once 'session_init.php';
require_once 'role_check.php';
requireAdminRole();
require_once 'config.php';
require_once 'log_activity.php';
ensureLogsTable($conn);

// Handle clear all logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_logs'])) {
    $password = $_POST['password'] ?? '';
    
    if ($password === 'admin12345') {
        // Get current user info before clearing
        $user_id = $_SESSION['userID'] ?? null;
        $user_name = $_SESSION['name'] ?? 'Unknown';
        $user_role = $_SESSION['role'] ?? 'unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $action = 'clear_logs';
        $target_type = 'system';
        $details = "Cleared all activity logs";
        
        // Delete all existing logs instead of truncate
        $delete_sql = "DELETE FROM activity_logs";
        $conn->query($delete_sql);
        
        // Insert the clear action log
        $insert_sql = "INSERT INTO activity_logs (user_id, user_name, user_role, action, target_type, details, ip_address, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('issssss', $user_id, $user_name, $user_role, $action, $target_type, $details, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
        
        // Store success message in session and redirect
        $_SESSION['success_message'] = "All logs have been cleared successfully!";
        header("Location: logs.php");
        exit();
    } else {
        $error_message = "Incorrect password. Access denied.";
    }
}

// Get success message from session if exists
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) {
    unset($_SESSION['success_message']); // Clear after displaying
}

// Filters
$filter_user   = intval($_GET['user_id'] ?? 0);
$filter_action = trim($_GET['action'] ?? '');
$filter_date   = trim($_GET['date'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 50;
$offset        = ($page - 1) * $per_page;

// Build WHERE clause
$where  = [];
$params = [];
$types  = '';

if ($filter_user > 0) {
    $where[]  = 'user_id = ?';
    $params[] = $filter_user;
    $types   .= 'i';
}
if ($filter_action !== '') {
    $where[]  = 'action = ?';
    $params[] = $filter_action;
    $types   .= 's';
}
if ($filter_date !== '') {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filter_date;
    $types   .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$count_sql  = "SELECT COUNT(*) as total FROM activity_logs $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_rows / $per_page));
$count_stmt->close();

// Fetch logs
$logs_sql  = "SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$logs_stmt = $conn->prepare($logs_sql);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . 'ii';
$logs_stmt->bind_param($all_types, ...$all_params);
$logs_stmt->execute();
$logs = $logs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logs_stmt->close();

// Fetch users for filter dropdown
$users = $conn->query("SELECT userID, name FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch distinct actions for filter dropdown
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetch_all(MYSQLI_ASSOC);

// Action label map
$action_labels = [
    'create_ticket'       => '➕ Created Ticket',
    'delete_ticket'       => '🗑️ Deleted Ticket',
    'bulk_delete_tickets' => '🗑️ Bulk Deleted Tickets',
    'assign_ticket'       => '👤 Assigned Ticket',
    'claim_ticket'        => '✋ Claimed Ticket',
    'update_status'       => '🔄 Updated Status',
    'clear_noc'           => '✅ NOC Cleared',
    'clear_cs'            => '✅ CS Cleared',
    'delete_user'         => '🗑️ Deleted User',
    'bulk_delete_users'   => '🗑️ Bulk Deleted Users',
    'delete_service_area' => '🗑️ Deleted Service Area',
    'delete_service_plan' => '🗑️ Deleted Service Plan',
    'clear_logs'          => '🗑️ Cleared All Logs',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />    <link rel="stylesheet" href="tickets.css?v=12" />
    <style>
        body, html { 
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
            min-height: 100vh;
        }
        body.logs-page {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        }
        .logs-container { 
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .logs-header h1 { 
            color: #f1f5f9;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        .logs-header h1::before {
            content: "📋";
            font-size: 32px;
        }
        .logs-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .logs-filters { 
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            align-items: flex-end;
        }
        .logs-filters .filter-group { 
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 150px;
        }
        .logs-filters label { 
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .logs-filters select,
        .logs-filters input[type="date"],
        .logs-filters input[type="password"] {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
            font-size: 14px;
            transition: all 0.2s;
        }
        .logs-filters select:focus,
        .logs-filters input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .logs-filters select option { 
            background: #1e293b;
            color: #f1f5f9;
        }



        .btn-filter, .btn-reset, .btn-clear {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-filter {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        .btn-filter:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-reset {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .btn-reset:hover {
            background: rgba(148, 163, 184, 0.2);
            color: #f1f5f9;
        }
        .btn-clear {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-clear:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .logs-table-wrap { 
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
        }
        .logs-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px;
        }
        .logs-table th { 
            background: rgba(15, 23, 42, 0.8);
            color: #94a3b8;
            padding: 14px 16px;
            text-align: left; 
            border-bottom: 2px solid rgba(148, 163, 184, 0.1);
            white-space: nowrap;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
        }
        .logs-table td { 
            padding: 12px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.05);
            color: #e2e8f0;
            vertical-align: top;
            background: transparent;
        }
        .logs-table tr:hover td { 
            background: rgba(59, 130, 246, 0.05);
        }
        .badge-role { 
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-admin    { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .badge-employee { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .badge-user     { 
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.2));
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .action-label { 
            font-weight: 600;
            color: #f1f5f9;
        }
        .details-cell { 
            max-width: 320px;
            word-break: break-word;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }
        .pagination { 
            display: flex;
            gap: 8px;
            margin-top: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            background: rgba(30, 41, 59, 0.8);
            color: #94a3b8;
            text-decoration: none;
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: all 0.2s;
        }
        .pagination a:hover { 
            background: rgba(59, 130, 246, 0.2);
            color: #f1f5f9;
            border-color: rgba(59, 130, 246, 0.3);
        }
        .pagination .active { 
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-color: transparent;
        }
        .logs-summary { 
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: rgba(30, 41, 59, 0.4);
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .empty-state { 
            text-align: center;
            padding: 80px 20px;
            color: #64748b;
            background: rgba(30, 41, 59, 0.4);
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .empty-state::before {
            content: "📭";
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1));
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            margin: 10% auto;
            padding: 32px;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .modal-header {
            margin-bottom: 24px;
        }
        .modal-header h3 {
            color: #f1f5f9;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        .modal-header p {
            color: #94a3b8;
            margin: 8px 0 0 0;
            font-size: 14px;
        }
        .modal-body {
            margin-bottom: 24px;
        }
        .modal-body label {
            display: block;
            margin-bottom: 8px;
            color: #e2e8f0;
            font-weight: 500;
        }
        .modal-body input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.6);
            color: #f1f5f9;
            font-size: 14px;
            box-sizing: border-box;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn-cancel {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .btn-cancel:hover {
            background: rgba(148, 163, 184, 0.2);
            color: #f1f5f9;
        }
        .btn-confirm {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-confirm:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        @media (max-width: 768px) {
            .logs-container { padding: 16px; }
            .logs-header { 
                flex-direction: column; 
                align-items: stretch; 
                gap: 12px;
            }
            .logs-header h1 {
                font-size: 24px;
                text-align: center;
            }
            .logs-header-actions {
                justify-content: center;
            }
            .logs-filters { 
                padding: 16px; 
                gap: 12px;
            }
            .logs-filters .filter-group { 
                min-width: 100%; 
            }
            .btn-filter, .btn-reset {
                flex: 1;
                justify-content: center;
            }
            .modal-content { 
                margin: 20% auto; 
                padding: 24px; 
                width: 95%;
            }
        }
    </style>
</head>
<body class="logs-page">
<header class="topbar" role="banner">
    <div class="sidebar-toggle-wrapper">
        <button id="sidebarToggle" class="btn" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle sidebar">☰</button>
    </div>
    <div class="logo-container">
        <img src="logo-09022026.png" alt="Ubilink Logo" class="logo">
    </div>
    <div class="controls">
        <?php include 'presence_menu.php'; ?>
        <button class="btn-night" id="theme-toggle"></button>
        <div class="user-profile">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?></span>
                <span class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
            </div>
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'G', 0, 1)) ?></div>
        </div>
    </div>
</header>

<?php include 'sidebar.php'; ?>

<main id="mainContent" class="main">
    <div class="logs-container">
        <div class="logs-header">
            <h1>Activity Logs</h1>
            <div class="logs-header-actions">
                <button class="btn-clear" onclick="showClearModal()">
                    🗑️ Clear All Logs
                </button>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="logs.php" class="logs-filters">
            <div class="filter-group">
                <label for="f_user">User</label>
                <select id="f_user" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['userID'] ?>" <?= $filter_user == $u['userID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="f_action">Action</label>
                <select id="f_action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filter_action === $a['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($action_labels[$a['action']] ?? $a['action']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="f_date">Date</label>
                <input type="date" id="f_date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <button type="submit" class="btn-filter">🔍 Filter</button>
            <a href="logs.php" class="btn-reset">↺ Reset</a>
        </form>

        <p class="logs-summary">
            📊 Showing <?= number_format(min($offset + 1, $total_rows)) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?>
            of <?= number_format($total_rows) ?> log entries
        </p>

        <?php if (empty($logs)): ?>
            <div class="empty-state">No activity logs found matching your criteria.</div>
        <?php else: ?>
        <div class="logs-table-wrap">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td style="white-space:nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                        <td><?= htmlspecialchars($log['user_name']) ?></td>
                        <td>
                            <?php $role = strtolower($log['user_role']); ?>
                            <span class="badge-role badge-<?= $role ?>">
                                <?= htmlspecialchars(ucfirst($log['user_role'])) ?>
                            </span>
                        </td>
                        <td class="action-label">
                            <?= htmlspecialchars($action_labels[$log['action']] ?? $log['action']) ?>
                        </td>
                        <td>
                            <?php if ($log['target_type']): ?>
                                <?= htmlspecialchars(ucfirst($log['target_type'])) ?>
                                <?= $log['target_id'] ? ' #' . $log['target_id'] : '' ?>
                            <?php endif; ?>
                        </td>
                        <td class="details-cell"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        <td style="white-space:nowrap;color:#64748b"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $base_url = 'logs.php?' . http_build_query(array_filter([
                'user_id' => $filter_user ?: null,
                'action'  => $filter_action ?: null,
                'date'    => $filter_date ?: null,
            ]));
            $sep = strpos($base_url, '?') !== false && substr($base_url, -1) !== '?' ? '&' : '?';
            for ($p = 1; $p <= $total_pages; $p++):
                $url = $base_url . ($base_url !== 'logs.php?' ? '&' : '') . 'page=' . $p;
            ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Clear Logs Modal -->
<div id="clearModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚠️ Clear All Logs</h3>
            <p>This action cannot be undone. All activity logs will be permanently deleted.</p>
        </div>
        <form method="POST" action="logs.php">
            <div class="modal-body">
                <label style="color: black;" for="password">Enter admin password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="hideClearModal()">Cancel</button>
                <button type="submit" name="clear_all_logs" class="btn-confirm">Clear All Logs</button>
            </div>
        </form>
    </div>
</div>

<script>
function showClearModal() {
    document.getElementById('clearModal').style.display = 'block';
}

function hideClearModal() {
    document.getElementById('clearModal').style.display = 'none';
    document.getElementById('password').value = '';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('clearModal');
    if (event.target == modal) {
        hideClearModal();
    }
}
</script>

<script src="JavaScript.js?v=11"></script>
<script src="darkmode.js"></script>
</body>
</html>
