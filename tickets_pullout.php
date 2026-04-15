<?php
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

// Check if pullout_tickets table exists
$table_check = $conn->query("SHOW TABLES LIKE 'pullout_tickets'");
if ($table_check->num_rows == 0) {
    $error_message = 'Pull Out Tickets table not found. <a href="create_pullout_database.php" style="color: #d60b0e; text-decoration: underline;">Click here to create it</a>';
    $result = null;
    $users = [];
} else {
    // Check if priority column exists
    $col_check = $conn->query("SHOW COLUMNS FROM pullout_tickets LIKE 'priority'");
    if ($col_check->num_rows == 0) {
        // Add priority column if it doesn't exist
        $conn->query("ALTER TABLE pullout_tickets ADD COLUMN priority enum('Less Priority','Normal','Urgent') NOT NULL DEFAULT 'Normal' AFTER status");
    }
    
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
        /* Edit modal should appear in front of view modal */
        #edit-pullout-modal {
            z-index: 1100 !important;
        }
        #view-pullout-modal {
            z-index: 1000 !important;
        }
        </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pull Out Tickets - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />
    <link rel="stylesheet" href="tickets.css?v=13" />
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

    <?php include 'sidebar.php'; ?>

    <div id="sidebarOverlay" class="sidebar-overlay" hidden></div>

    <main id="mainContent" class="main">
        <h1>Pull Out Tickets</h1>
        
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
            <button class="tab-btn" data-status="Closed">Closed</button>
            <button class="tab-btn" data-status="Negative">Negative</button>
            <button class="tab-btn" data-status="Reconnected">Reconnected</button>
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
            <button class="btn-create-ticket" id="create-pullout-btn">+ Create Pull Out Ticket</button>
            <button class="btn-delete-selected" id="delete-selected-btn" data-custom-handler="true" style="display: none;">🗑️ Delete Selected</button>
        </div>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-pullout"></th>
                        <th>Actions</th>
                        <th>Ticket #</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th>Reason</th>
                        <th id="date-column-header">Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="9" class="no-data">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Create Pull Out Ticket Modal -->
    <div id="create-pullout-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-create-pullout">×</button>
            <h2>Create Pull Out Ticket</h2>
            <form id="create-pullout-form" action="process_pullout.php" method="POST">
                
                <div class="form-group">
                    <label for="pullout_name">Client Name <span class="required">*</span></label>
                    <input type="text" id="pullout_name" name="client_name" required>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <div class="address-cascade">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pullout_province">Province</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="pullout_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="pullout_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="pullout_city">City</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="pullout_city_input" type="text" placeholder="Select province first.." autocomplete="off" name="city" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="pullout_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pullout_barangay">Barangay</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="pullout_barangay_input" type="text" placeholder="Select city first.." autocomplete="off" name="barangay" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="pullout_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="pullout_purok_zone">Purok/Zone</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="pullout_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="pullout_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pullout_zip_code">Zip Code</label>
                                <input type="text" id="pullout_zip_code" name="zip_code" readonly>
                            </div>
                        </div>
                        <!-- Hidden field for full address -->
                        <input type="hidden" id="pullout_address" name="address">
                    </div>
                </div>

                <div class="form-group">
                    <label for="pullout_number">Contact Number</label>
                    <input type="tel" id="pullout_number" name="contact_number">
                </div>

                <div class="form-group">
                    <label for="pullout_priority">Priority <span class="required">*</span></label>
                    <select id="pullout_priority" name="priority" required>
                        <option value="">--Select Priority--</option>
                        <option value="Less Priority">Less Priority</option>
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pullout_reason">Reason <span class="required">*</span></label>
                    <textarea id="pullout_reason" name="reason" rows="4" required placeholder="Describe the pull out reason in detail..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-create-pullout">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign User Modal -->
    <div id="assign-pullout-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-assign-pullout">×</button>
            <h2>Assign User</h2>
            <form id="assign-pullout-form" action="assign_pullout.php" method="POST">
                <input type="hidden" id="assign_pullout_id" name="ticket_id">
                <p id="assign_pullout_name" class="ticket-info"></p>
                
                <div class="form-group">
                    <label for="assigned_pullout_user">Select User <span class="required">*</span></label>
                    <select id="assigned_pullout_user" name="user_id" required>
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
                    <button type="button" class="btn-cancel" id="cancel-assign-pullout">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Pull Out Modal -->
    <div id="view-pullout-modal" class="modal">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-pullout">×</button>
            <h2>Pull Out Ticket Details</h2>

            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-pullout" onclick="editPulloutFromView()">Edit Pull Out Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToPulloutSection('pullout-noc-section')">⚙️ Jump to NOC Clearance</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToPulloutSection('pullout-images-section')">📷 Jump to Images</button>
            </div>
            
            <div id="view-pullout-content">
                <!-- Ticket Information -->
                <div class="details-section">
                    <h3>Ticket Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Ticket Number:</label>
                            <span id="view-pullout-ticket-number" class="ticket-number"></span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span id="view-pullout-status"></span>
                        </div>
                        <div class="info-item">
                            <label>Priority:</label>
                            <span id="view-pullout-priority"></span>
                        </div>
                        <div class="info-item">
                            <label>Created:</label>
                            <span id="view-pullout-created"></span>
                        </div>
                        <div class="info-item">
                            <label>Assigned To:</label>
                            <span id="view-pullout-assigned"></span>
                        </div>
                    </div>
                </div>

                <!-- Client Information -->
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name:</label>
                            <span id="view-pullout-client-name"></span>
                        </div>
                        <div class="info-item">
                            <label>Contact Number:</label>
                            <span id="view-pullout-contact"></span>
                        </div>
                        <div class="info-item full-width">
                            <label>Address:</label>
                            <span id="view-pullout-address"></span>
                        </div>
                    </div>
                </div>

                <!-- Pull Out Reason -->
                <div class="details-section">
                    <h3>Pull Out Reason</h3>
                    <div class="description-box" id="view-pullout-reason"></div>
                </div>

                <!-- Status Update Form -->
                <div class="details-section">
                    <h3>Update Status</h3>
                    <form id="modal-status-form">
                        <input type="hidden" id="modal-pullout-id" name="pullout_id">
                        
                        <div class="form-group">
                            <label for="modal-status">Status</label>
                            <select id="modal-status" name="status" onchange="updatePulloutStatus(document.getElementById('modal-pullout-id').value, this.value)">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Closed">Closed</option>
                                <option value="Negative">Negative</option>
                                <option value="Reconnected">Reconnected</option>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- NOC Clear Section -->
                <div class="details-section" id="pullout-noc-section">
                    <div class="clearance-section">
                        <h3>NOC Clearance</h3>
                        <div class="clearance-item">
                            <button type="button" class="btn-clear-noc" id="btn-modal-noc-clear" onclick="clearModalPulloutNOC(this)">NOC CLEAR</button>
                            <div id="modal-noc-clear-info" class="clearance-info"></div>
                        </div>
                    </div>
                </div>

                <!-- Image Upload Section -->
                <div class="details-section" id="pullout-images-section">
                    <h3>Upload Images</h3>
                    <form id="modal-upload-form" enctype="multipart/form-data">
                        <input type="hidden" id="modal-pullout-upload-id" name="pullout_id">
                        <div class="upload-area" onclick="document.getElementById('modal-image-upload').click()">
                            <input type="file" id="modal-image-upload" name="images[]" multiple accept="image/*" style="display:none;">
                            <label class="upload-label" style="pointer-events:none;">
                                <span class="upload-icon">📷</span>
                                <span>Click to select images</span>
                                <span class="upload-hint">You can select multiple images</span>
                            </label>
                        </div>
                        <div id="modal-preview-container" class="preview-container"></div>
                        <button type="submit" class="btn-upload" id="modal-upload-btn" style="display: none;">Upload Images</button>
                    </form>

                    <!-- Uploaded Images Gallery -->
                    <div id="modal-pullout-images-gallery" class="images-gallery"></div>
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
    <script src="tickets_pullout.js?v=33"></script>
    <script src="bulk_actions.js?v=8"></script>
    <script src="permissions.js"></script>
    <script src="ticket_filters.js?v=8"></script>
    <script src="tickets_status_filter.js?v=7"></script>
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
        // --- Custom Address Dropdowns for Pullout Modal ---
        let pullout_serviceAreas = [];
        let pullout_provinces = [];
        let pullout_cities = {};
        let pullout_barangays = {};
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_service_areas_list.php?t=' + Date.now())
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Service areas loaded:', data);
                    if (data.success && data.areas && data.areas.length > 0) {
                        pullout_serviceAreas = data.areas;
                        pullout_provinces = [];
                        pullout_cities = {};
                        pullout_barangays = {};
                        data.areas.forEach(area => {
                            if (!pullout_provinces.includes(area.province)) pullout_provinces.push(area.province);
                            if (!pullout_cities[area.province]) pullout_cities[area.province] = [];
                            if (!pullout_cities[area.province].includes(area.city)) pullout_cities[area.province].push(area.city);
                            const cityKey = area.province + '|' + area.city;
                            if (!pullout_barangays[cityKey]) pullout_barangays[cityKey] = [];
                            if (!pullout_barangays[cityKey].includes(area.barangay)) pullout_barangays[cityKey].push(area.barangay);
                        });
                        // Expose globally so parseAndPopulateAddress (tickets.js) can use them
                        window.pullout_serviceAreas = pullout_serviceAreas;
                        window.pullout_provinces    = pullout_provinces;
                        window.pullout_cities       = pullout_cities;
                        window.pullout_barangays    = pullout_barangays;
                        window.ticket_serviceAreas = pullout_serviceAreas;
                        window.ticket_provinces    = pullout_provinces;
                        window.ticket_cities       = pullout_cities;
                        window.ticket_barangays    = pullout_barangays;
                        setupCustomDropdown('pullout_province', pullout_provinces, function(selectedProvince) {
                            const cityList = pullout_cities[selectedProvince] || [];
                            setupCustomDropdown('pullout_city', cityList, function(selectedCity) {
                                const cityKey = selectedProvince + '|' + selectedCity;
                                const barangayList = pullout_barangays[cityKey] || [];
                                setupCustomDropdown('pullout_barangay', barangayList, function(selectedBarangay) {
                                    // Auto-fill zip code from service area
                                    const area = pullout_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('pullout_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('pullout_barangay_input').value = '';
                                document.getElementById('pullout_zip_code').value = '';
                            });
                            document.getElementById('pullout_city_input').value = '';
                            document.getElementById('pullout_barangay_input').value = '';
                            document.getElementById('pullout_zip_code').value = '';
                        });
                        // Purok/Zone dropdown for create modal
                        const pulloutPurokOptions = [
                            'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                            'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
                        ];
                        setupCustomDropdown('pullout_purok_zone', pulloutPurokOptions);
                        // Edit Pullout Modal Dropdowns
                        setupCustomDropdown('edit_pullout_province', pullout_provinces, function(selectedProvince) {
                const cityList = pullout_cities[selectedProvince] || [];
                setupCustomDropdown('edit_pullout_city', cityList, function(selectedCity) {
                    const cityKey = selectedProvince + '|' + selectedCity;
                    const barangayList = pullout_barangays[cityKey] || [];
                    setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                        // Auto-fill zip code from service area
                        const area = pullout_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                        document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                    });
                    document.getElementById('edit_pullout_barangay_input').value = '';
                    document.getElementById('edit_pullout_zip_code').value = '';
                });
                            document.getElementById('edit_pullout_city_input').value = '';
                            document.getElementById('edit_pullout_barangay_input').value = '';
                            document.getElementById('edit_pullout_zip_code').value = '';
                        });
                        // Edit pullout purok dropdown
                        const editPulloutPurokOptions = [
                            'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                            'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
                        ];
                        setupCustomDropdown('edit_pullout_purok_zone', editPulloutPurokOptions);
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
    // ── Pullout SPA Auto-Refresh ──────────────────────────────────────────────
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
            const completedStatuses = ['Completed', 'Closed', 'Cancelled', 'On Hold', 'Negative', 'Reconnected', 'In Progress'];
            if (!completedStatuses.includes(ticket.status)) {
                return { date: ticket.created_at, label: 'Created' };
            }
            // For pullout tickets, always show status label, use specific time if available
            if (ticket.status === 'Closed') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Closed' };
            }
            if (ticket.status === 'Cancelled') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Cancelled' };
            }
            if (ticket.status === 'Negative') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Negative' };
            }
            if (ticket.status === 'Reconnected') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Reconnected' };
            }
            if (ticket.status === 'In Progress') {
                return { date: ticket.in_progress_time || ticket.created_at, label: 'In Progress' };
            }
            if (ticket.status === 'On Hold') {
                return { date: ticket.created_at, label: 'On Hold' };
            }
            if (ticket.status === 'Completed') {
                return { date: ticket.completed_time || ticket.created_at, label: 'Completed' };
            }
            return { date: ticket.created_at, label: 'Created' };
        }

        function buildRow(t) {
            const assignedName = t.assigned_name || 'Unassigned';
            const displayInfo = getDisplayDate(t);
            const dateValue = formatDateTime(displayInfo.date);

            const reason = (t.reason || '');
            const reasonShort = reason.length > 50 ? reason.substring(0, 50) + '...' : reason;

            const canAssign = window._canAssign !== false;
            const canDelete = window._canDelete !== false;

            let actionBtns = `<button class="btn-icon btn-view" onclick="viewPulloutDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
            if (canAssign) {
                actionBtns = '<button class="btn-icon btn-assign" onclick="openAssignPulloutModal(' + t.id + ', \'' + (t.client_name||'').replace(/'/g,"\\'") + '\')" title="Assign"></button>' + actionBtns;
            }
            if (canDelete) {
                actionBtns += `<button class="btn-icon btn-delete" onclick="deletePulloutTicket(${t.id})" title="Delete">🗑️</button>`;
            }

            return `<tr data-status="${t.status}" data-priority="${t.priority}" data-type="pullout" data-id="${t.id}">
                <td><input type="checkbox" class="row-checkbox" data-id="${t.id}"></td>
                <td class="td-actions" onclick="event.stopPropagation()"><div class="action-buttons">${actionBtns}</div></td>
                <td>${t.ticket_number || ''}</td>
                <td>${t.client_name || ''}</td>
                <td>${t.address || ''}</td>
                <td>${t.contact_number || ''}</td>
                <td class="description-cell">${reasonShort}</td>
                <td class="created-cell"><small>${displayInfo.label}:</small><br>${dateValue}</td>
                <td>
                    <div class="details-column">
                        <div class="detail-item"><span class="priority-badge ${priorityClass(t.priority)}">${t.priority}</span></div>
                        <div class="detail-item">
                            <select class="status-select" data-id="${t.id}" onchange="updatePulloutStatus(${t.id}, this.value)">
                                <option value="Pending" ${t.status==='Pending'?'selected':''}>Pending</option>
                                <option value="In Progress" ${t.status==='In Progress'?'selected':''}>In Progress</option>
                                <option value="Closed" ${t.status==='Closed'?'selected':''}>Closed</option>
                                <option value="Negative" ${t.status==='Negative'?'selected':''}>Negative</option>
                                <option value="Reconnected" ${t.status==='Reconnected'?'selected':''}>Reconnected</option>
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

            fetch('get_pullout_tickets.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const hash = data.tickets.map(t => t.id + t.status + t.priority + (t.assigned_to||'')).join('|');
                    if (!force && hash === lastHash) return;
                    lastHash = hash;

                    const tbody = document.querySelector('.tickets-table tbody');
                    if (!tbody) return;

                    tbody.innerHTML = data.tickets.length === 0
                        ? '<tr><td colspan="9" class="no-data">No pull out tickets found</td></tr>'
                        : data.tickets.map(buildRow).join('');

                    applyFilters(tbody);

                    // Re-attach select-all checkbox
                    const selectAll = document.getElementById('select-all-pullout');
                    if (selectAll) {
                        selectAll.checked = false;
                        selectAll.onchange = function() {
                            tbody.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
                            updateBulkActionsBar();
                        };
                    }
                    tbody.querySelectorAll('.row-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateBulkActionsBar);
                    });
                })
                .catch((err) => {
                    console.error('Error fetching pullout tickets:', err);
                    const tbody = document.querySelector('.tickets-table tbody');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="9" class="no-data">Error loading tickets. Check console.</td></tr>';
                    }
                });
        }

        window.triggerPulloutRefresh = () => refresh(true);

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
                    'Closed': 'Closed Time',
                    'Negative': 'Negative Time',
                    'Reconnected': 'Reconnected Time',
                    'On Hold': 'On Hold Time',
                    'Completed': 'Completed Time',
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
            const cleanUrl = window.location.pathname + (params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') ? '?' + params.toString().replace(/open=[^&]*&?/, '').replace(/&$/, '') : '');
            window.history.replaceState({}, document.title, cleanUrl);
            window.addEventListener('load', function () {
                setTimeout(function () {
                    if (typeof viewPulloutDetails === 'function') {
                        viewPulloutDetails(parseInt(openId, 10));
                    }
                }, 300);
            });
        }
    })();
    </script>

