<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'role_check.php';
require_once 'permission_helper.php';
requireAdminRole();
require_once 'config.php';

// Check if service_areas table exists
$success_message = '';
$error_message = '';
$table_check = $conn->query("SHOW TABLES LIKE 'service_areas'");
if ($table_check->num_rows == 0) {
    $error_message = 'Service Areas table not found. Please run <a href="setup_service_areas.php">setup_service_areas.php</a> first.';
    $result = null;
} else {
    // Fetch all service areas
    $sql = "SELECT id, purok_zone, barangay, city, province, zip_code, created_at 
            FROM service_areas 
            ORDER BY province, city, barangay, purok_zone";
    $result = $conn->query($sql);
    
    if (!$result) {
        $error_message = 'Database error: ' . $conn->error;
        $result = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Areas - UBILINK</title>
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
    <link rel="stylesheet" href="tickets.css?v=12" />
    <link rel="stylesheet" href="notification_bar.css?v=11" />
    <link rel="stylesheet" href="searchable-select.css" />
    
    
    
    
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
        <h1>Service Areas</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="actions-bar">
            <button class="btn-create-ticket" id="add-area-btn">+ Add Service Area</button>
            <button class="btn-import" id="import-excel-btn">📊 Import from Excel</button>
            <button class="btn-delete-selected" id="delete-selected-btn" style="display: none;">🗑️ Delete Selected</button>
        </div>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-areas"></th>
                        <th>ID</th>
                        <th>Purok/Zone</th>
                        <th>Barangay</th>
                        <th>City</th>
                        <th>Province</th>
                        <th>Zip Code</th>
                        <th>Full Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $full_address = trim(implode(', ', array_filter([
                                $row['purok_zone'],
                                $row['barangay'],
                                $row['city'],
                                $row['province'],
                                $row['zip_code']
                            ])));
                        ?>
                            <tr>
                                <td><input type="checkbox" class="row-checkbox" data-id="<?= $row['id'] ?>"></td>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['purok_zone']) ?></td>
                                <td><?= htmlspecialchars($row['barangay']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td><?= htmlspecialchars($row['province']) ?></td>
                                <td><?= htmlspecialchars($row['zip_code']) ?></td>
                                <td><?= htmlspecialchars($full_address) ?></td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="editArea(<?= $row['id'] ?>)">Edit</button>
                                    <button class="btn-delete" onclick="deleteArea(<?= $row['id'] ?>)">Delete</button>
                                   
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">No service areas found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Service Area Modal -->
    <div id="add-area-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-add-area">×</button>
            <h2>Add Service Area</h2>
            <form id="add-area-form" action="process_service_area.php" method="POST">
                <div class="form-group">
                    <label for="purok_zone">Purok/Zone <span class="required">*</span></label>
                    <input type="text" id="purok_zone" name="purok_zone" required>
                </div>

                <div class="form-group">
                    <label for="barangay">Barangay <span class="required">*</span></label>
                    <input type="text" id="barangay" name="barangay" required>
                </div>

                <div class="form-group">
                    <label for="city">City <span class="required">*</span></label>
                    <input type="text" id="city" name="city" required>
                </div>

                <div class="form-group">
                    <label for="province">Province <span class="required">*</span></label>
                    <input type="text" id="province" name="province" required>
                </div>

                <div class="form-group">
                    <label for="zip_code">Zip Code <span class="required">*</span></label>
                    <input type="text" id="zip_code" name="zip_code" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Add Service Area</button>
                    <button type="button" class="btn-cancel" id="cancel-add-area">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Service Area Modal -->
    <div id="edit-area-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-edit-area">×</button>
            <h2>Edit Service Area</h2>
            <form id="edit-area-form" action="update_service_area.php" method="POST">
                <input type="hidden" id="edit_area_id" name="area_id">
                
                <div class="form-group">
                    <label for="edit_purok_zone">Purok/Zone <span class="required">*</span></label>
                    <input type="text" id="edit_purok_zone" name="purok_zone" required>
                </div>

                <div class="form-group">
                    <label for="edit_barangay">Barangay <span class="required">*</span></label>
                    <input type="text" id="edit_barangay" name="barangay" required>
                </div>

                <div class="form-group">
                    <label for="edit_city">City <span class="required">*</span></label>
                    <input type="text" id="edit_city" name="city" required>
                </div>

                <div class="form-group">
                    <label for="edit_province">Province <span class="required">*</span></label>
                    <input type="text" id="edit_province" name="province" required>
                </div>

                <div class="form-group">
                    <label for="edit_zip_code">Zip Code <span class="required">*</span></label>
                    <input type="text" id="edit_zip_code" name="zip_code" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Update Service Area</button>
                    <button type="button" class="btn-cancel" id="cancel-edit-area">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div id="import-excel-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="close-import-excel">×</button>
            <h2>Import Service Areas from Excel</h2>
            
            <div class="import-instructions">
                <h3>Excel Format Instructions:</h3>
                <ul>
                    <li><strong>Column A:</strong> Purok/Zone</li>
                    <li><strong>Column B:</strong> Barangay</li>
                    <li><strong>Column C:</strong> City</li>
                    <li><strong>Column D:</strong> Province</li>
                    <li><strong>Column E:</strong> Zip Code</li>
                </ul>
                <p class="note">Note: First row will be skipped (assumed to be headers)</p>
                <p class="note">Tip: For best compatibility, save your Excel file as CSV format</p>
            </div>

            <form id="import-excel-form" action="import_service_areas.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="excel_file">Select File (.xlsx, .xls, .csv) <span class="required">*</span></label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Import</button>
                    <button type="button" class="btn-cancel" id="cancel-import-excel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="JavaScript.js"></script>
    <script src="dialog.js?v=1"></script>
    <script src="notification_bar.js?v=11"></script>
    <script src="db_events.js?v=1"></script>
    <script src="searchable-select.js"></script>
    <script src="service_areas.js"></script>
    <script src="bulk_actions.js?v=8"></script>
    <script src="permissions.js"></script>
    <script>
        function createPullOutFromArea(purokZone, barangay, city, province, zipCode) {
            // Store the address data in sessionStorage
            sessionStorage.setItem('pullout_address_data', JSON.stringify({
                purok_zone: purokZone,
                barangay: barangay,
                city: city,
                province: province,
                zip_code: zipCode
            }));
            
            // Redirect to Pull Out page
            window.location.href = 'tickets_pullout.php?from_service_area=1';
        }
    </script>
<script src="darkmode.js"></script>
</body>
</html>
