<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'role_check.php';
require_once 'permission_helper.php';
requireAdminRole();
require_once 'config.php';
require_once 'session_timeout.php';

$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_url = str_replace(' ', '%20', $base_url);
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Check if maintenances table exists
$table_check = $conn->query("SHOW TABLES LIKE 'maintenances'");
if ($table_check->num_rows == 0) {
    $error_message = 'Maintenances table not found. Please import <strong>maintenances.sql</strong> in phpMyAdmin first.';
    $result = null;
    $users = [];
} else {
    // Fetch users for assignment
    $users_sql = "SELECT userID, name, role FROM users ORDER BY name ASC";
    $users_result = $conn->query($users_sql);
    $users = [];
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
        #edit-maintenance-modal {
            z-index: 1100 !important;
        }
        #view-maintenance-modal {
            z-index: 1000 !important;
        }
        </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Tickets - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css?v=3" />
    <link rel="stylesheet" href="mobile.css?v=1" />
    <link rel="stylesheet" href="tickets.css?v=29" />
    <link rel="stylesheet" href="notification_bar.css?v=11" />
    <link rel="preload" href="eye2.png" as="image" />
    <script>window.BASE_URL = '<?= $base_url ?>';</script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  
    
    
    
    
</head>
<style>
  .dropdown-container { position: relative; width: 100%; margin: 10px; right: 10px;}
  
  /* Forces the list to stay BELOW the input */
  .dropdown-list {
    position: absolute;
    top: 100%; /* Positions it right at the bottom of the input */
    right: 0px;
    width: 100%;
    background: white;
    border: 5px solid #ccc;
    display: none; /* Hidden by default */
    list-style: none;
    padding: 0;
    margin: 5px 0 0 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 100;
  }

  .dropdown-list li { padding: 8px; cursor: pointer; }
  .dropdown-list li:hover { background-color: #f0f0f0; }
</style>

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

    <?php include 'sidebar.php'; ?>
    <div id="sidebarOverlay" class="sidebar-overlay" hidden></div>

    <main id="mainContent" class="main">
        <h1>Maintenance Tickets</h1>
        
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
            <button class="tab-btn" data-status="Completed">Completed</button>
            <button class="tab-btn" data-status="Cancelled">Cancelled</button>
            <button class="tab-btn" data-status="On Hold">On Hold</button>
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
            <button class="btn-create-ticket" id="create-maintenance-btn">+ Create Maintenance Ticket</button>
            <button class="btn-delete-selected" id="delete-selected-btn" style="display: none;">🗑️ Delete Selected</button>
        </div>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-maintenance"></th>
                        <th>Actions</th>
                        <th>Ticket #</th>
                        <th>Issue Type</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th>Description</th>
                        <th id="date-column-header">Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="10" class="no-data">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Create Maintenance Ticket Modal -->
    <div id="create-maintenance-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-create-maintenance">×</button>
            <h2>Create Maintenance Ticket</h2>
            <form id="create-maintenance-form" action="process_maintenance.php" method="POST">
                
                <div class="form-group">
                    <label for="maint_name">Client Name <span class="required">*</span></label>
                    <input type="text" id="maint_name" name="client_name" required>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <div class="address-cascade">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="maint_province">Province</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="maint_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="maint_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="maint_city">City</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="maint_city_input" type="text" placeholder="Select province first.." autocomplete="off" name="city" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="maint_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="maint_barangay">Barangay</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="maint_barangay_input" type="text" placeholder="Select city first.." autocomplete="off" name="barangay" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="maint_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="maint_purok_zone">Purok/Zone</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="maint_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="maint_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="maint_zip_code">Zip Code</label>
                                <input type="text" id="maint_zip_code" name="zip_code" readonly>
                            </div>
                        </div>
                        <!-- Hidden field for full address -->
                        <input type="hidden" id="maint_address" name="address">
                    </div>
                </div>

              <div class="form-group">
                    <label for="maint_number">Contact Number <span class="required">*</span></label>
                    <input type="tel" id="maint_number" name="contact_number" required>
                </div>

                <!--   <div class="form-group">
                    <label for="maint_email">Email</label>
                    <input type="email" id="maint_email" name="email">
                </div>

                <div class="form-group">
                    <label for="maint_account">Account Number</label>
                    <input type="text" id="maint_account" name="account_number">
                </div>-->

                <div class="form-group">
                    <label for="maint_issue_type">Issue Type <span class="required">*</span></label>
                    <select id="maint_issue_type" name="issue_type" required>
                        <option value="">--Select Issue Type--</option>
                        <option value="No Connection">No Connection</option>
                        <option value="Slow Speed">Slow Speed</option>
                        <option value="Intermittent Connection">Intermittent Connection</option>
                        <option value="Equipment Issue">Equipment Issue</option>
                        <option value="Line Cut">Line Cut</option>
                        <option value="Signal Weak">Signal Weak</option>
                        <option value="Server Upgrade">Server Upgrade</option>
                        <option value="Server Maintenance">Server Maintenance</option>
                        <option value="Request">Request</option>
                        <option value="Laser Out">Laser Out</option>
                        <option value="Low Optical Reading">Low Optical Reading</option>
                        <option value="Pull Out">Pull Out</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="maint_priority">Priority <span class="required">*</span></label>
                    <select id="maint_priority" name="priority" required>
                        <option value="">--Select Priority--</option>
                        <option value="Less Priority">Less Priority</option>
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="maint_description">Description <span class="required">*</span></label>
                    <textarea id="maint_description" name="description" rows="4" required placeholder="Describe the maintenance issue in detail..."></textarea>
                </div>

                <div class="form-group">
                    <label for="maint_scheduled_date">Scheduled Date</label>
                    <input type="date" id="maint_scheduled_date" name="scheduled_date">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-create-maintenance">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign User Modal -->
    <div id="assign-maintenance-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-assign-maintenance">×</button>
            <h2>Assign User</h2>
            <form id="assign-maintenance-form" action="assign_maintenance.php" method="POST">
                <input type="hidden" id="assign_maintenance_id" name="ticket_id">
                <p id="assign_maintenance_name" class="ticket-info"></p>
                
                <div class="form-group">
                    <label for="assigned_maintenance_user">Select User <span class="required">*</span></label>
                    <select id="assigned_maintenance_user" name="user_id" required>
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
                    <button type="button" class="btn-cancel" id="cancel-assign-maintenance">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Maintenance Modal -->
    <div id="view-maintenance-modal" class="modal">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-maintenance">×</button>
            <h2>Maintenance Ticket Details</h2>
            
            <!-- Action Buttons -->
            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-maintenance" onclick="editMaintenanceFromView()">Edit Maintenance Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToTechnicalDetailsMaintenance()">&#9881; Jump to Technical Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToImagesSection()">&#128247; Jump to Images</button>
            </div>
            
            <div id="view-maintenance-content">
                <!-- Ticket Information -->
                <div class="details-section">
                    <h3>Ticket Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Ticket Number:</label>
                            <span id="view-maintenance-ticket-number" class="ticket-number"></span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span id="view-maintenance-status"></span>
                        </div>
                        <div class="info-item">
                            <label>Priority:</label>
                            <span id="view-maintenance-priority"></span>
                        </div>
                        <div class="info-item">
                            <label>Issue Type:</label>
                            <span id="view-issue-type"></span>
                        </div>
                        <div class="info-item">
                            <label>Created:</label>
                            <span id="view-maintenance-created"></span>
                        </div>
                        <div class="info-item">
                            <label>Assigned To:</label>
                            <span id="view-maintenance-assigned"></span>
                        </div>
                    </div>
                </div>

                <!-- Client Information -->
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name:</label>
                            <span id="view-maintenance-client-name"></span>
                        </div>
                        <div class="info-item">
                            <label>Contact Number:</label>
                            <span id="view-maintenance-contact"></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span id="view-email"></span>
                        </div>
                        <div class="info-item">
                            <label>Account Number:</label>
                            <span id="view-account"></span>
                        </div>
                        <div class="info-item full-width">
                            <label>Address:</label>
                            <span id="view-maintenance-address"></span>
                        </div>
                    </div>
                </div>

                <!-- Issue Description -->
                <div class="details-section">
                    <h3>Issue Description</h3>
                    <div class="description-box" id="view-maintenance-description"></div>
                </div>

                <!-- Technician Notes -->
                <div class="details-section" id="technician-notes-section" style="display: none;">
                    <h3>Technician Notes</h3>
                    <div class="notes-box" id="view-technician-notes"></div>
                </div>

                <!-- Customer Feedback -->
                <div class="details-section" id="customer-feedback-section" style="display: none;">
                    <h3>Customer Feedback</h3>
                    <div class="feedback-box">
                        <div id="view-customer-feedback"></div>
                        <div class="rating" id="view-rating-section" style="display: none;">
                            Rating: <span id="view-rating-stars"></span>
                        </div>
                    </div>
                </div>

                <!-- Technical Details Form -->
                <div class="details-section" id="modal-technical-details-section">
                    <h3>Technical Details</h3>
                    <form id="modal-technical-form">
                        <input type="hidden" id="modal-tech-maintenance-id" name="maintenance_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="modal-service-type">Service Type</label>
                                <select id="modal-service-type" name="service_type">
                                    <option value="">--Select Service Type--</option>
                                    <option value="Client">Client</option>
                                    <option value="Distribution Line">Distribution Line</option>
                                    <option value="Main Line">Main Line</option>
                                    <option value="Transport Line">Transport Line</option>
                                    <option value="Server">Server</option>
                                    <option value="Tower">Tower</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="fiber-core-group" style="display: none;">
                                <label for="modal-fiber-core">Fiber Core Count</label>
                                <select id="modal-fiber-core" name="fiber_core_count">
                                    <option value="">--Select Core Count--</option>
                                    <option value="1 Core">1 Core</option>
                                    <option value="2 Core">2 Core</option>
                                    <option value="3 Core">3 Core</option>
                                    <option value="4 Core">4 Core</option>
                                    <option value="6 Core">6 Core</option>
                                    <option value="8 Core">8 Core</option>
                                    <option value="12 Core">12 Core</option>
                                    <option value="24 Core">24 Core</option>
                                    <option value="48 Core">48 Core</option>
                                    <option value="72 Core">72 Core</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="modal-optical">Optical Reading (dBm)</label>
                                <input type="text" id="modal-optical" name="optical_reading" placeholder="e.g., -15.5">
                            </div>
                            
                            <div class="form-group">
                                <label for="modal-speed">Speed Test (Mbps)</label>
                                <input type="text" id="modal-speed" name="speed_test" placeholder="e.g., 95.5">
                            </div>

                            <div class="form-group">
                                <label for="modal-ping">Ping (ms)</label>
                                <input type="text" id="modal-ping" name="ping" placeholder="e.g., 12">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="modal-work-done">Work Done</label>
                            <textarea id="modal-work-done" name="work_done" rows="3" placeholder="Describe the work performed..."></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="modal-problem-cause">Problem Cause</label>
                            <textarea id="modal-problem-cause" name="problem_cause" rows="3" placeholder="Describe the root cause..."></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="modal-comment">FINDINGS</label>
                            <div class="dropdown-container">
                            <input name="comment" type="text" id="status-input"  autocomplete="off" >
                                <ul id="modal-comment" class="dropdown-list">
                                <li value="Slow Internet / Intermittent">Slow Internet / Intermittent</li>
                                <li value="Poor Router Range">Poor Router Range</li>
                                <li value="Reset Router">Reset Router</li>
                                <li value="Fiber Cut Drop Wire">Fiber Cut Drop Wire</li>
                                <li value="Fiber Cut Main Line">Fiber Cut Main Line</li>
                                <li value="Loose FOC">Loose FOC</li>
                                <li value="Bent FOC">Bent FOC</li>
                                <li value="Faulty Router WL">Faulty Router WL</li>
                                <li value="Faulty Router Fiber">Faulty Router Fiber</li>
                                <li value="Faulty Antenna">Faulty Antenna</li>
                                <li value="Faulty Power Adaptor WL">Faulty Power Adaptor WL</li>
                                <li value="Faulty Power Adaptor Fiber">Faulty Power Adaptor Fiber</li>
                                <li value="Damaged Router WL">Damaged Router WL</li>
                                <li value="Damaged Router Fiber">Damaged Router Fiber</li>
                                <li value="Damaged Antenna">Damaged Antenna</li>
                                <li value="Damaged UTP cable">Damaged UTP cable</li>
                                <li value="Disaligned Antenna">Disaligned Antenna</li>
                                <li value="Obstructed Antenna">Obstructed Antenna</li>
                                <li value="Low Optical Reading NAP Box">Low Optical Reading NAP Box</li>
                                <li value="Low Optical Reading LCP">Low Optical Reading LCP</li>
                                <li value="SC Connector / Termination Problem">SC Connector / Termination Problem</li>
                                <li value="Brownout">Brownout</li>
                                <li value="Transfer Location">Transfer Location</li>
                                <li value="Relocate">Relocate</li>
                                <li value="Unknown">Unknown</li>
                                <li value="Configuration">Configuration</li>
                                <li value="Cancelled">Cancelled</li>
                                <li value="Negative">Negative</li>
                                <li value="Client's Request">Client's Request</li>
                                <li value="Expansion/ Server Upgrade/ New Server">Expansion/ Server Upgrade/ New Server</li>
                                <li value="Fusion/Splice">Fusion/Splice</li>
                                <li value="Server Down">Server Down</li>
                                <li value="Tower Down/AP/BH">Tower Down/AP/BH</li>
                                <li value="Fiber Cut Nap Box">Fiber Cut Nap Box</li>
                                <li value="Fiber Cut LCP">Fiber Cut LCP</li>
                                <li value="Short Range">Short Range</li>
                                <li value="OLT No Link/Problem">OLT No Link/Problem</li>
                                <li value="Reboot Server">Reboot Server</li>
                                <li value="Reboot Tower/AP/BH">Reboot Tower/AP/BH</li>
                                <li value="Reboot OLT">Reboot OLT</li>
                                <li value="Reboot Client">Reboot Client</li>
                                <li value="Client Fault">Client Fault</li>
                                <li value="Low Optical Reading NAP Box">Low Optical Reading NAP Box</li>
                                <li value="Low Optical Reading LCP">Low Optical Reading LCP</li>
                                <li value="Brownout">Brownout</li>
                                <li value="Transfer Location">Transfer Location</li>
                                <li value="Relocate">Relocate</li>
                                <li value="Unknown">Unknown</li>
                                <li value="Configuration">Configuration</li>
                                <li value="Cancelled">Cancelled</li>
                                <li value="Negative">Negative</li>
                                <li value="Client's Request">Client's Request</li>
                                <li value="Expansion/ Server Upgrade/ New Server">Expansion/ Server Upgrade/ New Server</li>
                                <li value="Fusion/Splice">Fusion/Splice</li>
                                <li value="Server Down">Server Down</li>
                                <li value="Tower Down/AP/BH">Tower Down/AP/BH</li>
                                <li value="Fiber Cut Nap Box">Fiber Cut Nap Box</li>
                                <li value="Fiber Cut LCP">Fiber Cut LCP</li>
                                <li value="Short Range">Short Range</li>
                                <li value="OLT No Link/Problem">OLT No Link/Problem</li>
                                <li value="Reboot Server">Reboot Server</li>
                                <li value="Reboot Tower/AP/BH">Reboot Tower/AP/BH</li>
                                <li value="Reboot OLT">Reboot OLT</li>
                                <li value="Reboot Client">Reboot Client</li>
                                <li value="Client Fault">Client Fault</li>
                        </ul>
                        </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="modal-accepts-member">Member</label>
                            <input type="text" id="modal-accepts-member" name="accepts_member" placeholder="Enter member name who accepted the work...">
                        </div>
                        <div class="form-actions">
                            <button type="submit" id="maint-save-tech-btn" class="btn-submit">Save Technical Details</button>
                        </div>
                    </form>

                    <!-- NOC Clear Section -->
                    <div class="clearance-section">
                        <h3>NOC Clearance</h3>
                        <div class="clearance-item">
                            <button type="button" class="btn-clear-noc" id="btn-modal-noc-clear" onclick="clearModalMaintenanceNOC(this)">NOC CLEAR</button>
                            <div id="modal-noc-clear-info" class="clearance-info"></div>
                        </div>
                    </div>
                </div>

                <!-- Images Section -->
                <div class="details-section" id="modal-images-section">
                    <h3>Images</h3>
                    
                    <!-- Upload Form -->
                    <form id="modal-upload-form" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" id="modal-maintenance-id" name="maintenance_id">
                        <div class="upload-area">
                            <input type="file" id="modal-image-upload" name="images[]" multiple accept="image/*">
                            <label for="modal-image-upload" class="upload-label">
                                <span class="upload-icon">📷</span>
                                <span>Click to select images</span>
                                <span class="upload-hint">You can select multiple images</span>
                            </label>
                        </div>
                        <div id="modal-preview-container" class="preview-container"></div>
                        <button type="submit" class="btn-upload" id="modal-upload-btn" style="display: none;">Upload Images</button>
                    </form>

                    <!-- Uploaded Images Gallery -->
                    <div id="modal-images-gallery" class="images-gallery"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="image-modal" class="image-modal" onclick="if(event.target===this)closeImageModal()">
        <span class="close-modal" onclick="event.stopPropagation();closeImageModal()">&times;</span>
        <button class="modal-nav-btn modal-prev-btn" onclick="event.stopPropagation();showPrevImage()">&#10094;</button>
        <img class="modal-image" id="modal-image" src="" style="cursor:zoom-in;transition:transform 0.1s;transform-origin:center center;">
        <button class="modal-nav-btn modal-next-btn" onclick="event.stopPropagation();showNextImage()">&#10095;</button>
    </div>

    <script src="JavaScript.js?v=7"></script>
    <script src="dialog.js?v=1"></script>
    <script src="notification_bar.js?v=11"></script>
    <script src="db_events.js?v=1"></script>
    <script src="tickets_maintenance.js?v=24"></script>
    <script src="bulk_actions.js?v=8"></script>
    <script src="permissions.js"></script>
    <script src="ticket_filters.js?v=8"></script>
    <script src="tickets_status_filter.js?v=7"></script>
    <script src="create_ticket_ajax.js?v=2"></script>
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Select2 Integration -->
    <script src="select2-integration.js"></script>
    <!-- Address Formatting -->
    <script src="format_address.js"></script>
    <script src="tickets_address_save_patch.js?v=3"></script>
    <script>
        // Role flags for SPA-rendered rows
        window._canAssign = <?= canAssignTickets() ? 'true' : 'false' ?>;
        window._canDelete = <?= canDelete() ? 'true' : 'false' ?>;
    </script>
    <script>
        // --- Custom Address Dropdowns for Maintenance Modal ---
        let maint_serviceAreas = [];
        let maint_provinces = [];
        let maint_cities = {};
        let maint_barangays = {};
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_service_areas_list.php?t=' + Date.now())
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Service areas loaded:', data);
                    if (data.success && data.areas && data.areas.length > 0) {
                        maint_serviceAreas = data.areas;
                        maint_provinces = [];
                        maint_cities = {};
                        maint_barangays = {};
                        data.areas.forEach(area => {
                            if (!maint_provinces.includes(area.province)) maint_provinces.push(area.province);
                            if (!maint_cities[area.province]) maint_cities[area.province] = [];
                            if (!maint_cities[area.province].includes(area.city)) maint_cities[area.province].push(area.city);
                            const cityKey = area.province + '|' + area.city;
                            if (!maint_barangays[cityKey]) maint_barangays[cityKey] = [];
                            if (!maint_barangays[cityKey].includes(area.barangay)) maint_barangays[cityKey].push(area.barangay);
                        });
                        // Expose globally so parseAndPopulateAddress (tickets.js) can use them
                        window.maint_serviceAreas = maint_serviceAreas;
                        window.maint_provinces    = maint_provinces;
                        window.maint_cities       = maint_cities;
                        window.maint_barangays    = maint_barangays;
                        window.ticket_serviceAreas = maint_serviceAreas;
                        window.ticket_provinces    = maint_provinces;
                        window.ticket_cities       = maint_cities;
                        window.ticket_barangays    = maint_barangays;
                        setupCustomDropdown('maint_province', maint_provinces, function(selectedProvince) {
                            const cityList = maint_cities[selectedProvince] || [];
                            setupCustomDropdown('maint_city', cityList, function(selectedCity) {
                                const cityKey = selectedProvince + '|' + selectedCity;
                                const barangayList = maint_barangays[cityKey] || [];
                                setupCustomDropdown('maint_barangay', barangayList, function(selectedBarangay) {
                                    // Auto-fill zip code from service area
                                    const area = maint_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('maint_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('maint_barangay_input').value = '';
                                document.getElementById('maint_zip_code').value = '';
                            });
                            document.getElementById('maint_city_input').value = '';
                            document.getElementById('maint_barangay_input').value = '';
                            document.getElementById('maint_zip_code').value = '';
                        });
                        // Regular Maintenance Purok dropdown
                        const maintPurokOptions = [
                            'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                            'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
                        ];
                        setupCustomDropdown('maint_purok_zone', maintPurokOptions);
                        // Edit Maintenance Modal Dropdowns
                        setupCustomDropdown('edit_maintenance_province', maint_provinces, function(selectedProvince) {
                const cityList = maint_cities[selectedProvince] || [];
                setupCustomDropdown('edit_maintenance_city', cityList, function(selectedCity) {
                    const cityKey = selectedProvince + '|' + selectedCity;
                    const barangayList = maint_barangays[cityKey] || [];
                    setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                        // Auto-fill zip code from service area
                        const area = maint_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                        document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                    });
                    document.getElementById('edit_maintenance_barangay_input').value = '';
                    document.getElementById('edit_maintenance_zip_code').value = '';
                });
                            document.getElementById('edit_maintenance_city_input').value = '';
                            document.getElementById('edit_maintenance_barangay_input').value = '';
                            document.getElementById('edit_maintenance_zip_code').value = '';
                        });
                        // Purok/Zone dropdown for edit modal
                        const purokOptions = [
                            'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                            'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
                        ];
                        setupCustomDropdown('edit_maintenance_purok_zone', purokOptions);
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
        }); // Close DOMContentLoaded
        // --- setupCustomDropdown function (copied from installation_form.php) ---
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
        // Address assembly handled by tickets_address_save_patch.js (which also handles duplicate detection)

        // Assign form via AJAX
        const assignMaintenanceForm = document.getElementById('assign-maintenance-form');
        if (assignMaintenanceForm) {
            assignMaintenanceForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('assign_maintenance.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) { alert('Assignment failed: ' + (data.message || '')); return; }
                        document.getElementById('assign-maintenance-modal').style.display = 'none';
                        if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
                    })
                    .catch(err => console.error('Assign error:', err));
            });
        }
    </script>

    <script>
    // ── Maintenance SPA Auto-Refresh ──────────────────────────────────────────
    (function () {
        const INTERVAL = 3000;
        let lastHash = null;

        function priorityClass(p) {
            if (p === 'Urgent') return 'priority-urgent';
            if (p === 'Normal') return 'priority-normal';
            return 'priority-low';
        }

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
            const completedStatuses = ['Completed', 'Closed', 'Cancelled', 'On Hold', 'In Progress'];
            if (!completedStatuses.includes(ticket.status)) {
                return { date: ticket.created_at, label: 'Created' };
            }
            // For maintenance tickets, always show status label, use specific time if available
            if (ticket.status === 'Completed') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Completed' };
            }
            if (ticket.status === 'Closed') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Closed' };
            }
            if (ticket.status === 'Cancelled') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Cancelled' };
            }
            if (ticket.status === 'In Progress') {
                return { date: ticket.in_progress_time || ticket.created_at, label: 'In Progress' };
            }
            if (ticket.status === 'On Hold') {
                return { date: ticket.created_at, label: 'On Hold' };
            }
            return { date: ticket.created_at, label: 'Created' };
        }

        function buildRow(t) {
            const assignedName = t.assigned_name || 'Unassigned';
            const displayInfo = getDisplayDate(t);
            const dateValue = formatDateTime(displayInfo.date);
            const desc = (t.description || '');
            const descShort = desc.length > 50 ? desc.substring(0, 50) + '...' : desc;

            let actionBtns = `<button class="btn-icon btn-view" onclick="viewMaintenanceDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
            if (window._canAssign) {
                actionBtns = `<button class="btn-icon btn-assign" onclick="openAssignMaintenanceModal(${t.id}, '${(t.client_name||'').replace(/'/g,"\\'")}')" title="Assign"></button>` + actionBtns;
            }
            if (window._canDelete) {
                actionBtns += `<button class="btn-icon btn-delete" onclick="deleteMaintenanceTicket(${t.id})" title="Delete">🗑️</button>`;
            }

            return `<tr data-status="${t.status}" data-priority="${t.priority}" data-type="maintenance" data-id="${t.id}">
                <td><input type="checkbox" class="row-checkbox" data-id="${t.id}"></td>
                <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">
                    ${actionBtns}
                </div></td>
                <td>${t.ticket_number || ''}</td>
                <td>${t.issue_type || ''}</td>
                <td>${t.client_name || ''}</td>
                <td>${t.address || ''}</td>
                <td>${t.contact_number || ''}</td>
                <td class="description-cell">${descShort}</td>
                <td class="created-cell"><small>${displayInfo.label}:</small><br>${dateValue}</td>
                <td>
                    <div class="details-column">
                        <div class="detail-item"><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span></div>
                        <div class="detail-item">
                            <select class="status-select" data-id="${t.id}" onchange="updateMaintenanceStatus(${t.id}, this.value)">
                                <option value="Pending" ${t.status==='Pending'?'selected':''}>Pending</option>
                                <option value="In Progress" ${t.status==='In Progress'?'selected':''}>In Progress</option>
                                <option value="Completed" ${t.status==='Completed'?'selected':''}>Completed</option>
                                <option value="Cancelled" ${t.status==='Cancelled'?'selected':''}>Cancelled</option>
                                <option value="On Hold" ${t.status==='On Hold'?'selected':''}>On Hold</option>
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
                const matchCity = !activeCity || (row.querySelector('td:nth-child(6)') || {}).textContent?.toLowerCase().includes(activeCity.toLowerCase());
                const matchSearch = !searchVal.trim() || row.textContent.toLowerCase().includes(searchVal.toLowerCase());
                row.style.display = (matchStatus && matchCity && matchSearch) ? '' : 'none';
            });
            if (typeof updateTabCounts === 'function') updateTabCounts();
        }

        function refresh(force) {
            if (!force && document.querySelector('.modal[style*="block"]')) return;

            fetch('get_maintenance_tickets.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const hash = data.tickets.map(t => t.id + t.status + t.priority + (t.assigned_to||'')).join('|');
                    if (!force && hash === lastHash) return;
                    lastHash = hash;

                    const tbody = document.querySelector('.tickets-table tbody');
                    if (!tbody) return;

                    tbody.innerHTML = data.tickets.length === 0
                        ? '<tr><td colspan="10" class="no-data">No maintenance tickets found</td></tr>'
                        : data.tickets.map(buildRow).join('');

                    applyFilters(tbody);

                    const selectAll = document.getElementById('select-all-maintenance');
                    if (selectAll) {
                        selectAll.checked = false;
                        selectAll.onchange = function() {
                            tbody.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
                            if (typeof updateBulkActionsBar === 'function') updateBulkActionsBar();
                        };
                    }
                    tbody.querySelectorAll('.row-checkbox').forEach(cb => {
                        cb.addEventListener('change', () => { if (typeof updateBulkActionsBar === 'function') updateBulkActionsBar(); });
                    });
                })
                .catch(() => {});
        }

        window.triggerMaintenanceRefresh = () => refresh(true);

        document.addEventListener('DOMContentLoaded', () => {
            refresh(true);
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
                    'Completed': 'Completed Time',
                    'Closed': 'Closed Time',
                    'Cancelled': 'Cancelled Time',
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
        });
    })();
    </script>
    <script>
    // Auto-open ticket modal when navigated from dashboard (?open=<id>)
    (function () {
        const params = new URLSearchParams(window.location.search);
        const openId = params.get('open');
        if (openId) {
            // Remove ?open= from URL so reload doesn't re-open the modal
            const cleanUrl = window.location.pathname + (params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') ? '?' + params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') : '');
            window.history.replaceState({}, document.title, cleanUrl);
            window.addEventListener('load', function () {
                setTimeout(function () {
                    if (typeof viewMaintenanceDetails === 'function') {
                        viewMaintenanceDetails(parseInt(openId, 10));
                    }
                }, 300);
            });
        }
    })();
    </script>
<script>
  const input = document.getElementById('status-input');
  const list = document.getElementById('modal-comment');
  const items = list.getElementsByTagName('li');

  // Show/Filter list on type
  input.addEventListener('input', () => {
    const filter = input.value.toLowerCase();
    list.style.display = 'block';
    
    Array.from(items).forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(filter) ? 'block' : 'none';
    });
  });

  // Select item on click
  list.addEventListener('click', (e) => {
    if (e.target.tagName === 'LI') {
      input.value = e.target.textContent;
      list.style.display = 'none';
    }
  });

  // Hide list when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown-container')) list.style.display = 'none';
  });

  // Force white eye icon on urgent rows
  function fixUrgentEyeIcons() {
    document.querySelectorAll('tr[data-priority="Urgent"] .btn-view img, tr[data-priority="urgent"] .btn-view img').forEach(img => {
      img.style.setProperty('filter', 'brightness(0) invert(1)', 'important');
      img.style.setProperty('-webkit-filter', 'brightness(0) invert(1)', 'important');
    });
  }
  // Run after table renders
  setTimeout(fixUrgentEyeIcons, 100);
  setTimeout(fixUrgentEyeIcons, 500);
  setTimeout(fixUrgentEyeIcons, 1000);
</script>

<!-- Edit Maintenance Ticket Modal -->
<div id="edit-maintenance-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <button class="close-btn" id="close-edit-maintenance">×</button>
        <h2>Edit Maintenance Ticket</h2>
        <form id="edit-maintenance-form" action="update_maintenance.php" method="POST">
            <input type="hidden" id="edit_maintenance_id" name="ticket_id">

            <div class="form-section">
                <h3>Client Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_maintenance_client_name">Client Name <span class="required">*</span></label>
                        <input type="text" id="edit_maintenance_client_name" name="client_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_contact_number">Contact Number</label>
                        <input type="tel" id="edit_maintenance_contact_number" name="contact_number">
                    </div>
                </div>
                <div class="form-group full-width">
                    <label>Address</label>
                    <div class="address-cascade">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Province</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_maintenance_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_maintenance_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_maintenance_city_input" type="text" placeholder="Select province first..." autocomplete="off" name="city" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_maintenance_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                           
                            <div class="form-group">
                                <label>Barangay</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_maintenance_barangay_input" type="text" placeholder="Select city first..." autocomplete="off" name="barangay" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_maintenance_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                             <div class="form-group">
                                <label>Purok</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_maintenance_purok_zone_input" type="text" placeholder="Purok" autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_maintenance_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Zip Code</label>
                                <input id="edit_maintenance_zip_code" type="text" placeholder="Zip code" autocomplete="off" name="zip_code">
                            </div>
                        </div>
                        <!-- Hidden field for full address -->
                        <input type="hidden" id="edit_maintenance_address" name="address">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Ticket Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_maintenance_issue_type">Issue Type <span class="required">*</span></label>
                        <select id="edit_maintenance_issue_type" name="issue_type" required>
                            <option value="">--Select Issue Type--</option>
                            <option value="No Connection">No Connection</option>
                            <option value="Slow Speed">Slow Speed</option>
                            <option value="Intermittent Connection">Intermittent Connection</option>
                            <option value="Equipment Issue">Equipment Issue</option>
                            <option value="Line Cut">Line Cut</option>
                            <option value="Signal Weak">Signal Weak</option>
                            <option value="Server Upgrade">Server Upgrade</option>
                            <option value="Server Maintenance">Server Maintenance</option>
                            <option value="Request">Request</option>
                            <option value="Laser Out">Laser Out</option>
                            <option value="Low Optical Reading">Low Optical Reading</option>
                            <option value="Pull Out">Pull Out</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_maintenance_priority">Priority <span class="required">*</span></label>
                        <select id="edit_maintenance_priority" name="priority" required>
                            <option value="">--Select Priority--</option>
                            <option value="Less Priority">Less Priority</option>
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group full-width">
                    <label for="edit_maintenance_issue">Issue Description</label>
                    <textarea id="edit_maintenance_issue" name="issue_description" rows="3"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Update Ticket</button>
                <button type="button" class="btn-cancel" id="cancel-edit-maintenance">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Edit maintenance ticket function
function editTicket(ticketId, type) {
    if (!ticketId) return;
    
    const ticketType = type || 'maintenance';
    
    // Fetch ticket data
    fetch('get_ticket_details.php?type=' + ticketType + '&id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ticket = data.ticket;
                
                if (ticketType === 'maintenance') {
                    // Populate maintenance form
                    document.getElementById('edit_maintenance_id').value = ticket.id;
                    document.getElementById('edit_maintenance_client_name').value = ticket.client_name || '';
                    document.getElementById('edit_maintenance_contact_number').value = ticket.contact_number || '';
                    
                    // Parse address using smart matching against service areas
                    if (ticket.address) {
                        const addressParts = ticket.address.split(',').map(p => p.trim()).filter(Boolean);
                        const sAreas = window.maint_serviceAreas || [];
                        const sCities = window.maint_cities || {};
                        const sBarangay = window.maint_barangays || {};
                        
                        let province = '', city = '', barangay = '', purok = '', zipCode = '';
                        
                        // Walk through known provinces/cities/barangays to find a match
                        const allProvinces = Object.keys(sCities);
                        for (const prov of allProvinces) {
                            if (addressParts.includes(prov)) {
                                province = prov;
                                const provCities = sCities[prov] || [];
                                for (const c of provCities) {
                                    if (addressParts.includes(c)) {
                                        city = c;
                                        const cityKey = prov + '|' + c;
                                        const cityBarangays = sBarangay[cityKey] || [];
                                        for (const b of cityBarangays) {
                                            if (addressParts.includes(b)) {
                                                barangay = b;
                                                break;
                                            }
                                        }
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                        
                        // Remaining parts that aren't province/city/barangay are purok and possibly zip
                        if (province || city || barangay) {
                            const known = [province, city, barangay].filter(Boolean);
                            const remaining = addressParts.filter(p => !known.includes(p));
                            const zipPart = remaining.find(p => /^\d{4,5}$/.test(p));
                            if (zipPart) zipCode = zipPart;
                            purok = remaining.filter(p => p !== zipPart).join(', ');
                        }
                        
                        document.getElementById('edit_maintenance_province_input').value = province;
                        document.getElementById('edit_maintenance_city_input').value = city;
                        document.getElementById('edit_maintenance_barangay_input').value = barangay;
                        document.getElementById('edit_maintenance_purok_zone_input').value = purok;
                        document.getElementById('edit_maintenance_zip_code').value = zipCode;
                        
                        // Setup cascading dropdowns for the pre-filled values
                        if (province && window.maint_cities) {
                            const cityList = window.maint_cities[province] || [];
                            const savedCity = city;
                            const savedBarangay = barangay;
                            setupCustomDropdown('edit_maintenance_city', cityList, function(selectedCity) {
                                const cityKey = province + '|' + selectedCity;
                                const barangayList = window.maint_barangays[cityKey] || [];
                                setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                                    const area = window.maint_serviceAreas.find(a => a.province === province && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('edit_maintenance_barangay_input').value = '';
                                document.getElementById('edit_maintenance_zip_code').value = '';
                            });
                            // Restore city value
                            document.getElementById('edit_maintenance_city_input').value = savedCity;
                            // If city is also set, setup barangay dropdown
                            if (city) {
                                const cityKey = province + '|' + city;
                                const barangayList = window.maint_barangays[cityKey] || [];
                                setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                                    const area = window.maint_serviceAreas.find(a => a.province === province && a.city === city && a.barangay === selectedBarangay);
                                    document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                                });
                                // Restore barangay value
                                document.getElementById('edit_maintenance_barangay_input').value = savedBarangay;
                            }
                        }
                    }
                    
                    document.getElementById('edit_maintenance_issue_type').value = ticket.issue_type || '';
                    document.getElementById('edit_maintenance_issue').value = ticket.description || '';
                    document.getElementById('edit_maintenance_priority').value = ticket.priority || '';
                    
                    // Show maintenance modal
                    document.getElementById('edit-maintenance-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            }
        })
        .catch(error => console.error('Error fetching ticket:', error));
}

// Handle edit maintenance form submission via AJAX
document.getElementById('edit-maintenance-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Combine address fields
    const fullAddress = getFullAddress('edit_maintenance_');
    document.getElementById('edit_maintenance_address').value = fullAddress;
    
    const formData = new FormData(this);
    const ticketId = document.getElementById('edit_maintenance_id').value;
    
    fetch('update_maintenance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close edit modal
            document.getElementById('edit-maintenance-modal').style.display = 'none';
            document.body.style.overflow = '';
            
            // Trigger refresh to update ticket data
            if (typeof window.triggerMaintenanceRefresh === 'function') {
                window.triggerMaintenanceRefresh();
            }
            
            // Open view modal
            if (typeof viewMaintenanceDetails === 'function') {
                viewMaintenanceDetails(parseInt(ticketId, 10));
            }
        } else {
            alert(data.message || 'Failed to update ticket');
        }
    })
    .catch(error => {
        console.error('Error updating ticket:', error);
        alert('Error updating ticket. Please try again.');
    });
});

// Close edit maintenance modal
document.getElementById('close-edit-maintenance').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-maintenance-modal').style.display = 'none';
    document.body.style.overflow = '';
});

document.getElementById('cancel-edit-maintenance').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-maintenance-modal').style.display = 'none';
    document.body.style.overflow = '';
});
</script>
<script src="darkmode.js"></script>
</body>
</html>
<?php 
?>
