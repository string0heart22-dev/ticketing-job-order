<?php
session_start();
require_once 'config.php';

$ticket_id = intval($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['error_message'] = 'Invalid ticket ID.';
    header('Location: tickets_maintenance.php');
    exit;
}

// Fetch maintenance ticket data
$sql = "SELECT * FROM maintenances WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = 'Maintenance ticket not found.';
    header('Location: tickets_maintenance.php');
    exit;
}

$ticket = $result->fetch_assoc();

// Get assigned user name
$assigned_name = 'Unassigned';
if ($ticket['assigned_to']) {
    $user_query = $conn->query("SELECT name FROM users WHERE userID = " . intval($ticket['assigned_to']));
    if ($user_query && $user_query->num_rows > 0) {
        $user_data = $user_query->fetch_assoc();
        $assigned_name = $user_data['name'];
    }
}

// Get uploaded images
$images_sql = "SELECT * FROM maintenance_images WHERE maintenance_id = ? ORDER BY uploaded_at DESC";
$images_stmt = $conn->prepare($images_sql);
if ($images_stmt) {
    $images_stmt->bind_param('i', $ticket_id);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    $images = [];
    while ($img = $images_result->fetch_assoc()) {
        $images[] = $img;
    }
    $images_stmt->close();
} else {
    $images = [];
}

