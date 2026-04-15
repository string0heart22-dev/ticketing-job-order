<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'role_check.php';
require_once 'permission_helper.php';
requireAdminRole();
require_once 'config.php';

// Check page access - hide page if no permission
checkPageAccess();

$users_sql = "SELECT userID, name FROM users ORDER BY name ASC";
$users_result = $conn->query($users_sql);
$users = [];
if ($users_result) {
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
    $users_result->free();
}

// Fetch other cities not in the Main group
$main_city_list = ['Echague','Jones','Santiago','Cauayan','Diffun','San Agustin','Angadanan','San Isidro'];
$escaped = implode("','", array_map(fn($c) => $conn->real_escape_string($c), $main_city_list));
$cities_sql = "SELECT DISTINCT city FROM service_areas WHERE city NOT IN ('$escaped') ORDER BY city ASC";
$cities_result = $conn->query($cities_sql);
$other_cities = [];
if ($cities_result) {
    while ($c = $cities_result->fetch_assoc()) {
        $other_cities[] = $c['city'];
    }
    $cities_result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />    <link rel="stylesheet" href="tickets.css?v=12" />
    <link rel="stylesheet" href="reports.css?v=2" />
    <link rel="stylesheet" href="notification_bar.css?v=11" />
</head>
<body>
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

    <main id="mainContent" class="main">
        <h1>📊 Reports</h1>
        
        <div class="report-container">
            <h2>Generate Report</h2>
            
            <form id="report-form" method="POST" action="generate_report.php">
                <div class="report-filters">
                    <div class="filter-group">
                        <label for="date_from">From Date <span class="required">*</span></label>
                        <input type="date" id="date_from" name="date_from" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To Date <span class="required">*</span></label>
                        <input type="date" id="date_to" name="date_to" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="ticket_type">Ticket Type <span class="required">*</span></label>
                        <select id="ticket_type" name="ticket_type" required>
                            <option value="all">All Tickets</option>
                            <option value="installation">Installation</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="pullout">Pull Out</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status_filter">Filter by Status</label>
                        <select id="status_filter" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Installed">Installed</option>
                            <option value="Closed">Closed</option>
                            <option value="installed_completed_closed">Installed / Completed / Closed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Negative">Negative</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="city_filter">Filter by City</label>
                        <select id="city_filter" name="city_filter">
                            <option value="">All Cities</option>
                            <option value="main_group">— Main (All) —</option>
                            <optgroup label="Main">
                                <option value="Echague">Echague</option>
                                <option value="Jones">Jones</option>
                                <option value="Santiago">Santiago</option>
                                <option value="Cauayan">Cauayan</option>
                                <option value="Diffun">Diffun</option>
                                <option value="San Agustin">San Agustin</option>
                                <option value="Angadanan">Angadanan</option>
                                <option value="San Isidro">San Isidro</option>
                            </optgroup>
                            <?php if (!empty($other_cities)): ?>
                            <optgroup label="Other">
                                <?php foreach ($other_cities as $oc): ?>
                                    <option value="<?= htmlspecialchars($oc) ?>"><?= htmlspecialchars($oc) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="user_filter">Filter by User</label>
                        <select id="user_filter" name="user_filter">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['userID'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="report-actions">
                    <button type="submit" class="btn-generate">Generate Report</button>
                    <button type="button" class="btn-export" id="export-excel" disabled>Export to Excel</button>
                    <button type="button" class="btn-export-csv" id="export-csv" disabled>Export CSV (Google Sheets)</button>
                    <button type="button" class="btn-export-sheets" id="export-sheets" disabled>Export to Google Sheets</button>
                </div>
            </form>
        </div>
        
        <div id="report-results" class="report-results" style="display: none;">
            <div class="report-container">
                <h2>Report Results</h2>
                
                <div class="report-summary" id="report-summary"></div>
                
                <div class="table-container">
                    <table class="tickets-table" id="report-table">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Client Name</th>
                                <th>Address</th>
                                <th>Type/Plan</th>
                                <th>Description/Amount</th>
                                <th>Fiber Core</th>
                                <th>Team Member</th>
                                <th>Member</th>
                                <th>Status</th>
                                <th>Comment</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody id="report-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        document.getElementById('report-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('generate_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReport(data.tickets, data.summary);
                    document.getElementById('export-excel').disabled = false;
                    document.getElementById('export-csv').disabled = false;
                    document.getElementById('export-sheets').disabled = false;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while generating the report.');
            });
        });
        
        function displayReport(tickets, summary) {
            const resultsDiv = document.getElementById('report-results');
            const summaryDiv = document.getElementById('report-summary');
            const tbody = document.getElementById('report-tbody');
            
            // Display summary
            summaryDiv.innerHTML = 
                '<div class="summary-card">' +
                    '<h3>Total Tickets</h3>' +
                    '<div class="value">' + summary.total + '</div>' +
                '</div>' +
                '<div class="summary-card">' +
                    '<h3>Installation</h3>' +
                    '<div class="value">' + summary.installation + '</div>' +
                '</div>' +
                '<div class="summary-card">' +
                    '<h3>Maintenance</h3>' +
                    '<div class="value">' + summary.maintenance + '</div>' +
                '</div>' +
                '<div class="summary-card">' +
                    '<h3>Pull Out</h3>' +
                    '<div class="value">' + (summary.pullout || 0) + '</div>' +
                '</div>';
            
            // Display tickets
            tbody.innerHTML = '';
            if (tickets.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="no-results">No tickets found for the selected criteria.</td></tr>';
            } else {
                tickets.forEach(ticket => {
                    const row = document.createElement('tr');
                    row.innerHTML = 
                        '<td>' + ticket.ticket_number + '</td>' +
                        '<td>' + ticket.client_name + '</td>' +
                        '<td>' + ticket.address + '</td>' +
                        '<td>' + ticket.type_plan + '</td>' +
                        '<td>' + ticket.description_amount + '</td>' +
                        '<td>' + (ticket.fiber_core || 'N/A') + '</td>' +
                        '<td>' + (ticket.assigned_user || 'Unassigned') + '</td>' +
                        '<td>' + (ticket.accepts_member || '-') + '</td>' +
                        '<td><span class="status-badge status-' + ticket.status.toLowerCase().replace(' ', '-') + '">' + ticket.status + '</span></td>' +
                        '<td>' + (ticket.comment || 'N/A') + '</td>' +
                        '<td>' + ticket.start_time + '</td>' +
                        '<td>' + ticket.end_time + '</td>' +
                        '<td class="duration-cell">' + ticket.duration + '</td>';
                    tbody.appendChild(row);
                });
            }
            
            resultsDiv.style.display = 'block';
        }
        
        document.getElementById('export-excel').addEventListener('click', function() {
            const form = document.getElementById('report-form');
            const formData = new FormData(form);
            formData.append('export', 'excel');
            
            // Create a temporary form to submit
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'export_report.php';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                tempForm.appendChild(input);
            }
            
            document.body.appendChild(tempForm);
            tempForm.submit();
            document.body.removeChild(tempForm);
        });
        
        document.getElementById('export-csv').addEventListener('click', function() {
            const form = document.getElementById('report-form');
            const formData = new FormData(form);
            
            // Create a temporary form to submit
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = 'export_csv_for_sheets.php';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                tempForm.appendChild(input);
            }
            
            document.body.appendChild(tempForm);
            tempForm.submit();
            document.body.removeChild(tempForm);
        });
        
        document.getElementById('export-sheets').addEventListener('click', function() {
            showGoogleSheetsModal();
        });
        
        // Set default dates (current month)
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.getElementById('date_from').valueAsDate = firstDay;
        document.getElementById('date_to').valueAsDate = lastDay;
    </script>
    
    <!-- Google Sheets Export Modal -->
    <div id="google-sheets-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeGoogleSheetsModal()">&times;</span>
            <h2>Export to Google Sheets</h2>
            
            <form id="sheets-export-form">
                <div class="form-group">
                    <label for="spreadsheet_id">Google Sheet ID <span class="required">*</span></label>
                    <input type="text" id="spreadsheet_id" name="spreadsheet_id" required 
                           placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms">
                    <small>Copy the ID from your Google Sheet URL: docs.google.com/spreadsheets/d/<strong>SHEET_ID</strong>/edit</small>
                </div>
                
                <div class="form-group">
                    <label for="sheet_name">Sheet Name</label>
                    <input type="text" id="sheet_name" name="sheet_name" 
                           placeholder="Ticket Report 2024-03-11">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="create_new_sheet" name="create_new_sheet" checked>
                        Create new sheet (uncheck to overwrite existing sheet)
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeGoogleSheetsModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Export to Google Sheets</button>
                </div>
            </form>
            
            <div class="setup-instructions" style="margin-top: 20px; padding: 15px; background: #f3f4f6; border-radius: 6px;">
                <h4>Quick Setup:</h4>
                <ol>
                    <li>Create or open a Google Sheet</li>
                    <li>Copy the Sheet ID from the URL</li>
                    <li>Share the sheet with: <code id="service-email">Loading...</code></li>
                    <li>Give "Editor" permissions</li>
                    <li>Paste the Sheet ID above and click Export</li>
                </ol>
            </div>
        </div>
    </div>
    
    <script>
        // Load service account email for sharing instructions
        fetch('get_service_email.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('service-email').textContent = data.client_email || 'Not configured';
            })
            .catch(() => {
                document.getElementById('service-email').textContent = 'Not configured';
            });
        
        function showGoogleSheetsModal() {
            document.getElementById('google-sheets-modal').style.display = 'flex';
            // Set default sheet name with current Philippine time (UTC+8)
            var now = new Date();
            var phTime = new Date(now.getTime() + (8 * 60 * 60 * 1000));
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var month = months[phTime.getUTCMonth()];
            var day = phTime.getUTCDate();
            var year = phTime.getUTCFullYear();
            var hours = phTime.getUTCHours();
            var minutes = String(phTime.getUTCMinutes()).padStart(2, '0');
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            document.getElementById('sheet_name').value = 'Ticket Report ' + month + ' ' + day + ', ' + year + ' ' + hours + ':' + minutes + ' ' + ampm;
        }
        
        function closeGoogleSheetsModal() {
            document.getElementById('google-sheets-modal').style.display = 'none';
        }
        
        document.getElementById('sheets-export-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('create_new_sheet', document.getElementById('create_new_sheet').checked ? 'true' : 'false');
            
            // Show loading state
            const submitBtn = this.querySelector('.btn-submit');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Exporting...';
            submitBtn.disabled = true;
            
            fetch('google_sheets_production.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeGoogleSheetsModal();
                    showExportSuccess(data.rows_exported, data.url);
                } else {
                    showExportError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showExportError('An error occurred while exporting to Google Sheets.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('google-sheets-modal');
            if (event.target === modal) {
                closeGoogleSheetsModal();
            }
        }
    </script>
    
    <script src="JavaScript.js"></script>
    <script src="notification_bar.js?v=11"></script>
    <script src="db_events.js?v=1"></script>

    <!-- Export Result Modal -->
    <div id="export-result-modal" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(15,23,42,0.65); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:36px 32px 28px; max-width:420px; width:90%; box-shadow:0 24px 64px rgba(0,0,0,0.25); text-align:center; position:relative;">
            <div id="export-result-icon" style="font-size:3rem; margin-bottom:12px;"></div>
            <h3 id="export-result-title" style="margin:0 0 10px; font-size:1.25rem; font-weight:700; color:#1f2937;"></h3>
            <p id="export-result-msg" style="margin:0 0 8px; color:#4b5563; font-size:0.95rem; line-height:1.5;"></p>
            <p id="export-result-rows" style="margin:0 0 24px; color:#6b7280; font-size:0.88rem;"></p>
            <div style="display:flex; gap:10px; justify-content:center;">
                <button id="export-open-sheet" onclick="openExportedSheet()" style="display:none; padding:10px 24px; background:linear-gradient(80deg,#1a73e8,#4285f4); color:#fff; border:none; border-radius:8px; font-size:0.95rem; font-weight:700; cursor:pointer; box-shadow:0 3px 10px rgba(26,115,232,0.3);">
                    📊 Open Sheet
                </button>
                <button onclick="closeExportResult()" style="padding:10px 24px; background:#f3f4f6; color:#374151; border:2px solid #e5e7eb; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        var _exportedUrl = '';

        function showExportSuccess(rows, url) {
            _exportedUrl = url;
            document.getElementById('export-result-icon').textContent = '✅';
            document.getElementById('export-result-title').textContent = 'Export Successful!';
            document.getElementById('export-result-title').style.color = '#166534';
            document.getElementById('export-result-msg').textContent = 'Report exported to Google Sheets.';
            document.getElementById('export-result-rows').textContent = 'Rows exported: ' + rows;
            document.getElementById('export-open-sheet').style.display = 'inline-block';
            document.getElementById('export-result-modal').style.display = 'flex';
        }

        function showExportError(msg) {
            _exportedUrl = '';
            document.getElementById('export-result-icon').textContent = '❌';
            document.getElementById('export-result-title').textContent = 'Export Failed';
            document.getElementById('export-result-title').style.color = '#991b1b';
            document.getElementById('export-result-msg').textContent = msg;
            document.getElementById('export-result-rows').textContent = '';
            document.getElementById('export-open-sheet').style.display = 'none';
            document.getElementById('export-result-modal').style.display = 'flex';
        }

        function closeExportResult() {
            document.getElementById('export-result-modal').style.display = 'none';
        }

        function openExportedSheet() {
            if (_exportedUrl) window.open(_exportedUrl, '_blank');
            closeExportResult();
        }
    </script>
<script src="darkmode.js"></script>
</body>
</html>