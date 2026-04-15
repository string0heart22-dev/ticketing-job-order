<?php
// Unified sidebar for all root folder pages
// Usage: include 'sidebar.php'; after session_start() and role checks

// Detect if we're in a subdirectory and set the path prefix accordingly
$script_dir = dirname($_SERVER['PHP_SELF']);
$base_path = '';

// If we're in the olt folder (or any subfolder), go up one level
if (strpos($script_dir, '/olt') !== false || basename($script_dir) === 'olt') {
    $base_path = '../';
}

// Determine current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check if a link is active
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Helper function to check if any tickets page is active
function isTicketsActive() {
    global $current_page;
    $ticket_pages = ['tickets.php', 'tickets_installation.php', 'tickets_maintenance.php', 'tickets_pullout.php'];
    return in_array($current_page, $ticket_pages);
}

// Include permission helper to check user permissions
require_once 'permission_helper.php';

// Helper function to check if user has access to a page
function hasPageAccess($page) {
    $userID = $_SESSION['userID'] ?? null;
    if (!$userID) return false;

    // Load config if not already loaded
    if (!isset($GLOBALS['conn'])) {
        require_once 'config.php';
    }
    global $conn;

    // Check if connection is available
    if (!isset($conn) || $conn === null) {
        return true; // Default to allow if no connection
    }

    $col = "can_$page";
    
    // Check if column exists first
    $checkCol = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if (!$checkCol || $checkCol->num_rows === 0) {
        // Column doesn't exist, default to allow
        return true;
    }
    
    $sql = "SELECT $col FROM users WHERE userID = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $hasAccess = ($row[$col] ?? 1) == 1;
            $stmt->close();
            return $hasAccess;
        }
        $stmt->close();
    }
    return true; // Default to allow if error
}
?>

<aside id="sidebar" class="sidebar collapsed" role="navigation" aria-label="Primary sidebar" aria-hidden="true">
    <nav>
        <ul>
            <!-- Main Section -->
            <div class="sidebar-section">
                <div class="section-title">📌 Main</div>
                <ul>
                    <li><a href="<?= $base_path ?>htmlpage.php" class="nav-link <?= isActive('htmlpage.php') ?>"><span class="nav-icon">🏠</span> <span class="nav-text">Home</span></a></li>
                </ul>
            </div>

            <!-- Operations Section -->
            <div class="sidebar-section">
                <div class="section-title">⚡ Operations</div>
                <ul>
                    <?php if (hasPageAccess('tickets') || hasPageAccess('tickets_installation') || hasPageAccess('tickets_maintenance') || hasPageAccess('tickets_pullout')): ?>
                    <li class="has-submenu tickets-menu <?= isTicketsActive() ? 'open' : '' ?>" aria-haspopup="true">
                        <a href="#" class="submenu-toggle nav-link" aria-expanded="<?= isTicketsActive() ? 'true' : 'false' ?>">
                            <span class="nav-icon">📋</span> <span class="nav-text">Tickets</span>
                            <span class="arrow">▼</span>
                        </a>
                        <ul class="submenu" aria-label="Tickets submenu" <?= isTicketsActive() ? 'style="display:block;"' : '' ?>>
                            <?php if (hasPageAccess('tickets')): ?>
                            <li><a href="<?= $base_path ?>tickets.php" class="<?= isActive('tickets.php') ? 'active' : '' ?>">All Tickets</a></li>
                            <?php endif; ?>
                            <?php if (hasPageAccess('tickets_installation')): ?>
                            <li><a href="<?= $base_path ?>tickets_installation.php" class="<?= isActive('tickets_installation.php') ? 'active' : '' ?>">Installation</a></li>
                            <?php endif; ?>
                            <?php if (hasPageAccess('tickets_maintenance')): ?>
                            <li><a href="<?= $base_path ?>tickets_maintenance.php" class="<?= isActive('tickets_maintenance.php') ? 'active' : '' ?>">Maintenance</a></li>
                            <?php endif; ?>
                            <?php if (hasPageAccess('tickets_pullout')): ?>
                            <li><a href="<?= $base_path ?>tickets_pullout.php" class="<?= isActive('tickets_pullout.php') ? 'active' : '' ?>">Pull Out</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('installation_form')): ?>
                    <li><a href="<?= $base_path ?>installation_form.php" class="nav-link <?= isActive('installation_form.php') ?>"><span class="nav-icon">📝</span> <span class="nav-text">Installation Form</span></a></li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('olt')): ?>
                    <li><a href="<?= $base_path ?>olt/olt.php" class="nav-link <?= isActive('olt.php') ?>"><span class="nav-icon">🌐</span> <span class="nav-text">OLT Devices</span></a></li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('customer_database')): ?>
                    <li><a href="<?= $base_path ?>customer_database.php" class="nav-link <?= isActive('customer_database.php') ?>"><span class="nav-icon">📊</span> <span class="nav-text">Customer Database</span></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Management Section -->
            <div class="sidebar-section">
                <div class="section-title">📊 Management</div>
                <ul>
                    <?php if (hasPageAccess('service_areas')): ?>
                    <li><a href="<?= $base_path ?>service_areas.php" class="nav-link <?= isActive('service_areas.php') ?>"><span class="nav-icon">📍</span> <span class="nav-text">Service Areas</span></a></li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('service_plans')): ?>
                    <li><a href="<?= $base_path ?>service_plans.php" class="nav-link <?= isActive('service_plans.php') ?>"><span class="nav-icon">💼</span> <span class="nav-text">Service Plans</span></a></li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('reports')): ?>
                    <li><a href="<?= $base_path ?>reports.php" class="nav-link <?= isActive('reports.php') ?>"><span class="nav-icon">📊</span> <span class="nav-text">Reports</span></a></li>
                    <?php endif; ?>
                    <?php if (hasPageAccess('inventory')): ?>
                    <li><a href="<?= $base_path ?>inv/overview.php" class="nav-link <?= isActive('overview.php') ?>"><span class="nav-icon">📦</span> <span class="nav-text">Inventory</span></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- System Section -->
            <div class="sidebar-section">
                <div class="section-title">⚙️ System</div>
                <ul>
                    <?php if (hasPageAccess('users')): ?>
                    <li><a href="<?= $base_path ?>USERs.php" class="nav-link <?= isActive('USERs.php') ?>"><span class="nav-icon">👥</span> <span class="nav-text">Users</span></a></li>
                    <?php endif; ?>
                    <li><a href="<?= $base_path ?>logs.php" class="nav-link <?= isActive('logs.php') ?>"><span class="nav-icon">📋</span> <span class="nav-text">Activity Logs</span></a></li>
                    <li><a href="<?= $base_path ?>logout.php" class="nav-link logout-link"><span class="nav-icon">🚪</span> <span class="nav-text">Logout</span></a></li>
                </ul>
            </div>
        </ul>
    </na
</aside></noscript><script>if(typeof window!=="undefined"&&window.localStorage){document.write('</aside>');}</script>

<div id="sidebarOverlay" class="sidebar-overlay" hidden></div>