<!-- Edit Pull Out Ticket Modal -->
<div id="edit-pullout-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <button class="close-btn" id="close-edit-pullout">×</button>
        <h2>Edit Pull Out Ticket</h2>
        <form id="edit-pullout-form" action="update_pullout.php" method="POST">
            <input type="hidden" id="edit_pullout_id" name="ticket_id">

            <div class="form-section">
                <h3>Client Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_pullout_client_name">Client Name <span class="required">*</span></label>
                        <input type="text" id="edit_pullout_client_name" name="client_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_pullout_contact_number">Contact Number</label>
                        <input type="tel" id="edit_pullout_contact_number" name="contact_number">
                    </div>
                </div>
                <div class="form-group full-width">
                    <label>Address</label>
                    <div class="address-cascade">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Province</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_pullout_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_pullout_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_pullout_city_input" type="text" placeholder="Select province first..." autocomplete="off" name="city" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_pullout_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Barangay</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_pullout_barangay_input" type="text" placeholder="Select city first..." autocomplete="off" name="barangay" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_pullout_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Purok</label>
                                <div class="input-dropdown-group" style="position:relative;">
                                    <input id="edit_pullout_purok_zone_input" type="text" placeholder="Purok" autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                    <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                    <ul id="edit_pullout_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                </div>
                            </div>
                            
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Zip Code</label>
                                <input id="edit_pullout_zip_code" type="text" placeholder="Zip code" autocomplete="off" name="zip_code">
                            </div>
                        </div>
                        <!-- Hidden field for full address -->
                        <input type="hidden" id="edit_pullout_address" name="address">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Ticket Details</h3>
                <div class="form-group full-width">
                    <label for="edit_pullout_reason">Reason for Pull Out</label>
                    <textarea id="edit_pullout_reason" name="reason" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_pullout_priority">Priority <span class="required">*</span></label>
                        <select id="edit_pullout_priority" name="priority" required>
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
                <button type="button" class="btn-cancel" id="cancel-edit-pullout">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Edit pull out ticket function
