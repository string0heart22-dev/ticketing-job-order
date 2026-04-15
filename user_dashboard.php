<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'config.php';
require_once 'role_check.php';

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    header('Location: Login.php');
    exit;
}

// Check if user is restricted user (User or Employee)
if (!isRestrictedUser()) {
    header('Location: htmlpage.php');
    exit;
}

$user_id = $_SESSION['userID'];
$user_name = $_SESSION['name'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? 'User';

// Fetch tickets assigned to this user
$tickets = [];

// Fetch installation tickets
$inst_sql = "SELECT 
    id,
    ticket_number,
    client_name,
    address,
    contact_number,
    service_type,
    connection_type,
    plan,
    priority,
    status,
    created_at,
    'Installation' as ticket_type
    FROM installations 
    WHERE assigned_to = ?
    ORDER BY 
        CASE priority
            WHEN 'Urgent' THEN 1
            WHEN 'Normal' THEN 2
            WHEN 'Less Priority' THEN 3
        END,
        created_at DESC";

$stmt = $conn->prepare($inst_sql);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Fetch maintenance tickets
$maint_sql = "SELECT 
    id,
    ticket_number,
    client_name,
    address,
    contact_number,
    issue_type as service_type,
    '' as connection_type,
    '' as plan,
    priority,
    status,
    created_at,
    'Maintenance' as ticket_type
    FROM maintenances 
    WHERE assigned_to = ?
    ORDER BY 
        CASE priority
            WHEN 'Urgent' THEN 1
            WHEN 'Normal' THEN 2
            WHEN 'Less Priority' THEN 3
        END,
        created_at DESC";

$stmt = $conn->prepare($maint_sql);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Sort all tickets by priority and date
usort($tickets, function($a, $b) {
    $priority_order = ['Urgent' => 1, 'Normal' => 2, 'Less Priority' => 3];
    $a_priority = $priority_order[$a['priority']] ?? 2;
    $b_priority = $priority_order[$b['priority']] ?? 2;
    
    if ($a_priority != $b_priority) {
        return $a_priority - $b_priority;
    }
    
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />
    <link rel="stylesheet" href="tickets.css" />
    
    
    
    
</head>
<body>
    <header class="topbar" role="banner">
        <div class="sidebar-toggle-wrapper">
            <button id="sidebarToggle" class="btn" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle sidebar">☰</button>
        </div>
        <div class="logo-container">
            <span style="color:#fff;font-weight:700;font-size:0.95rem;letter-spacing:0.04em;white-space:nowrap;">UBILINK COMMUNICATION CORPORATION</span>
        </div>
        <div class="controls">
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                    <span class="user-role"><?= htmlspecialchars($user_role) ?></span>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
        </div>
    </header>

    <aside id="sidebar" class="sidebar collapsed" role="navigation" aria-label="Primary sidebar" aria-hidden="true">
        <nav>
            <ul>
                <li><a href="user_dashboard.php" class="active">🏠 My Tickets</a></li>
                <li class="has-submenu tickets-menu open" aria-haspopup="true">
                    <a href="#" class="submenu-toggle" aria-expanded="true">
                        <span>📋 All Tickets</span>
                        <span class="arrow">▼</span>
                    </a>
                    <ul class="submenu" aria-label="Tickets submenu">
                        <li><a href="tickets_installation.php">Installation</a></li>
                        <li><a href="tickets_maintenance.php">Maintenance</a></li>
                    </ul>
                </li>
                <li><a href="installation_form.php">📝 Installation Form</a></li>
                <li><a href="inv/overview.php">📦 Inventory</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </nav>
    </aside>

    <div id="sidebarOverlay" class="sidebar-overlay" hidden></div>

    <main id="mainContent" class="main">
        <h1>My Assigned Tickets</h1>

        <div class="stats-summary" style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
            <div class="stat-card" style="flex: 1; min-width: 200px; background: #fef3c7; padding: 15px; border-radius: 8px;">
                <h3 style="margin: 0 0 5px 0; font-size: 0.9rem; color: #92400e;">Pending</h3>
                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #92400e;">
                    <?= count(array_filter($tickets, fn($t) => $t['status'] === 'Pending')) ?>
                </p>
            </div>
            <div class="stat-card" style="flex: 1; min-width: 200px; background: #dbeafe; padding: 15px; border-radius: 8px;">
                <h3 style="margin: 0 0 5px 0; font-size: 0.9rem; color: #1e40af;">In Progress</h3>
                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #1e40af;">
                    <?= count(array_filter($tickets, fn($t) => $t['status'] === 'In Progress')) ?>
                </p>
            </div>
            <div class="stat-card" style="flex: 1; min-width: 200px; background: #d1fae5; padding: 15px; border-radius: 8px;">
                <h3 style="margin: 0 0 5px 0; font-size: 0.9rem; color: #065f46;">Completed</h3>
                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #065f46;">
                    <?= count(array_filter($tickets, fn($t) => in_array($t['status'], ['Installed', 'Completed', 'Closed']))) ?>
                </p>
            </div>
        </div>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Type</th>
                        <th>Client Name</th>
                        <th>Address</th>
                        <th>Contact</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $ticket): 
                            $priority_class = '';
                            if ($ticket['priority'] == 'Urgent') $priority_class = 'priority-urgent';
                            elseif ($ticket['priority'] == 'Normal') $priority_class = 'priority-normal';
                            else $priority_class = 'priority-low';
                            
                            $type_class = $ticket['ticket_type'] == 'Maintenance' ? 'type-maintenance' : 'type-installation';
                            $ticket_type_lower = strtolower(str_replace(' ', '', $ticket['ticket_type']));
                        ?>
                            <tr data-type="<?= $ticket_type_lower ?>" data-priority="<?= $ticket['priority'] ?>" data-status="<?= $ticket['status'] ?>">
                                <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                                <td><span class="type-badge <?= $type_class ?>"><?= htmlspecialchars($ticket['ticket_type']) ?></span></td>
                                <td><?= htmlspecialchars($ticket['client_name']) ?></td>
                                <td><?= htmlspecialchars($ticket['address']) ?></td>
                                <td><?= htmlspecialchars($ticket['contact_number']) ?></td>
                                <td><span class="priority-badge <?= $priority_class ?>"><?= htmlspecialchars($ticket['priority']) ?></span></td>
                                <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>"><?= htmlspecialchars($ticket['status']) ?></span></td>
                                <td>
                                    <?php if ($ticket['ticket_type'] == 'Installation'): ?>
                                        <button class="btn-edit" onclick="window.location.href='tickets_installation.php'">View</button>
                                    <?php elseif ($ticket['ticket_type'] == 'Maintenance'): ?>
                                        <button class="btn-edit" onclick="window.location.href='tickets_maintenance.php'">View</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data">No tickets assigned to you yet. Visit the tickets pages to claim available tickets.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="JavaScript.js"></script>
</body>
</html>