$stmt->close();

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Maintenance Details - UBILINK</title>
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
    <link rel="stylesheet" href="view_maintenance.css" />
    
    
    
    
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
            <?php include 'presence_menu.php'; ?>
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

    <aside id="sidebar" class="sidebar collapsed" role="navigation" aria-label="Primary sidebar" aria-hidden="true">
        <nav>
            <ul>
                <li class="has-submenu" aria-haspopup="true">
                    <button class="submenu-toggle" aria-expanded="false" aria-controls="installationSubmenu">INSTALLATION FORM</button>
                    <ul id="installationSubmenu" class="submenu" hidden>
                        <li><a href="installation_form.php">New Client</a></li>
                        <li><a href="#install-schedule">Reconnect</a></li>
                        <li><a href="#install-history">History</a></li>
                    </ul>
                </li>

                <li class="has-submenu" aria-haspopup="true">
                    <button class="submenu-toggle" aria-expanded="false" aria-controls="ticketsSubmenu">TICKETS</button>
                    <ul id="ticketsSubmenu" class="submenu" hidden>
                        <li><a href="tickets.php">All Tickets</a></li>
                        <li><a href="tickets_installation.php">Installation</a></li>
                        <li><a href="tickets_maintenance.php">Maintenance</a></li>
                        <li><a href="tickets_pullout.php">Pull Out</a></li>
                    </ul>
                        <li><a href="#ticket-urgent">Urgent</a></li>
                        <li><a href="#ticket-reports">Pending</a></li>
                    </ul>
                </li>

                <li class="has-submenu" aria-haspopup="true">
                    <button class="submenu-toggle" aria-expanded="false" aria-controls="settingsSubmenu">SETTINGS</button>
                    <ul id="settingsSubmenu" class="submenu" hidden>
                        <li><a href="#setting1">INFO</a></li>
                        <li><a href="service_areas.php">Service Area</a></li>
                        <li><a href="service_plans.php">Internet Plans</a></li>
                        <li><a href="USERs.php">Users</a></li>
                        <li><a href="#setting5">Reports</a></li>
                        <li><a href="#setting6">Logs</a></li>
                    </ul>
                </li>

                <li class="logout">
                    <a onclick="window.location.href='logout.php'">LOG OUT</a>
                </li>
            </ul>
        </nav>
    </aside>

    <div id="sidebarOverlay" class="sidebar-overlay" hidden></div>

    <main id="mainContent" class="main">
        <div class="page-header">
            <h1>Maintenance Ticket Details</h1>
            <a href="tickets_maintenance.php" class="btn-back">← Back to Tickets</a>
            <button type="button" class="btn-jump-tech" onclick="scrollToTechnicalDetails()">⚙️ Jump to Technical Details</button>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="details-container">
            <!-- Ticket Information -->
            <div class="details-section">
                <h2>Ticket Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Ticket Number:</label>
                        <span class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status:</label>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>">
                            <?= htmlspecialchars($ticket['status']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Priority:</label>
                        <span class="priority-badge priority-<?= strtolower(str_replace(' ', '-', $ticket['priority'])) ?>">
                            <?= htmlspecialchars($ticket['priority']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Issue Type:</label>
                        <span><?= htmlspecialchars($ticket['issue_type']) ?></span>
                    </div>
                    <div class="info-item">
                        <label>Created:</label>
                        <span><?= date('M d, Y h:i A', strtotime($ticket['created_at'])) ?></span>
                    </div>
                    <div class="info-item">
                        <label>Assigned To:</label>
                        <span><?= htmlspecialchars($assigned_name) ?></span>
                    </div>
                </div>
            </div>

            <!-- Client Information -->
            <div class="details-section">
                <h2>Client Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?= htmlspecialchars($ticket['client_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <label>Contact Number:</label>
                        <span><?= htmlspecialchars($ticket['contact_number']) ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?= htmlspecialchars($ticket['email'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <label>Account Number:</label>
                        <span><?= htmlspecialchars($ticket['account_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item full-width">
                        <label>Address:</label>
                        <span><?= htmlspecialchars($ticket['address']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Issue Description -->
            <div class="details-section">
                <h2>Issue Description</h2>
                <div class="description-box">
                    <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                </div>
            </div>

            <!-- Technical Details Form -->
            <div class="details-section" id="technical-details-section">
                <h2>Technical Details</h2>
                <form id="maint-technical-form" class="technical-form">
                    <input type="hidden" name="maintenance_id" value="<?= $ticket_id ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maint-service-type">Service Type</label>
                            <select id="maint-service-type" name="service_type">
                                <option value="">--Select Service Type--</option>
                                <option value="Client" <?= ($ticket['service_type'] ?? '') == 'Client' ? 'selected' : '' ?>>Client</option>
                                <option value="Distribution Line" <?= ($ticket['service_type'] ?? '') == 'Distribution Line' ? 'selected' : '' ?>>Distribution Line</option>
                                <option value="Transport Line" <?= ($ticket['service_type'] ?? '') == 'Transport Line' ? 'selected' : '' ?>>Transport Line</option>
                                <option value="Server" <?= ($ticket['service_type'] ?? '') == 'Server' ? 'selected' : '' ?>>Server</option>
                                <option value="Tower" <?= ($ticket['service_type'] ?? '') == 'Tower' ? 'selected' : '' ?>>Tower</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="maint-optical">Optical Reading (dBm)</label>
                            <input type="text" id="maint-optical" name="optical_reading" value="<?= htmlspecialchars($ticket['optical_reading'] ?? '') ?>" placeholder="e.g., -15.5">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="maint-speed">Speed Test (Mbps)</label>
                            <input type="text" id="maint-speed" name="speed_test" value="<?= htmlspecialchars($ticket['speed_test'] ?? '') ?>" placeholder="e.g., 95.5">
                        </div>
                        
                        <div class="form-group">
                            <label for="maint-ping">Ping (ms)</label>
                            <input type="text" id="maint-ping" name="ping" value="<?= htmlspecialchars($ticket['ping'] ?? '') ?>" placeholder="e.g., 12">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="maint-work-done">Work Done</label>
                        <textarea id="maint-work-done" name="work_done" rows="3" placeholder="Describe the work performed..."><?= htmlspecialchars($ticket['work_done'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="maint-problem-cause">Problem Cause</label>
                        <textarea id="maint-problem-cause" name="problem_cause" rows="3" placeholder="Describe the root cause..."><?= htmlspecialchars($ticket['problem_cause'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="maint-comment">Comment</label>
                        <select id="maint-comment" name="comment">
                            <option value="">--Select Comment--</option>
                            <option value="Slow Internet / Intermittent" <?= ($ticket['comment'] ?? '') == 'Slow Internet / Intermittent' ? 'selected' : '' ?>>Slow Internet / Intermittent</option>
                            <option value="Poor Router Range" <?= ($ticket['comment'] ?? '') == 'Poor Router Range' ? 'selected' : '' ?>>Poor Router Range</option>
                            <option value="Reset Router" <?= ($ticket['comment'] ?? '') == 'Reset Router' ? 'selected' : '' ?>>Reset Router</option>
                            <option value="Fiber Cut Drop Wire" <?= ($ticket['comment'] ?? '') == 'Fiber Cut Drop Wire' ? 'selected' : '' ?>>Fiber Cut Drop Wire</option>
                            <option value="Fiber Cut Main Line" <?= ($ticket['comment'] ?? '') == 'Fiber Cut Main Line' ? 'selected' : '' ?>>Fiber Cut Main Line</option>
                            <option value="Loose FOC" <?= ($ticket['comment'] ?? '') == 'Loose FOC' ? 'selected' : '' ?>>Loose FOC</option>
                            <option value="Bent FOC" <?= ($ticket['comment'] ?? '') == 'Bent FOC' ? 'selected' : '' ?>>Bent FOC</option>
                            <option value="Faulty Router WL" <?= ($ticket['comment'] ?? '') == 'Faulty Router WL' ? 'selected' : '' ?>>Faulty Router WL</option>
                            <option value="Faulty Router Fiber" <?= ($ticket['comment'] ?? '') == 'Faulty Router Fiber' ? 'selected' : '' ?>>Faulty Router Fiber</option>
                            <option value="Faulty Antenna" <?= ($ticket['comment'] ?? '') == 'Faulty Antenna' ? 'selected' : '' ?>>Faulty Antenna</option>
                            <option value="Faulty Power Adaptor WL" <?= ($ticket['comment'] ?? '') == 'Faulty Power Adaptor WL' ? 'selected' : '' ?>>Faulty Power Adaptor WL</option>
                            <option value="Faulty Power Adaptor Fiber" <?= ($ticket['comment'] ?? '') == 'Faulty Power Adaptor Fiber' ? 'selected' : '' ?>>Faulty Power Adaptor Fiber</option>
                            <option value="Damaged Router WL" <?= ($ticket['comment'] ?? '') == 'Damaged Router WL' ? 'selected' : '' ?>>Damaged Router WL</option>
                            <option value="Damaged Router Fiber" <?= ($ticket['comment'] ?? '') == 'Damaged Router Fiber' ? 'selected' : '' ?>>Damaged Router Fiber</option>
                            <option value="Damaged Antenna" <?= ($ticket['comment'] ?? '') == 'Damaged Antenna' ? 'selected' : '' ?>>Damaged Antenna</option>
                            <option value="Damaged UTP cable" <?= ($ticket['comment'] ?? '') == 'Damaged UTP cable' ? 'selected' : '' ?>>Damaged UTP cable</option>
                            <option value="Disaligned Antenna" <?= ($ticket['comment'] ?? '') == 'Disaligned Antenna' ? 'selected' : '' ?>>Disaligned Antenna</option>
                            <option value="Obstructed Antenna" <?= ($ticket['comment'] ?? '') == 'Obstructed Antenna' ? 'selected' : '' ?>>Obstructed Antenna</option>
                            <option value="Low Optical Reading NAP Box" <?= ($ticket['comment'] ?? '') == 'Low Optical Reading NAP Box' ? 'selected' : '' ?>>Low Optical Reading NAP Box</option>
                            <option value="Low Optical Reading LCP" <?= ($ticket['comment'] ?? '') == 'Low Optical Reading LCP' ? 'selected' : '' ?>>Low Optical Reading LCP</option>
                            <option value="SC Connector / Termination Problem" <?= ($ticket['comment'] ?? '') == 'SC Connector / Termination Problem' ? 'selected' : '' ?>>SC Connector / Termination Problem</option>
                            <option value="Brownout" <?= ($ticket['comment'] ?? '') == 'Brownout' ? 'selected' : '' ?>>Brownout</option>
                            <option value="Transfer Location" <?= ($ticket['comment'] ?? '') == 'Transfer Location' ? 'selected' : '' ?>>Transfer Location</option>
                            <option value="Relocate" <?= ($ticket['comment'] ?? '') == 'Relocate' ? 'selected' : '' ?>>Relocate</option>
                            <option value="Unknown" <?= ($ticket['comment'] ?? '') == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                            <option value="Configuration" <?= ($ticket['comment'] ?? '') == 'Configuration' ? 'selected' : '' ?>>Configuration</option>
                            <option value="Cancelled" <?= ($ticket['comment'] ?? '') == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="Negative" <?= ($ticket['comment'] ?? '') == 'Negative' ? 'selected' : '' ?>>Negative</option>
                            <option value="Client's Request" <?= ($ticket['comment'] ?? '') == "Client's Request" ? 'selected' : '' ?>>Client's Request</option>
                            <option value="Expansion/ Server Upgrade/ New Server" <?= ($ticket['comment'] ?? '') == 'Expansion/ Server Upgrade/ New Server' ? 'selected' : '' ?>>Expansion/ Server Upgrade/ New Server</option>
                            <option value="Fusion/Splice" <?= ($ticket['comment'] ?? '') == 'Fusion/Splice' ? 'selected' : '' ?>>Fusion/Splice</option>
                            <option value="Server Down" <?= ($ticket['comment'] ?? '') == 'Server Down' ? 'selected' : '' ?>>Server Down</option>
                            <option value="Tower Down/AP/BH" <?= ($ticket['comment'] ?? '') == 'Tower Down/AP/BH' ? 'selected' : '' ?>>Tower Down/AP/BH</option>
                            <option value="Fiber Cut Nap Box" <?= ($ticket['comment'] ?? '') == 'Fiber Cut Nap Box' ? 'selected' : '' ?>>Fiber Cut Nap Box</option>
                            <option value="Fiber Cut LCP" <?= ($ticket['comment'] ?? '') == 'Fiber Cut LCP' ? 'selected' : '' ?>>Fiber Cut LCP</option>
                            <option value="Short Range" <?= ($ticket['comment'] ?? '') == 'Short Range' ? 'selected' : '' ?>>Short Range</option>
                            <option value="OLT No Link/Problem" <?= ($ticket['comment'] ?? '') == 'OLT No Link/Problem' ? 'selected' : '' ?>>OLT No Link/Problem</option>
                            <option value="Reboot Server" <?= ($ticket['comment'] ?? '') == 'Reboot Server' ? 'selected' : '' ?>>Reboot Server</option>
                            <option value="Reboot Tower/AP/BH" <?= ($ticket['comment'] ?? '') == 'Reboot Tower/AP/BH' ? 'selected' : '' ?>>Reboot Tower/AP/BH</option>
                            <option value="Reboot OLT" <?= ($ticket['comment'] ?? '') == 'Reboot OLT' ? 'selected' : '' ?>>Reboot OLT</option>
                            <option value="Reboot Client" <?= ($ticket['comment'] ?? '') == 'Reboot Client' ? 'selected' : '' ?>>Reboot Client</option>
                            <option value="Client Fault" <?= ($ticket['comment'] ?? '') == 'Client Fault' ? 'selected' : '' ?>>Client Fault</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">Save Technical Details</button>
                    </div>
                </form>

                <!-- NOC Clear Section -->
                <div class="clearance-section">
                    <h3>NOC Clearance</h3>
                    <div class="clearance-item">
                        <button type="button" class="btn-clear-noc" id="btn-maint-noc-clear" onclick="clearMaintenanceNOC(<?= $ticket_id ?>)">NOC CLEAR</button>
                        <div id="maint-noc-clear-info" class="clearance-info">
                            <?php if ($ticket['noc_cleared_at']): ?>
                                <?php
                                $noc_user_name = 'Unknown';
                                if ($ticket['noc_cleared_by']) {
                                    $noc_user_query = $conn->query("SELECT name FROM users WHERE userID = " . intval($ticket['noc_cleared_by']));
                                    if ($noc_user_query && $noc_user_query->num_rows > 0) {
                                        $noc_user_data = $noc_user_query->fetch_assoc();
                                        $noc_user_name = $noc_user_data['name'];
                                    }
                                }
                                ?>
                                <div class="cleared-status">
                                    <span class="cleared-icon">✓</span>
                                    <div>
                                        <div class="cleared-label">Cleared</div>
                                        <div class="cleared-time"><?= date('M d, Y h:i A', strtotime($ticket['noc_cleared_at'])) ?></div>
                                        <div class="cleared-by">By: <?= htmlspecialchars($noc_user_name) ?></div>
                                    </div>
                                </div>
                                <script>
                                    document.getElementById('btn-maint-noc-clear').disabled = true;
                                    document.getElementById('btn-maint-noc-clear').classList.add('disabled');
                                </script>
                            <?php else: ?>
                                <div class="not-cleared">Not cleared yet</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technician Notes -->
            <?php if ($ticket['technician_notes']): ?>
            <div class="details-section">
                <h2>Technician Notes</h2>
                <div class="notes-box">
                    <?= nl2br(htmlspecialchars($ticket['technician_notes'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customer Feedback -->
            <?php if ($ticket['customer_feedback']): ?>
            <div class="details-section">
                <h2>Customer Feedback</h2>
                <div class="feedback-box">
                    <?= nl2br(htmlspecialchars($ticket['customer_feedback'])) ?>
                    <?php if ($ticket['rating']): ?>
                        <div class="rating">
                            Rating: 
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $ticket['rating'] ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Images Section -->
            <div class="details-section">
                <h2>Images</h2>
                
                <!-- Upload Form -->
                <form id="upload-form" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="maintenance_id" value="<?= $ticket_id ?>">
                    <div class="upload-area">
                        <input type="file" id="image-upload" name="images[]" multiple accept="image/*" required>
                        <label for="image-upload" class="upload-label">
                            <span class="upload-icon">📷</span>
                            <span>Click to select images or drag and drop</span>
                            <span class="upload-hint">You can select multiple images</span>
                        </label>
                    </div>
                    <div id="preview-container" class="preview-container"></div>
                    <button type="submit" class="btn-upload" id="upload-btn" style="display: none;">Upload Images</button>
                </form>

                <!-- Uploaded Images Gallery -->
                <div class="images-gallery">
                    <?php if (count($images) > 0): ?>
                        <?php foreach ($images as $image): ?>
                            <div class="image-item" data-image-id="<?= $image['id'] ?>">
                                <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Maintenance Image" onclick="openImageModal('<?= htmlspecialchars($image['image_path']) ?>')">
                                <div class="image-info">
                                    <span class="image-date"><?= date('M d, Y', strtotime($image['uploaded_at'])) ?></span>
                                    <button class="btn-delete-image" onclick="deleteImage(<?= $image['id'] ?>)">×</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-images">No images uploaded yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="image-modal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-image" id="modal-image" src="">
    </div>

    <script src="JavaScript.js"></script>
    <script src="dialog.js?v=1"></script>
    <script src="view_maintenance.js"></script>
</body>
</html>
<?php $conn->close(); ?>
