<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'config.php';
require_once 'role_check.php';
require_once 'permission_helper.php';
requireAdminRole();
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_url = str_replace(' ', '%20', $base_url);
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Check if installations table exists
$table_check = $conn->query("SHOW TABLES LIKE 'installations'");
if ($table_check->num_rows == 0) {
    $error_message = 'Installations table not found. Please run <a href="setup_installation_db.php">setup_installation_db.php</a> first.';
    $result = null;
    $users = [];
} else {
    // Check if status_comment column exists, add it if not
    $check_comment_col = $conn->query("SHOW COLUMNS FROM installations LIKE 'status_comment'");
    if ($check_comment_col && $check_comment_col->num_rows == 0) {
        $conn->query("ALTER TABLE installations ADD COLUMN status_comment TEXT NULL AFTER status");
    }
    
    // Fetch all installations with assigned user
    $sql = "SELECT i.id, i.ticket_number, i.client_name, i.address, i.contact_number, i.status, i.service_type, 
            i.connection_type, i.plan, i.installation_date, i.nap_assignment, i.created_at,
            i.installed_time, i.cancelled_time, i.negative_time, i.in_progress_time,
            COALESCE(i.assigned_to, NULL) as assigned_to,
            COALESCE(i.priority, 'Normal') as priority,
            i.status_comment
            FROM installations i
            ORDER BY
                CASE 
                    WHEN status IN ('Installed', 'Cancelled', 'Negative') THEN 2
                    ELSE 1
                END,
                CASE 
                    WHEN status = 'In Progress' THEN
                        CASE COALESCE(priority, 'Normal')
                            WHEN 'Urgent' THEN 1
                            WHEN 'Normal' THEN 2
                            WHEN 'Less Priority' THEN 3
                            ELSE 2
                        END
                    ELSE NULL
                END ASC,
                CASE 
                    WHEN status = 'In Progress' THEN in_progress_time
                    WHEN status = 'Installed' THEN installed_time
                    WHEN status = 'Cancelled' THEN cancelled_time
                    WHEN status = 'Negative' THEN negative_time
                    ELSE created_at
                END DESC,
                CASE 
                    WHEN status = 'Pending' THEN
                        CASE COALESCE(priority, 'Normal')
                            WHEN 'Urgent' THEN 1
                            WHEN 'Normal' THEN 2
                            WHEN 'Less Priority' THEN 3
                            ELSE 2
                        END
                    ELSE NULL
                END ASC,
                CASE 
                    WHEN status = 'Pending' THEN created_at
                    ELSE NULL
                END DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        // If query fails, it might be because columns don't exist yet
        $error_message = 'Database error. Please run <a href="setup_installation_db.php">setup_installation_db.php</a> or import <strong>update_installations_table.sql</strong> in phpMyAdmin to update the table structure.';
        $result = null;
    }

    // Fetch users for assignment
    $users_sql = "SELECT userID, name, role FROM users ORDER BY name ASC";
    $users_result = $conn->query($users_sql);
    $installations = [];
    if ($users_result) {
        while ($user = $users_result->fetch_assoc()) {
            $users[] = $user;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <style>
        .input-dropdown-group {
            position: relative;
            width: 100%;
            margin-bottom: 0.5em;
        }
        .input-dropdown-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 36px 10px 12px;
            border: 1px solid #cfd8dc;
            border-radius: 6px;
            font-size: 1em;
            background: #f9fafb;
            transition: border 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .input-dropdown-group input:focus {
            border: 1.5px solid #1976d2;
            background: #fff;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);
        }
        .input-dropdown-group .dropdown-arrow {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 1.2em;
            color: #757575;
            transition: color 0.2s;
        }
        .input-dropdown-group input:focus ~ .dropdown-arrow {
            color: #1976d2;
        }
        .custom-dropdown {
            box-shadow: 0 4px 16px rgba(25, 118, 210, 0.10);
            border-radius: 0 0 8px 8px;
            font-size: 1em;
            border: 1.5px solid #1976d2;
            border-top: none;
            margin-top: -2px;
            padding: 0;
            z-index: 100;
            background: #fff;
            animation: dropdownFadeIn 0.18s;
        }
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .custom-dropdown li {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            border-bottom: 1px solid #f0f0f0;
            font-size: 1em;
            color: #222;
            background: #fff;
        }
        .custom-dropdown li:last-child {
            border-bottom: none;
        }
        .custom-dropdown li:hover, .custom-dropdown li.active {
            background: #e3f2fd;
            color: #1976d2;
        }
        .custom-dropdown li:active {
            background: #bbdefb;
            color: #0d47a1;
        }
        .btn-save-tech-success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            transform: scale(0.97);
            transition: background 0.3s, transform 0.15s;
        }
        .btn-save-tech-error {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            transform: scale(0.97);
            transition: background 0.3s, transform 0.15s;
        }
        /* Edit modal should appear in front of view modal */
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Tickets - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />
    <link rel="stylesheet" href="tickets.css?v=14" />
    <link rel="stylesheet" href="notification_bar.css?v=11" />
    <link rel="preload" href="eye2.png" as="image" />
    <script>window.BASE_URL = '<?= $base_url ?>';</script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    
</head>
<body <?= getPermissionBodyAttributes() ?>>
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
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'G', 0, 1)) ?>
                </div>
            </div>
        </div>
    </header>

   <?php include 'sidebar.php'; ?>>

    <div id="sidebarOverlay" class="sidebar-overlay" hidden></div>

    <main id="mainContent" class="main">
        <h1>Installation Tickets</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <!-- Status Tabs -->
        <div class="status-tabs">
            <button class="tab-btn active" data-status="Pending">Pending</button>
            <button class="tab-btn" data-status="In Progress">In Progress</button>
            <button class="tab-btn" data-status="Installed">Installed</button>
            <button class="tab-btn" data-status="Cancelled">Cancelled</button>
            <button class="tab-btn" data-status="Negative">Negative</button>
            <button class="tab-btn" data-status="all">All Tickets</button>
        </div>

        <!-- Filters Section -->
        <div class="filters-bar">
            <div class="filter-group search-group">
                <label for="search-input">🔍 Search Tickets</label>
                <input type="text" id="search-input" class="search-input" placeholder="Search by name, address, contact...">
            </div>
            <button class="clear-filters-btn" onclick="resetTicketFilters()">Clear Filters</button>
        </div>

        <!-- City Filter Buttons -->
        <div class="city-filters">
            <div class="city-filters-label">📍 Filter by City/Town:</div>
            <div id="city-buttons-container" class="city-buttons-container">
                <button class="city-btn active" data-city="">All Cities</button>
                <!-- City buttons will be loaded here dynamically -->
            </div>
        </div>

        <div class="actions-bar">
            <button class="btn-create-ticket" id="create-ticket-btn">+ Create Ticket</button>
            <button class="btn-delete-selected" id="delete-selected-btn" style="display: none;">🗑️ Delete Selected</button>
        </div>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-installation"></th>
                        <th>Actions</th>
                        <th>Ticket #</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th>Plan</th>
                        <th>Service / Connection</th>
                        <th id="date-column-header">Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            // Get assigned user name
                            $assigned_name = 'Unassigned';
                            if ($row['assigned_to']) {
                                $user_query = $conn->query("SELECT name FROM users WHERE userID = " . intval($row['assigned_to']));
                                if ($user_query && $user_query->num_rows > 0) {
                                    $user_data = $user_query->fetch_assoc();
                                    $assigned_name = $user_data['name'];
                                }
                            }
                            
                            $priority_class = '';
                            if ($row['priority'] == 'Urgent') $priority_class = 'priority-urgent';
                            elseif ($row['priority'] == 'Normal') $priority_class = 'priority-normal';
                            else $priority_class = 'priority-low';
                            
                            // Check if ticket has comment (Cancelled or Negative with comment)
                            $has_comment = !empty($row['status_comment']) && in_array($row['status'], ['Cancelled', 'Negative']);
                            $row_class = $has_comment ? 'ticket-row-comment-overlay' : '';
                        ?>
                            <tr class="<?= $row_class ?>" data-status="<?= $row['status'] ?>" data-priority="<?= $row['priority'] ?>" data-type="installation">
                                <td><input type="checkbox" class="row-checkbox" data-id="<?= $row['id'] ?>"></td>
                                <td class="td-actions" onclick="event.stopPropagation()">
                                    <div class="action-buttons">
                                        <?php if (canAssignTickets()): ?>
                                            <button class="btn-icon btn-assign" onclick="openAssignModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['client_name'], ENT_QUOTES) ?>')" title="Assign">➕</button>
                                        <?php endif; ?>
                                        <button class="btn-icon btn-view" onclick="viewInstallation(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                        <?php if (canDelete()): ?>
                                            <button class="btn-icon btn-delete" onclick="deleteTicket(<?= $row['id'] ?>)" title="Delete">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="position:relative;">
                                    <?php if ($has_comment): ?>
                                    <div style="position:absolute;top:0;left:-100%;right:-900%;bottom:0;background:linear-gradient(135deg,rgba(100, 95, 95, 0.85),rgba(126, 113, 113, 0.85));color:white;display:flex;align-items:center;justify-content:center;z-index:5;font-size:1.1rem;padding:10px;pointer-events:none;text-align:center;font-weight:600;"><?= htmlspecialchars($row['status_comment']) ?></div>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($row['ticket_number'] ?? 'INST-' . $row['id']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['client_name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                <td>₱<?= htmlspecialchars($row['plan']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($row['service_type']) ?></div>
                                    <small style="color: #666;"><?= htmlspecialchars($row['connection_type']) ?></small>
                                </td>
                                <td class="created-cell">
                                    <?php 
                                    $display_date = null;
                                    $display_label = 'Created';
                                    
                                    // For installation tickets, always show status label if in completed status
                                    // Use status-specific time if available, otherwise fall back to created_at
                                    if ($row['status'] === 'Installed') {
                                        $display_label = 'Installed';
                                        $time_val = !empty($row['installed_time']) ? $row['installed_time'] : $row['created_at'];
                                        if ($time_val) {
                                            $display_date = new DateTime($time_val, new DateTimeZone('Asia/Manila'));
                                        }
                                    } elseif ($row['status'] === 'In Progress') {
                                        $display_label = 'In Progress';
                                        $time_val = !empty($row['in_progress_time']) ? $row['in_progress_time'] : $row['created_at'];
                                        if ($time_val) {
                                            $display_date = new DateTime($time_val, new DateTimeZone('Asia/Manila'));
                                        }
                                    } elseif ($row['status'] === 'Cancelled') {
                                        $display_label = 'Cancelled';
                                        $time_val = !empty($row['cancelled_time']) ? $row['cancelled_time'] : $row['created_at'];
                                        if ($time_val) {
                                            $display_date = new DateTime($time_val, new DateTimeZone('Asia/Manila'));
                                        }
                                    } elseif ($row['status'] === 'Negative') {
                                        $display_label = 'Negative';
                                        $time_val = !empty($row['negative_time']) ? $row['negative_time'] : $row['created_at'];
                                        if ($time_val) {
                                            $display_date = new DateTime($time_val, new DateTimeZone('Asia/Manila'));
                                        }
                                    } elseif ($row['status'] === 'On Hold') {
                                        $display_label = 'On Hold';
                                        if (isset($row['created_at']) && $row['created_at']) {
                                            $display_date = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
                                        }
                                    } elseif (isset($row['created_at']) && $row['created_at']) {
                                        $display_date = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
                                    }
                                    
                                    if ($display_date) {
                                        echo '<small>' . $display_label . ':</small><br>';
                                        echo $display_date->format('M j, Y') . '<br>';
                                        echo $display_date->format('g:i A');
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="details-column">
                                        <div class="detail-item">
                                            <span class="priority-badge <?= $priority_class ?>"><?= htmlspecialchars($row['priority']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <select class="status-select" data-id="<?= $row['id'] ?>" onchange="updateStatus(<?= $row['id'] ?>, this.value)">
                                                <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="In Progress" <?= $row['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="Installed" <?= $row['status'] == 'Installed' ? 'selected' : '' ?>>Installed</option>
                                                <option value="Cancelled" <?= $row['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                <option value="Negative" <?= $row['status'] == 'Negative' ? 'selected' : '' ?>>Negative</option>
                                            </select>
                                        </div>
                                        <div class="detail-item assigned-to">
                                            <small>👤 <?= htmlspecialchars($assigned_name) ?></small>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-data">No installation tickets found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Create Ticket Modal -->
    <div id="create-ticket-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-create-ticket">×</button>
            <h2>Create Installation Ticket</h2>
            <form id="create-ticket-form" action="process_installation.php" method="POST">
                
                <div class="form-section">
                    <h3>Client Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_name">Client Name <span class="required">*</span></label>
                            <input type="text" id="ticket_name" name="client_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_number">Contact Number</label>
                            <input type="tel" id="ticket_number" name="contact_number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_email">Email</label>
                            <input type="email" id="ticket_email" name="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_account">Account Number <span class="required"></span></label>
                            <input type="text" id="ticket_account" name="account_number">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Address</label>
                        <div class="address-cascade">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ticket_province">Province</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ticket_city">City</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_city_input" type="text" placeholder="Select province first.." autocomplete="off" name="city" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ticket_barangay">Barangay</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_barangay_input" type="text" placeholder="Select city first.." autocomplete="off" name="barangay" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ticket_purok_zone">Purok/Zone</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ticket_zip_code">Zip Code</label>
                                    <input type="text" id="ticket_zip_code" name="zip_code" readonly>
                                </div>
                            </div>
                            <!-- Hidden field for full address -->
                            <input type="hidden" id="ticket_address" name="address">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Installation Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_date">Installation Date</label>
                            <input type="date" id="ticket_date" name="installation_date">
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_nap">NAP Assignment <span class="required"></span></label>
                            <input type="text" id="ticket_nap" name="nap_assignment" >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_connection">Connection Type <span class="required">*</span></label>
                            <select id="ticket_connection" name="connection_type" required>
                                <option value="">--Select Type--</option>
                                <option value="Wireless">Wireless</option>
                                <option value="Fiber">Fiber</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_service">Service Type <span class="required">*</span></label>
                            <select id="ticket_service" name="service_type" required>
                                <option value="">--Select Service--</option>
                                <option value="New Client">New Client</option>
                                <option value="Migrate">Migrate</option>
                                <option value="Reconnection as New Client">Reconnection as New Client</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_plan">Plan <span class="required">*</span></label>
                            <select id="ticket_plan" name="plan" required>
                                <option value="">--Select Plan--</option>
                                <!-- Plans loaded dynamically from service_plans table -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_contract">Contract Duration <span class="required">*</span></label>
                            <select id="ticket_contract" name="contract_duration" required>
                                <option value="">--Select Duration--</option>
                                <!-- Duration populated based on selected plan -->
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_prepaid">Prepaid Amount Paid <span class="required"></span></label>
                            <input type="number" id="ticket_prepaid" name="prepaid_amount" step="0.01" min="0" >
                        </div>
                        
                        <div class="form-group">
                            <label for="ticket_priority">Priority <span class="required">*</span></label>
                            <select id="ticket_priority" name="priority" required>
                                <option value="">--Select Priority--</option>
                                <option value="Less Priority">Less Priority</option>
                                <option value="Normal" selected>Normal</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-create-ticket">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign User Modal -->
    <div id="assign-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-assign">×</button>
            <h2>Assign User</h2>
            <form id="assign-form" action="assign_user.php" method="POST">
                <input type="hidden" id="assign_ticket_id" name="ticket_id">
                <p id="assign_ticket_name" class="ticket-info"></p>
                
                <div class="form-group">
                    <label for="assigned_user">Select User <span class="required">*</span></label>
                    <select id="assigned_user" name="user_id" required>
                        <option value="">--Select User--</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['userID'] ?>">
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Assign</button>
                    <button type="button" class="btn-cancel" id="cancel-assign">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Ticket Modal -->
    <div id="edit-ticket-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-edit-ticket">×</button>
            <h2>Edit Installation Ticket</h2>
            <form id="edit-ticket-form" action="update_ticket.php" method="POST">
                <input type="hidden" id="edit_ticket_id" name="ticket_id">
                
                <div class="form-section">
                    <h3>Client Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_client_name">Client Name <span class="required">*</span></label>
                            <input type="text" id="edit_client_name" name="client_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_contact_number">Contact Number</label>
                            <input type="tel" id="edit_contact_number" name="contact_number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_account_number">Account Number <span class="required"></span></label>
                            <input type="text" id="edit_account_number" name="account_number">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Address</label>
                        <div class="address-cascade">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_province">Province</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit_city">City</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_city_input" type="text" placeholder="Select province first.." autocomplete="off" name="city" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_barangay">Barangay</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_barangay_input" type="text" placeholder="Select city first.." autocomplete="off" name="barangay" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit_purok_zone">Purok/Zone</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_zip_code">Zip Code</label>
                                    <input type="text" id="edit_zip_code" name="zip_code" readonly>
                                </div>
                            </div>
                            <!-- Hidden field for full address -->
                            <input type="hidden" id="edit_address" name="address">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Installation Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_installation_date">Installation Date</label>
                            <input type="date" id="edit_installation_date" name="installation_date">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_nap_assignment">NAP Assignment</label>
                            <input type="text" id="edit_nap_assignment" name="nap_assignment">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_connection_type">Connection Type <span class="required">*</span></label>
                            <select id="edit_connection_type" name="connection_type" required>
                                <option value="">--Select Type--</option>
                                <option value="Wireless">Wireless</option>
                                <option value="Fiber">Fiber</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_service_type">Service Type <span class="required">*</span></label>
                            <select id="edit_service_type" name="service_type" required>
                                <option value="">--Select Service--</option>
                                <option value="New Client">New Client</option>
                                <option value="Migrate">Migrate</option>
                                <option value="Reconnection as New Client">Reconnection as New Client</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_plan">Plan <span class="required">*</span></label>
                            <select id="edit_plan" name="plan" required>
                                <option value="">--Select Plan--</option>
                                <!-- Plans loaded dynamically from service_plans table -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_contract_duration">Contract Duration <span class="required">*</span></label>
                            <select id="edit_contract_duration" name="contract_duration" required>
                                <option value="">--Select Duration--</option>
                                <!-- Duration populated based on selected plan -->
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_prepaid_amount">Prepaid Amount Paid</label>
                            <input type="number" id="edit_prepaid_amount" name="prepaid_amount" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_priority">Priority <span class="required">*</span></label>
                            <select id="edit_priority" name="priority" required>
                                <option value="">--Select Priority--</option>
                                <option value="Less Priority">Less Priority</option>
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Update Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-edit-ticket">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Installation Modal -->
    <div id="view-installation-modal" class="modal">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-installation" onclick="InstallationSPA.closeViewModal()">×</button>
            <h2>Installation Details</h2>
            
            <!-- Edit Button at Top -->
            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-installation" onclick="editInstallationFromView()">Edit Installation Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToTechnicalDetails()">⚙️ Jump to Technical Details</button>
                <button type="button" class="btn-subscription" onclick="openSubscriptionAgreement()">📄 Generate Subscription Agreement</button>
            </div>
            
            <div id="view-installation-content">
                <!-- Installation Information -->
                <div class="details-section">
                    <h3>Installation Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Ticket Number:</label>
                            <span id="view-inst-ticket-number" class="ticket-number"></span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span id="view-inst-status"></span>
                        </div>
                        <div class="info-item">
                            <label>Priority:</label>
                            <span id="view-inst-priority"></span>
                        </div>
                        <div class="info-item">
                            <label>Service Type:</label>
                            <span id="view-inst-service-type"></span>
                        </div>
                        <div class="info-item">
                            <label>Connection Type:</label>
                            <span id="view-inst-connection"></span>
                        </div>
                        <div class="info-item">
                            <label>Plan:</label>
                            <span id="view-inst-plan"></span>
                        </div>
                        <div class="info-item">
                            <label>Contract Duration:</label>
                            <span id="view-inst-contract"></span>
                        </div>
                        <div class="info-item">
                            <label>Installation Date:</label>
                            <span id="view-inst-date"></span>
                        </div>
                        <div class="info-item">
                            <label>Assigned To:</label>
                            <span id="view-inst-assigned"></span>
                        </div>
                        <div class="info-item">
                            <label>Created:</label>
                            <span id="view-inst-created"></span>
                        </div>
                        <div class="info-item">
                            <label>Prepaid Amount:</label>
                            <span id="view-inst-prepaid"></span>
                        </div>
                    </div>
                </div>

                <!-- Client Information -->
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name:</label>
                            <span id="view-inst-client-name"></span>
                        </div>
                        <div class="info-item">
                            <label>Contact Number:</label>
                            <span id="view-inst-contact"></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span id="view-inst-email"></span>
                        </div>
                        <div class="info-item">
                            <label>Account Number:</label>
                            <span id="view-inst-account"></span>
                        </div>
                        <div class="info-item full-width"><label>Address: <button type="button" onclick="copyAddress(this)" style="margin-left: 8px; padding: 2px 6px; background: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; vertical-align: middle;">Copy</button></label><span id="view-inst-address"></span></div>
                        <div class="info-item" style="margin-top: -60px; visibility: hidden;"><label></label><span></span></div>
                        <div class="info-item" style="margin-top: -60px;"><label>Service Connection Info: <button type="button" onclick="copyServiceConnectionInfo(this)" style="margin-left: 8px; padding: 2px 6px; background: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; vertical-align: middle;">Copy</button></label><span id="view-inst-service-connection-info" style="padding: 0; background: transparent; border: none; display: none;">
                                <div style="font-weight: 600; color: #1e40af; margin-bottom: 4px;" id="view-inst-service-connection-title"></div>
                                <div style="color: #6b7280; font-size: 0.9rem;" id="view-inst-prepaid-info"></div>
                            </span></div>
                    </div>
                </div>

                <!-- Technical Details Form -->
                <div class="details-section" id="technical-details-section">
                    <h3>Technical Details</h3>
                    <form id="technical-details-form">
                        <input type="hidden" id="tech-installation-id" name="installation_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tech-nap-assignment">NAP Assignment <span class="required"></span></label>
                                <input type="text" id="tech-nap-assignment" name="nap_assignment" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="tech-nap-optical">NAP Box Optical Reading (dBm)</label>
                                <input type="text" id="tech-nap-optical" name="nap_optical_reading" placeholder="e.g., -15.5">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="tech-client-optical">Client Side Optical Reading (dBm)</label>
                                <input type="text" id="tech-client-optical" name="client_optical_reading" placeholder="e.g., -18.2">
                            </div>
                            
                            <div class="form-group">
                                <label for="tech-speed-test">Speed Test (Mbps)</label>
                                <input type="text" id="tech-speed-test" name="speed_test_mbps" placeholder="e.g., 95.5">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="tech-accepts-member">Member</label>
                            <input type="text" id="tech-accepts-member" name="accepts_member" placeholder="Enter member name who accepted the work...">
                        </div>

                        <div class="form-actions">
                            <button type="submit" id="inst-save-tech-btn" class="btn-submit">Save Technical Details</button>
                        </div>
                    </form>
                </div>

                <!-- Clearance Section -->
                <div class="details-section">
                    <h3>Clearance Status</h3>
                    <div class="clearance-grid">
                        <div class="clearance-item">
                            <div class="clearance-header">
                                <h4>NOC Clear</h4>
                                <button type="button" class="btn-clear-noc" id="btn-noc-clear" onclick="clearInstallation('noc', this)">NOC CLEAR</button>
                            </div>
                            <div id="noc-clear-info" class="clearance-info"></div>
                        </div>
                        
                        <div class="clearance-item">
                            <div class="clearance-header">
                                <h4>CS Clear</h4>
                                <button type="button" class="btn-clear-cs" id="btn-cs-clear" onclick="clearInstallation('cs', this)">CS CLEAR</button>
                            </div>
                            <div id="cs-clear-info" class="clearance-info"></div>
                        </div>
                    </div>
                </div>

                <!-- Images Section -->
                <div class="details-section">
                    <h3>Installation Images</h3>
                    
                    <!-- Upload Form -->
                    <form id="inst-upload-form" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" id="inst-upload-id" name="installation_id">
                        <div class="upload-area">
                            <input type="file" id="inst-image-upload" name="images[]" multiple accept="image/*" style="display: none;">
                            <label for="inst-image-upload" class="upload-label">
                                <span class="upload-icon">📷</span>
                                <span>Click to select images</span>
                                <span class="upload-hint">You can select multiple images</span>
                            </label>
                        </div>
                        <div id="inst-preview-container" class="preview-container"></div>
                        <button type="submit" class="btn-upload" id="inst-upload-btn" style="display: none;">Upload Images</button>
                    </form>

                    <!-- Uploaded Images Gallery -->
                    <div id="inst-images-gallery" class="images-gallery"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="inst-image-modal" class="image-modal" onclick="if(event.target===this)closeInstImageModal()">
        <span class="close-modal" onclick="event.stopPropagation();closeInstImageModal()">&times;</span>
        <button class="modal-nav-btn modal-prev-btn" onclick="event.stopPropagation();showPrevImage()">&#10094;</button>
        <img class="modal-image" id="inst-modal-image" src="" style="cursor:zoom-in;transition:transform 0.1s;transform-origin:center center;">
        <button class="modal-nav-btn modal-next-btn" onclick="event.stopPropagation();showNextImage()">&#10095;</button>
    </div>

    <!-- Status Comment Modal -->
    <div id="status-comment-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-status-comment">&times;</button>
            <h2>Add Comment for Status Change</h2>
            <form id="status-comment-form">
                <input type="hidden" id="comment_ticket_id" name="ticket_id">
                <input type="hidden" id="comment_status" name="status">
                
                <div class="form-group">
                    <label for="status_comment">Comment <span class="required">*</span></label>
                    <textarea id="status_comment" name="comment" rows="4" required placeholder="Please provide a reason for this status change..."></textarea>
                    <small style="color: #666;">This comment will be displayed on the ticket row.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Save Status & Comment</button>
                    <button type="button" class="btn-cancel" id="cancel-status-comment">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Role flags for SPA-rendered rows
        window._canAssign = <?= canAssignTickets() ? 'true' : 'false' ?>;
        window._canDelete = <?= canDelete()         ? 'true' : 'false' ?>;
    </script>
    <script src="JavaScript.js?v=7"></script>
    <script src="dialog.js?v=1"></script>
    <script src="notification_bar.js?v=11"></script>
    <script src="db_events.js?v=1"></script>
    <script src="tickets.js?v=8"></script>
    <script src="installation_view.js?v=28"></script>
    <script src="spa_view_modal.js?v=1"></script>
    <script src="bulk_actions.js?v=8"></script>
    <script src="service_plans_loader.js?v=8"></script>
    <script src="ticket_filters.js?v=8"></script>
    <script src="tickets_status_filter.js?v=7"></script>
    <script src="session_keeper.js"></script>
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Select2 Integration -->
    <script src="select2-integration.js"></script>
    <!-- Address Formatting -->
    <script src="format_address.js"></script>
    <script src="tickets_address_save_patch.js?v=3"></script>
    <script src="permissions.js"></script>
        <script>
        // --- Custom Address Dropdowns for Ticket Modal & Edit Modal ---
        let ticket_serviceAreas = [];
        let ticket_provinces = [];
        let ticket_cities = {};
        let ticket_barangays = {};
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_service_areas_list.php?t=' + Date.now())
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Service areas loaded:', data);
                    if (data.success && data.areas && data.areas.length > 0) {
                        ticket_serviceAreas = data.areas;
                        ticket_provinces = [];
                        ticket_cities = {};
                        ticket_barangays = {};
                        data.areas.forEach(area => {
                            if (!ticket_provinces.includes(area.province)) ticket_provinces.push(area.province);
                            if (!ticket_cities[area.province]) ticket_cities[area.province] = [];
                            if (!ticket_cities[area.province].includes(area.city)) ticket_cities[area.province].push(area.city);
                            const cityKey = area.province + '|' + area.city;
                            if (!ticket_barangays[cityKey]) ticket_barangays[cityKey] = [];
                            if (!ticket_barangays[cityKey].includes(area.barangay)) ticket_barangays[cityKey].push(area.barangay);
                        });
                        // Expose globally so parseAndPopulateAddress (tickets.js) can use them
                        window.ticket_serviceAreas = ticket_serviceAreas;
                        window.ticket_provinces    = ticket_provinces;
                        window.ticket_cities       = ticket_cities;
                        window.ticket_barangays    = ticket_barangays;
                        window.setupCustomDropdown = setupCustomDropdown;
                        // Create Ticket Modal
                        setupCustomDropdown('ticket_province', ticket_provinces, function(selectedProvince) {
                            const cityList = ticket_cities[selectedProvince] || [];
                            setupCustomDropdown('ticket_city', cityList, function(selectedCity) {
                                const cityKey = selectedProvince + '|' + selectedCity;
                                const barangayList = ticket_barangays[cityKey] || [];
                                setupCustomDropdown('ticket_barangay', barangayList, function(selectedBarangay) {
                                    // Auto-fill zip code from service area
                                    const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('ticket_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('ticket_barangay_input').value = '';
                                document.getElementById('ticket_zip_code').value = '';
                            });
                            document.getElementById('ticket_city_input').value = '';
                            document.getElementById('ticket_barangay_input').value = '';
                            document.getElementById('ticket_zip_code').value = '';
                        });
                        // Edit Ticket Modal
                        setupCustomDropdown('edit_province', ticket_provinces, function(selectedProvince) {
                            const cityList = ticket_cities[selectedProvince] || [];
                            setupCustomDropdown('edit_city', cityList, function(selectedCity) {
                                const cityKey = selectedProvince + '|' + selectedCity;
                                const barangayList = ticket_barangays[cityKey] || [];
                                setupCustomDropdown('edit_barangay', barangayList, function(selectedBarangay) {
                                    // Auto-fill zip code from service area
                                    const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('edit_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('edit_barangay_input').value = '';
                                document.getElementById('edit_zip_code').value = '';
                            });
                            document.getElementById('edit_city_input').value = '';
                            document.getElementById('edit_barangay_input').value = '';
                            document.getElementById('edit_zip_code').value = '';
                        });
                    } else {
                        console.warn('No service areas found or error:', data);
                        if (!data.success) {
                            alert('Error loading service areas: ' + (data.error || 'Unknown error'));
                        } else {
                            alert('No service areas found in database. Please add service areas first.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading service areas:', error);
                    alert('Failed to load service areas. Error: ' + error.message);
                });
            // Purok/Zone dropdowns
            const purokOptions = [
                'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
            ];
            setupCustomDropdown('ticket_purok_zone', purokOptions);
            setupCustomDropdown('edit_purok_zone', purokOptions);
        });
        // --- setupCustomDropdown is defined globally below ---
        </script>
    <script>
        // --- setupCustomDropdown function (global so tickets.js can reference it) ---
        function setupCustomDropdown(type, items, onSelect) {
            const input = document.getElementById(type + '_input');
            const dropdown = document.getElementById(type + '_dropdown');
            if (!input || !dropdown) return;
            input.value = '';
            input.placeholder = items.length ? 'Type or select ' + type.replace(/_/g,' ') + '...' : (type.includes('city') ? 'Select province first..' : type.includes('barangay') ? 'Select city first..' : '');
            dropdown.innerHTML = '';
            items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item;
                li.tabIndex = 0;
                li.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item;
                    dropdown.style.display = 'none';
                    if (onSelect) onSelect(item);
                });
                dropdown.appendChild(li);
            });
            // Show dropdown on focus or arrow click
            input.addEventListener('focus', showDropdown);
            input.parentElement.querySelector('.dropdown-arrow').addEventListener('mousedown', function(e) {
                e.preventDefault();
                showDropdown();
                input.focus();
            });
            // Hide dropdown on blur (with delay for click)
            input.addEventListener('blur', function() {
                setTimeout(hideDropdown, 150);
            });
            // Filter dropdown as user types
            input.addEventListener('input', function() {
                filterDropdown(this.value);
            });
            // Keyboard navigation
            input.addEventListener('keydown', function(e) {
                const visibleItems = Array.from(dropdown.querySelectorAll('li')).filter(li => li.style.display !== 'none');
                let active = dropdown.querySelector('li.active');
                if (e.key === 'ArrowDown') {
                    if (!dropdown.style.display || dropdown.style.display === 'none') showDropdown();
                    if (active) {
                        active.classList.remove('active');
                        let next = visibleItems[visibleItems.indexOf(active) + 1] || visibleItems[0];
                        next.classList.add('active');
                    } else if (visibleItems.length) {
                        visibleItems[0].classList.add('active');
                    }
                    e.preventDefault();
                } else if (e.key === 'ArrowUp') {
                    if (active) {
                        active.classList.remove('active');
                        let prev = visibleItems[visibleItems.indexOf(active) - 1] || visibleItems[visibleItems.length - 1];
                        prev.classList.add('active');
                    }
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    if (active) {
                        input.value = active.textContent;
                        dropdown.style.display = 'none';
                        if (onSelect) onSelect(active.textContent);
                    }
                    e.preventDefault();
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });
            function showDropdown() {
                filterDropdown(input.value);
                dropdown.style.display = 'block';
            }
            function hideDropdown() {
                dropdown.style.display = 'none';
                let active = dropdown.querySelector('li.active');
                if (active) active.classList.remove('active');
            }
            function filterDropdown(val) {
                const filter = val.toLowerCase();
                let anyVisible = false;
                Array.from(dropdown.querySelectorAll('li')).forEach(li => {
                    if (li.textContent.toLowerCase().includes(filter)) {
                        li.style.display = '';
                        anyVisible = true;
                    } else {
                        li.style.display = 'none';
                    }
                });
                if (!anyVisible) dropdown.style.display = 'none';
            }
        }
        </script>
    <script>
        // Before form submission, combine address fields for edit ticket
        document.getElementById('edit-ticket-form').addEventListener('submit', function(e) {
            const fullAddress = getFullAddress('edit_');
            document.getElementById('edit_address').value = fullAddress;
        });
    </script>

    <script>
    // ── SPA Auto-Refresh for Installation Tickets (every 3 seconds) ──────────
    (function () {
        const INTERVAL = 3000;
        let lastHash = null;

        function priorityClass(p) {
            if (p === 'Urgent') return 'priority-urgent';
            if (p === 'Normal') return 'priority-normal';
            return 'priority-low';
        }

        // Preload eye icon
        const eyeIcon = new Image();
        eyeIcon.src = 'eye2.png';

        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        function getDisplayDate(ticket) {
            const status = (ticket.status || '').toString().trim();
            const completedStatuses = ['Installed', 'In Progress', 'Cancelled', 'Negative', 'Completed', 'Closed', 'On Hold'];
            
            if (!status || !completedStatuses.includes(status)) {
                return { date: ticket.created_at, label: 'Created' };
            }
            
            // For installation tickets, always show status label
            // Use status-specific time if available, otherwise fall back to created_at
            switch(status) {
                case 'Installed':
                    return { date: ticket.installed_time || ticket.created_at, label: 'Installed' };
                case 'In Progress':
                    return { date: ticket.in_progress_time || ticket.created_at, label: 'In Progress' };
                case 'Cancelled':
                    return { date: ticket.cancelled_time || ticket.created_at, label: 'Cancelled' };
                case 'Negative':
                    return { date: ticket.negative_time || ticket.created_at, label: 'Negative' };
                case 'On Hold':
                    return { date: ticket.created_at, label: 'On Hold' };
                default:
                    return { date: ticket.created_at, label: status };
            }
        }

        function buildRow(t) {
            const assignedName = t.assigned_name || 'Unassigned';
            const displayInfo = getDisplayDate(t);
            
            // Debug log
            console.log('Building row for ticket', t.id, 'status:', t.status, 'comment:', t.status_comment);
            
            // Check if ticket has comment overlay
            const hasComment = t.status_comment && (t.status === 'Cancelled' || t.status === 'Negative');
            const rowClass = hasComment ? 'ticket-row-comment-overlay' : '';
            
            if (hasComment) {
                console.log('Ticket has comment overlay:', t.status_comment);
            }
            
            // Format date to match PHP's 3-line output
            let dateHtml = '-';
            if (displayInfo.date) {
                const date = new Date(displayInfo.date);
                if (!isNaN(date.getTime())) {
                    const dateStr = date.toLocaleDateString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric'
                    });
                    const timeStr = date.toLocaleTimeString('en-US', {
                        hour: 'numeric', minute: '2-digit', hour12: true
                    });
                    dateHtml = `<small>${displayInfo.label}:</small><br>${dateStr}<br>${timeStr}`;
                }
            }

            let actionBtns = `<button class="btn-icon btn-view" onclick="viewInstallation(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
            if (window._canAssign) {
                actionBtns = `<button class="btn-icon btn-assign" onclick="openAssignModal(${t.id}, '${(t.client_name||'').replace(/'/g,"\\'")}')" title="Assign"></button>` + actionBtns;
            }
            if (window._canDelete) {
                actionBtns += `<button class="btn-icon btn-delete" onclick="deleteTicket(${t.id})" title="Delete">🗑️</button>`;
            }

            // Build comment overlay if there's a comment
            let commentOverlay = '';
            if (hasComment) {
                commentOverlay = `<div style="position:absolute;top:0;left:-100%;right:-900%;bottom:0;background:linear-gradient(135deg,rgba(100, 95, 95, 0.85),rgba(83, 81, 81, 0.85));color:white;display:flex;align-items:center;justify-content:center;z-index:5;font-size:1.1rem;padding:10px;pointer-events:none;text-align:center;font-weight:600;">${t.status_comment}</div>`;
            }

            return `<tr class="${rowClass}" data-status="${t.status}" data-priority="${t.priority}" data-type="installation" data-id="${t.id}">
                <td><input type="checkbox" class="row-checkbox" data-id="${t.id}"></td>
                <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${actionBtns}</div></td>
                <td style="position:relative;">
                    ${commentOverlay}
                    ${t.ticket_number || 'INST-' + t.id}
                </td>
                <td>${t.client_name || ''}</td>
                <td>${t.address || ''}</td>
                <td>${t.contact_number || ''}</td>
                <td>&#8369;${t.plan || ''}</td>
                <td><div>${t.service_type || ''}</div><small style="color:#666;">${t.connection_type || ''}</small></td>
                <td class="created-cell">${dateHtml}</td>
                <td>
                    <div class="details-column">
                        <div class="detail-item"><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span></div>
                        <div class="detail-item">
                            <select class="status-select" data-id="${t.id}" onchange="updateStatus(${t.id}, this.value)">
                                <option value="Pending" ${t.status==='Pending'?'selected':''}>Pending</option>
                                <option value="In Progress" ${t.status==='In Progress'?'selected':''}>In Progress</option>
                                <option value="Installed" ${t.status==='Installed'?'selected':''}>Installed</option>
                                <option value="Cancelled" ${t.status==='Cancelled'?'selected':''}>Cancelled</option>
                                <option value="Negative" ${t.status==='Negative'?'selected':''}>Negative</option>
                            </select>
                        </div>
                        <div class="detail-item assigned-to"><small>&#128100; ${assignedName}</small></div>
                    </div>
                </td>
            </tr>`;
        }

        function applyFilters(tbody) {
            const searchVal = (document.getElementById('search-input') || {}).value || '';
            const activeStatus = (document.querySelector('.tab-btn.active') || {}).getAttribute('data-status') || 'Pending';
            const activeCity = (document.querySelector('.city-btn.active') || {}).getAttribute('data-city') || '';
            tbody.querySelectorAll('tr[data-status]').forEach(row => {
                const matchStatus = activeStatus === 'all' || row.getAttribute('data-status') === activeStatus;
                const matchCity = !activeCity || (row.querySelector('td:nth-child(5)') || {}).textContent?.toLowerCase().includes(activeCity.toLowerCase());
                const matchSearch = !searchVal.trim() || row.textContent.toLowerCase().includes(searchVal.toLowerCase());
                row.style.display = (matchStatus && matchCity && matchSearch) ? '' : 'none';
            });
            if (typeof updateTabCounts === 'function') updateTabCounts();
        }

        function refresh(force) {
            if (!force && document.querySelector('.modal[style*="block"]')) return;

            fetch('get_installation_tickets.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const hash = data.tickets.map(t => t.id + t.status + t.priority + (t.assigned_to||'')).join('|');
                    if (!force && hash === lastHash) return;
                    lastHash = hash;

                    const tbody = document.querySelector('.tickets-table tbody');
                    if (!tbody) return;

                    tbody.innerHTML = data.tickets.length === 0
                        ? '<tr><td colspan="10" class="no-data">No installation tickets found</td></tr>'
                        : data.tickets.map(buildRow).join('');

                    applyFilters(tbody);
                })
                .catch(() => {});
        }

        // Exposed so other scripts can trigger an immediate re-render
        window.triggerInstallationRefresh = () => refresh(true);

        setInterval(refresh, INTERVAL);
        
        // Function to update date column header based on active tab
        function updateDateColumnHeader() {
            const header = document.getElementById('date-column-header');
            if (!header) return;
            
            const activeTab = document.querySelector('.tab-btn.active');
            const status = activeTab ? activeTab.getAttribute('data-status') : 'all';
            
            const headerMap = {
                'Pending': 'Created',
                'In Progress': 'Started Time',
                'Installed': 'Installed Time',
                'Cancelled': 'Cancelled Time',
                'Negative': 'Negative Time',
                'On Hold': 'On Hold Time',
                'all': 'Time Started / Time Finished'
            };
            
            header.textContent = headerMap[status] || 'Created';
        }
        
        // Update header when tabs are clicked
        document.querySelectorAll('.tab-btn').forEach(tab => {
            tab.addEventListener('click', function() {
                updateDateColumnHeader();
            });
        });
        
        // Initialize header on page load
        updateDateColumnHeader();
    })();
    </script>
    <script>
    // Auto-open ticket modal when navigated from dashboard (?open=<id>)
    (function () {
        const params = new URLSearchParams(window.location.search);
        const openId = params.get('open');
        if (openId) {
            const cleanUrl = window.location.pathname + (params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') ? '?' + params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') : '');
            window.history.replaceState({}, document.title, cleanUrl);
            window.addEventListener('load', function () {
                setTimeout(function () {
                    if (typeof viewInstallation === 'function') {
                        viewInstallation(parseInt(openId, 10));
                    }
                }, 300);
            });
        }
    })();
    </script>
<script src="darkmode.js"></script>

</body>
</html>
<?php 