function editTicket(ticketId, type) {
    if (!ticketId) return;
    
    const ticketType = type || 'pullout';
    
    // Fetch ticket data
    fetch('get_ticket_details.php?type=' + ticketType + '&id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ticket = data.ticket;
                
                if (ticketType === 'pullout') {
                    // Populate pull out form
                    document.getElementById('edit_pullout_id').value = ticket.id;
                    document.getElementById('edit_pullout_client_name').value = ticket.client_name || '';
                    document.getElementById('edit_pullout_contact_number').value = ticket.contact_number || '';
                    
                    // Parse address using smart matching against service areas
                    if (ticket.address) {
                        const addressParts = ticket.address.split(',').map(p => p.trim()).filter(Boolean);
                        const sAreas = window.pullout_serviceAreas || [];
                        const sCities = window.pullout_cities || {};
                        const sBarangay = window.pullout_barangays || {};
                        
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
                        
                        document.getElementById('edit_pullout_province_input').value = province;
                        document.getElementById('edit_pullout_city_input').value = city;
                        document.getElementById('edit_pullout_barangay_input').value = barangay;
                        document.getElementById('edit_pullout_purok_zone_input').value = purok;
                        document.getElementById('edit_pullout_zip_code').value = zipCode;
                        
                        // Setup cascading dropdowns for the pre-filled values
                        if (province && window.pullout_cities) {
                            const cityList = window.pullout_cities[province] || [];
                            const savedCity = city;
                            const savedBarangay = barangay;
                            setupCustomDropdown('edit_pullout_city', cityList, function(selectedCity) {
                                const cityKey = province + '|' + selectedCity;
                                const barangayList = window.pullout_barangays[cityKey] || [];
                                setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                                    const area = window.pullout_serviceAreas.find(a => a.province === province && a.city === selectedCity && a.barangay === selectedBarangay);
                                    document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                                });
                                document.getElementById('edit_pullout_barangay_input').value = '';
                                document.getElementById('edit_pullout_zip_code').value = '';
                            });
                            // Restore city value
                            document.getElementById('edit_pullout_city_input').value = savedCity;
                            // If city is also set, setup barangay dropdown
                            if (city) {
                                const cityKey = province + '|' + city;
                                const barangayList = window.pullout_barangays[cityKey] || [];
                                setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                                    const area = window.pullout_serviceAreas.find(a => a.province === province && a.city === city && a.barangay === selectedBarangay);
                                    document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                                });
                                // Restore barangay value
                                document.getElementById('edit_pullout_barangay_input').value = savedBarangay;
                            }
                        }
                    }
                    
                    document.getElementById('edit_pullout_reason').value = ticket.reason || '';
                    document.getElementById('edit_pullout_priority').value = ticket.priority || '';
                    
                    // Show pull out modal
                    document.getElementById('edit-pullout-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            }
        })
        .catch(error => console.error('Error fetching ticket:', error));
}

// Handle edit pullout form submission via AJAX
document.getElementById('edit-pullout-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Combine address fields
    const fullAddress = getFullAddress('edit_pullout_');
    document.getElementById('edit_pullout_address').value = fullAddress;
    
    const formData = new FormData(this);
    const ticketId = document.getElementById('edit_pullout_id').value;
    
    fetch('update_pullout.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close edit modal
            document.getElementById('edit-pullout-modal').style.display = 'none';
            document.body.style.overflow = '';
            
            // Trigger refresh to update ticket data
            if (typeof window.triggerPulloutRefresh === 'function') {
                window.triggerPulloutRefresh();
            }
            
            // Open view modal
            if (typeof viewPulloutDetails === 'function') {
                viewPulloutDetails(parseInt(ticketId, 10));
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

// Close edit pull out modal
document.getElementById('close-edit-pullout').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-pullout-modal').style.display = 'none';
    document.body.style.overflow = '';
});

document.getElementById('cancel-edit-pullout').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-pullout-modal').style.display = 'none';
    document.body.style.overflow = '';
});
</script>
<script src="darkmode.js"></script>

</body>
</html>
<?php 
?>
