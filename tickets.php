<?php
// Use centralized session initialization
require_once 'session_init.php';
require_once 'config.php';
require_once 'role_check.php';
require_once 'permission_helper.php';
requireAdminRole();
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_url = str_replace(' ', '%20', $base_url);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch users for assignment
$users_sql = "SELECT userID, name, role FROM users ORDER BY name ASC";
$users_result = $conn->query($users_sql);
$users = [];
if ($users_result) {
    while ($user = $users_result->fetch_assoc()) {
        $users[] = $user;
    }
}

// Fetch Installation Tickets
$installations_sql = "SELECT i.id, i.ticket_number, i.client_name, i.address, i.contact_number, i.status, i.service_type, 
        i.connection_type, i.plan, i.installation_date, i.nap_assignment, i.created_at,
        COALESCE(i.assigned_to, NULL) as assigned_to,
        CASE WHEN i.assigned_to > 0 AND u.name IS NOT NULL THEN u.name ELSE 'Unassigned' END as assigned_name,
        COALESCE(i.priority, 'Normal') as priority
        FROM installations i
        LEFT JOIN users u ON i.assigned_to = u.userID AND i.assigned_to > 0
        WHERE i.status IN ('Pending', 'In Progress')
        ORDER BY 
            CASE COALESCE(i.priority, 'Normal')
                WHEN 'Urgent' THEN 1
                WHEN 'Normal' THEN 2
                WHEN 'Less Priority' THEN 3
            END,
            i.created_at DESC";
$installations_result = $conn->query($installations_sql);

// Fetch Maintenance Tickets
$maintenance_sql = "SELECT m.id, m.ticket_number, m.client_name, m.address, m.contact_number, m.issue_type, 
        m.priority, m.description, m.status, m.assigned_to, m.scheduled_date, m.created_at,
        CASE WHEN m.assigned_to > 0 AND u.name IS NOT NULL THEN u.name ELSE 'Unassigned' END as assigned_name
        FROM maintenances m
        LEFT JOIN users u ON m.assigned_to = u.userID AND m.assigned_to > 0
        WHERE m.status IN ('Pending', 'In Progress')
        ORDER BY 
            CASE m.priority
                WHEN 'Urgent' THEN 1
                WHEN 'Normal' THEN 2
                WHEN 'Less Priority' THEN 3
            END,
            m.created_at DESC";
$maintenance_result = $conn->query($maintenance_sql);

// Fetch Pull Out Tickets
$pullout_sql = "SELECT p.id, p.ticket_number, p.client_name, p.address, p.contact_number, p.reason, 
        p.priority, p.status, p.assigned_to, p.created_at,
        CASE WHEN p.assigned_to > 0 AND u.name IS NOT NULL THEN u.name ELSE 'Unassigned' END as assigned_name
        FROM pullout_tickets p
        LEFT JOIN users u ON p.assigned_to = u.userID AND p.assigned_to > 0
        WHERE p.status IN ('Pending', 'In Progress')
        ORDER BY 
            CASE p.priority
                WHEN 'Urgent' THEN 1
                WHEN 'Normal' THEN 2
                WHEN 'Less Priority' THEN 3
            END,
            p.created_at DESC";
$pullout_result = $conn->query($pullout_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="100x100" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="150x150" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="150x150" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    
    
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="StyleSheet.css" />
    <link rel="stylesheet" href="darkmode.css?v=3" />
    <link rel="stylesheet" href="mobile.css?v=1" />
    <link rel="stylesheet" href="tickets.css?v=3" />
    <link rel="stylesheet" href="notification_bar.css" />
    <script>window.BASE_URL = '<?= $base_url ?>';</script>
    <script src="tickets_address_save_patch.js?v=3"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
   
    
    <style>
        /* ── Modern Tickets.php Redesign ─────────────────────────────────── */

        .main h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 20px;
            letter-spacing: -0.3px;
        }

        /* Pill-style tab switcher */
        .ticket-type-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 22px;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 14px;
            width: fit-content;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.07);
            border: none;
        }
        .ticket-type-tab {
            padding: 9px 20px;
            background: transparent;
            border: none;
            border-bottom: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }
        .ticket-type-tab:hover {
            color: #1f2937;
            background: rgba(255,255,255,0.7);
        }
        .ticket-type-tab.active {
            background: linear-gradient(135deg, #485e8b 0%, #9e7979 100%);
            color: #000000;
            box-shadow: 0 4px 12px rgba(235, 230, 230, 0.28);
        }
        .ticket-type-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(255,255,255,0.22);
            color: inherit;
            margin-left: 0;
        }
        .ticket-type-tab:not(.active) .ticket-type-badge {
            background: #e2e8f0;
            color: #475569;
        }

        /* Filters bar — compact, no heavy box */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
            flex-wrap: wrap;
        }
        .filter-group.search-group { flex: 1; min-width: 200px; }
        .filter-group label { display: none; }
        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.875rem;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .search-input:focus {
            outline: none;
            border-color: #d60b0e;
            box-shadow: 0 0 0 3px rgba(214,11,14,0.08);
        }
        .search-group::before { bottom: 11px; font-size: 1rem; }
        /* New Sidebar Styles */
        .sidebar-section {
            margin-bottom: 20px;
            padding: 0 12px;
        }
        .sidebar-section:first-child {
            margin-top: 15px;
        }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            font-weight: 700;
            padding: 8px 12px;
            margin-bottom: 6px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar nav ul li {
            margin: 2px 0;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, transparent 100%);
            transition: width 0.3s ease;
        }
        .nav-link:hover::before {
            width: 100%;
        }
        .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(4px);
        }
        .nav-link.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        .nav-link.active::before {
            display: none;
        }
        .nav-icon {
            font-size: 18px;
            width: 26px;
            text-align: center;
            flex-shrink: 0;
        }
        .nav-text {
            white-space: nowrap;
        }
        .logout-link {
            color: #f87171;
            margin-top: 8px;
        }
        .logout-link:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        .logout-link::before {
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.15) 0%, transparent 100%);
        }
        /* Submenu styling */
        .submenu {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            margin: 4px 0 8px 38px;
            padding: 6px 0;
        }
        .submenu li a {
            display: block;
            padding: 8px 14px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            border-radius: 6px;
            margin: 2px 8px;
            transition: all 0.2s ease;
        }
        .submenu li a:hover {
            color: #fff;
            background: rgba(99, 102, 241, 0.15);
        }
        .submenu-toggle {
            cursor: pointer;
        }
        .arrow {
            margin-left: auto;
            font-size: 10px;
            transition: transform 0.3s ease;
        }
        .has-submenu.open .arrow {
            transform: rotate(180deg);
        }
        /* Collapsed sidebar adjustments */
        .sidebar.collapsed .sidebar-section {
            padding: 0 8px;
        }
        .sidebar.collapsed .section-title {
            display: none;
        }
        .sidebar.collapsed .nav-text {
            display: none;
        }
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px;
        }
        .sidebar.collapsed .nav-icon {
            margin: 0;
        }
        .sidebar.collapsed .arrow {
            display: none;
        }

        .clear-filters-btn {
            padding: 10px 16px;
            background: #f1f5f9;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.18s;
            align-self: auto;
            box-shadow: none;
        }
        .clear-filters-btn:hover {
            background: #e2e8f0;
            color: #1f2937;
            transform: none;
            box-shadow: none;
        }

        /* City filter pills */
        .city-filters {
            margin-bottom: 14px;
            padding: 14px 16px;
            background: #fff;
            border-radius: 12px;
            border: 1.5px solid #f1f5f9;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .city-filters-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 10px;
        }
        .city-btn {
            padding: 5px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            background: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: none;
        }
        .city-btn:hover { border-color: #d60b0e; color: #d60b0e; background: #fff; }
        .city-btn.active {
            background: linear-gradient(135deg, #1f2937, #d60b0e);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 2px 8px rgba(214,11,14,0.22);
        }
        .city-btn.active:hover { background: linear-gradient(135deg, #1f2937, #d60b0e); }

        /* Actions bar */
        .actions-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn-create-ticket {
            padding: 9px 18px;
            background: linear-gradient(135deg, #1f2937 0%, #d60b0e 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(214,11,14,0.18);
            letter-spacing: 0.2px;
        }
        .btn-create-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(214,11,14,0.32);
        }

        /* Table */
        .table-container {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 4px 16px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .tickets-table thead tr {
            background: linear-gradient(135deg, #1f2937 0%, #d60b0e 100%);
        }
        .tickets-table th {
            padding: 13px 14px;
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(255,255,255,0.88);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tickets-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.875rem;
            color: #374151;
            vertical-align: middle;
        }
        .tickets-table tbody tr:last-child td { border-bottom: none; }

        /* Ticket number monospace chip */
        .ticket-number {
            font-weight: 700;
            font-size: 0.82rem;
            color: #1f2937;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3px;
        }

        /* Type chips in unified table */
        .tickets-table span[style*="background:#1f2937"],
        .tickets-table span[style*="background:#059669"],
        .tickets-table span[style*="background:#d97706"] {
            border-radius: 6px !important;
            font-size: 0.65rem !important;
            letter-spacing: 0.4px;
        }

        /* Bulk delete button */
        .bulk-delete-btn {
            padding: 9px 16px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(220,38,38,0.2);
        }
        .bulk-delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(220,38,38,0.35);
        }

        /* Priority badges — refined */
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }
        .priority-urgent  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .priority-normal  { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .priority-low     { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }
        .status-pending     { background: #fef9c3; color: #854d0e; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .status-installed, .status-completed, .status-closed { background: #dcfce7; color: #166534; }
        .status-cancelled   { background: #fee2e2; color: #991b1b; }
        .status-negative    { background: #fce7f3; color: #9d174d; }

        @media (max-width: 600px) {
            .ticket-type-tabs {
                width: 100%;
                overflow-x: auto;
                border-radius: 10px;
            }
            .ticket-type-tab { font-size: 0.8rem; padding: 8px 14px; }
        }

                /* Custom Address Dropdowns */
                .input-dropdown-group {
                    position: relative;
                    width: 100%;
                }
                .input-dropdown-group input[type="text"] {
                    width: 100%;
                    padding: 10px 36px 10px 12px;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    font-size: 15px;
                    background: #fff;
                    transition: border-color 0.2s;
                    box-sizing: border-box;
                }
                .input-dropdown-group input[type="text"]:focus {
                    border-color: #059669;
                    outline: none;
                    background: #f8fdfa;
                }
                .dropdown-arrow {
                    position: absolute;
                    right: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    pointer-events: none;
                    font-size: 1.2em;
                    color: #888;
                }
                .custom-dropdown {
                    display: none;
                    position: absolute;
                    left: 0;
                    right: 0;
                    top: 100%;
                    z-index: 30;
                    background: #fff;
                    border: 1px solid #ccc;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    max-height: 200px;
                    overflow-y: auto;
                    margin: 0;
                    padding: 0;
                    list-style: none;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                }
                .custom-dropdown li {
                    padding: 10px 16px;
                    cursor: pointer;
                    font-size: 15px;
                    transition: background 0.15s;
                }
                .custom-dropdown li:hover, .custom-dropdown li:focus {
                    background: #f0fdf4;
                    color: #059669;
                }
                .input-dropdown-group input[type="text"][readonly] {
                    background: #f5f5f5;
                    color: #888;
                    cursor: not-allowed;
                }
                /* Fix overlapping dropdowns in modals */
                .modal .custom-dropdown {
                    z-index: 1050;
                }
        /* Ticket Type Tabs */
        .ticket-type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        }
        
        .ticket-type-tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .ticket-type-tab:hover {
            color: #d60b0e;
            background: #f5f5f5;
        }
        
        .ticket-type-tab.active {
            color: #180102;
            border-bottom-color: #d60b0e;
            font-weight: 600;
        }
        
        .ticket-section {
            display: none;
        }
        
        .ticket-section.active {
            display: block;
        }
        
        .ticket-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            background: #e0e0e0;
            color: #666;
        }
        
        .ticket-type-tab.active .ticket-type-badge {
            background: #d60b0e;
            color: white;
        }

        @media (max-width: 600px) {
            .ticket-type-tabs {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6px !important;
                border-bottom: none !important;
                overflow: visible !important;
                margin-bottom: 15px;
            }
            .ticket-type-tab {
                padding: 10px 6px !important;
                font-size: 13px !important;
                border: 2px solid #e0e0e0 !important;
                border-radius: 6px !important;
                text-align: center;
                white-space: normal !important;
            }
            .ticket-type-tab.active {
                background: linear-gradient(80deg, #1f2937, #d60b0e) !important;
                color: white !important;
                border-color: #d60b0e !important;
            }
            .ticket-type-tab.active .ticket-type-badge {
                background: rgba(255,255,255,0.3) !important;
                color: white !important;
            }
        }

        /* Bulk Actions */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .bulk-delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .bulk-delete-btn:hover {
            background: #c82333;
        }

        .bulk-delete-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
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

        /* ── View Modal Redesign ─────────────────────────────────────────── */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.55);
            backdrop-filter: blur(3px);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        
        /* Edit modals should appear on top of view modals */
        #edit-ticket-modal,
        #edit-maintenance-modal,
        #edit-pullout-modal {
            z-index: 1100 !important;
        }
        
        /* Ensure view modals stay at base level */
        #view-installation-modal,
        #view-maintenance-modal,
        #view-pullout-modal {
            z-index: 1000 !important;
        }

        .modal-content {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.18), 0 4px 16px rgba(0,0,0,0.08);
            max-width: 680px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .modal-large {
            max-width: 860px;
        }

        /* Modal sticky header */
        .modal-content > h2,
        .modal-content > .close-btn {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-content > h2 {
            margin: 0;
            padding: 22px 56px 18px 28px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #1f2937 0%, #d60b0e 100%);
            letter-spacing: -0.2px;
        }

        .close-btn {
            position: absolute !important;
            top: 14px;
            right: 16px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.18);
            color: #fff;
            font-size: 1.3rem;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            transition: background 0.15s;
        }
        .close-btn:hover { background: rgba(255,255,255,0.32); }

        /* Header action buttons row */
        .modal-header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 14px 28px 0;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .btn-edit-full, .btn-jump-tech, .btn-subscription {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s;
            white-space: nowrap;
        }
        .btn-edit-full {
            background: linear-gradient(135deg, #1f2937, #374151);
            color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .btn-edit-full:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .btn-jump-tech {
            background: #f1f5f9;
            color: #475569;
            border: 1.5px solid #e2e8f0;
        }
        .btn-jump-tech:hover { background: #e2e8f0; color: #1f2937; }
        .btn-subscription {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            box-shadow: 0 2px 6px rgba(5,150,105,0.2);
        }
        .btn-subscription:hover { transform: translateY(-1px); }

        /* Modal body scroll area */
        #view-installation-content,
        #view-maintenance-content,
        #view-pullout-content {
            padding: 24px 28px 28px;
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }

        /* Details sections */
        .details-section {
            background: #fff;
            border: 1.5px solid #f1f5f9;
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .details-section h3 {
            font-size: 0.72rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1.5px solid #f1f5f9;
        }

        /* Info grid — 2 columns on desktop */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .info-item.full-width {
            grid-column: 1 / -1;
        }

        .info-item label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            word-break: break-word;
        }

        /* Description / notes boxes */
        .description-box,
        .notes-box,
        .feedback-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.6;
            min-height: 60px;
        }

        /* Mobile overrides for modals */
        @media (max-width: 600px) {
            .modal[style*="flex"],
            .modal[style*="block"] { padding: 0; align-items: flex-end; display: flex !important; flex-direction: column; justify-content: flex-end; }
            .modal-content,
            .modal-large {
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 20px 20px 0 0 !important;
                margin: 0 !important;
                max-height: 92vh;
                overflow-y: auto;
            }
            .modal-content > h2 {
                padding: 18px 52px 14px 20px;
                font-size: 1rem;
                border-radius: 20px 20px 0 0;
            }
            .modal-header-actions {
                padding: 12px 16px 0;
                gap: 6px;
            }
            .btn-edit-full, .btn-jump-tech, .btn-subscription {
                font-size: 0.75rem;
                padding: 7px 12px;
                flex: 1;
                text-align: center;
            }
            #view-installation-content,
            #view-maintenance-content,
            #view-pullout-content {
                padding: 16px 16px 24px;
                max-height: none;
            }
            .info-grid {
                grid-template-columns: 1fr !important;
                gap: 10px;
            }
            .details-section {
                padding: 16px;
                border-radius: 12px;
            }
        }

    </style>
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
        <h1>All Tickets</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <!-- Ticket Type Tabs -->
        <div class="ticket-type-tabs">
            <button class="ticket-type-tab active" data-ticket-type="all">
                All Tickets
                <span class="ticket-type-badge">
                    <?= ($installations_result ? $installations_result->num_rows : 0) + 
                        ($maintenance_result ? $maintenance_result->num_rows : 0) + 
                        ($pullout_result ? $pullout_result->num_rows : 0) ?>
                </span>
            </button>
            <button class="ticket-type-tab" data-ticket-type="installation">
                Installation
                <span class="ticket-type-badge"><?= $installations_result ? $installations_result->num_rows : 0 ?></span>
            </button>
            <button class="ticket-type-tab" data-ticket-type="maintenance">
                Maintenance
                <span class="ticket-type-badge"><?= $maintenance_result ? $maintenance_result->num_rows : 0 ?></span>
            </button>
            <button class="ticket-type-tab" data-ticket-type="pullout">
                Pull Out
                <span class="ticket-type-badge"><?= $pullout_result ? $pullout_result->num_rows : 0 ?></span>
            </button>
        </div>

        <!-- All Tickets Section (Combined) -->
        <div id="all-section" class="ticket-section active">
            <!-- Filters Section -->
            <div class="filters-bar">
                <div class="filter-group search-group">
                    <label for="search-all">🔍 Search Tickets</label>
                    <input type="text" id="search-all" class="search-input" placeholder="Search by name, address, contact...">
                </div>
                <button class="clear-filters-btn" onclick="resetTicketFilters()">Clear Filters</button>
            </div>

            <!-- City Filter Buttons -->
            <div class="city-filters">
                <div class="city-filters-label">� Filter by City/Town:</div>
                <div id="city-buttons-container-all" class="city-buttons-container">
                    <button class="city-btn active" data-city="">All Cities</button>
                    <?php
                    // Get all cities from service_areas table
                    $city_result = $conn->query("SELECT DISTINCT city FROM service_areas WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
                    
                    if ($city_result && $city_result->num_rows > 0) {
                        while ($row = $city_result->fetch_assoc()) {
                            echo '<button class="city-btn" data-city="' . htmlspecialchars($row['city']) . '">' . htmlspecialchars($row['city']) . '</button>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="actions-bar">
                <form id="bulk-delete-all-form" method="POST" action="bulk_delete_tickets.php" style="display:inline;" onsubmit="return prepareAllTicketsBulkDelete()">
                    <button type="submit" class="bulk-delete-btn" id="bulk-delete-all-btn" style="margin-right:10px;display:none;">🗑️ Delete Selected (<span id="bulk-delete-all-count">0</span>)</button>
                    <div id="bulk-delete-tickets-inputs"></div>
                </form>
                <button class="btn-create-ticket" id="open-create-installation" type="button">+ Create Installation</button>
                <button class="btn-create-ticket" id="open-create-maintenance" type="button" style="background: linear-gradient(80deg, #059669, #10b981);">+ Create Maintenance</button>
                <button class="btn-create-ticket" id="open-create-pullout" type="button" style="background: linear-gradient(80deg, #d97706, #f59e0b);">+ Create Pull Out</button>
            </div>

            <div class="table-container">
                <table class="tickets-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-all"></th>
                                <th>Actions</th>
                                <th>Ticket #</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Contact Number</th>
                                <th>Info</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Combine all tickets with type indicator
                            $all_tickets = [];
                            // Add installation tickets
                            if ($installations_result && $installations_result->num_rows > 0) {
                                $installations_result->data_seek(0);
                                while ($row = $installations_result->fetch_assoc()) {
                                    $row['ticket_type'] = 'installation';
                                    $row['ticket_type_label'] = 'Installation';
                                    $row['info'] = '₱' . ($row['plan'] ?? '') . '<br><small>' . htmlspecialchars($row['service_type'] ?? '') . '</small>';
                                    $all_tickets[] = $row;
                                }
                            }
                            // Add maintenance tickets
                            if ($maintenance_result && $maintenance_result->num_rows > 0) {
                                $maintenance_result->data_seek(0);
                                while ($row = $maintenance_result->fetch_assoc()) {
                                    $row['ticket_type'] = 'maintenance';
                                    $row['ticket_type_label'] = 'Maintenance';
                                    $row['info'] = htmlspecialchars($row['issue_type'] ?? '') . '<br><small>' . htmlspecialchars(mb_substr($row['description'] ?? '', 0, 30)) . ((isset($row['description']) && mb_strlen($row['description']) > 30) ? '...' : '') . '</small>';
                                    $all_tickets[] = $row;
                                }
                            }
                            // Add pullout tickets
                            if ($pullout_result && $pullout_result->num_rows > 0) {
                                $pullout_result->data_seek(0);
                                while ($row = $pullout_result->fetch_assoc()) {
                                    $row['ticket_type'] = 'pullout';
                                    $row['ticket_type_label'] = 'Pull Out';
                                    $row['info'] = '<small>' . htmlspecialchars(mb_substr($row['reason'] ?? '', 0, 40)) . ((isset($row['reason']) && mb_strlen($row['reason']) > 40) ? '...' : '') . '</small>';
                                    $all_tickets[] = $row;
                                }
                            }
                            // Sort by priority and date
                            usort($all_tickets, function($a, $b) {
                                $priority_order = ['Urgent' => 1, 'Normal' => 2, 'Less Priority' => 3];
                                $a_priority = $priority_order[$a['priority'] ?? 'Normal'] ?? 2;
                                $b_priority = $priority_order[$b['priority'] ?? 'Normal'] ?? 2;
                                if ($a_priority != $b_priority) return $a_priority - $b_priority;
                                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
                            });
                            // Resolve assigned_to ID -> name (guaranteed fallback via DB lookup)
                            $users_map = [];
                            foreach ($users as $u) { $users_map[$u['userID']] = $u['name']; }
                            foreach ($all_tickets as &$t) {
                                if (!empty($t['assigned_name'])) continue; // already set by JOIN
                                if (!empty($t['assigned_to']) && $t['assigned_to'] > 0) {
                                    $t['assigned_name'] = $users_map[$t['assigned_to']] ?? 'Unassigned';
                                } else {
                                    $t['assigned_name'] = 'Unassigned';
                                }
                            }
                            unset($t);
                            if (count($all_tickets) > 0):
                                foreach ($all_tickets as $row):
                                    $priority_class = $row['priority'] == 'Urgent' ? 'priority-urgent' : ($row['priority'] == 'Normal' ? 'priority-normal' : 'priority-low');
                            ?>
                                <tr class="clickable-row" data-status="<?= $row['status'] ?>" data-priority="<?= $row['priority'] ?? 'Normal' ?>" data-type="<?= $row['ticket_type'] ?>" data-id="<?= $row['id'] ?>">
                                    <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox-all" value="<?= $row['ticket_type'] ?>:<?= $row['id'] ?>"></td>
                                    <td class="td-actions" onclick="event.stopPropagation()">
                                        <div class="action-buttons">
                                            <?php if (canAssignTickets()): ?>
                                                <button class="btn-icon btn-assign" onclick="<?= $row['ticket_type'] == 'installation' ? 'openAssignModal' : ($row['ticket_type'] == 'maintenance' ? 'openAssignMaintenanceModal' : 'openAssignPulloutModal') ?>(<?= $row['id'] ?>, '<?= htmlspecialchars($row['client_name'] ?? '', ENT_QUOTES) ?>')" title="Assign"></button>
                                            <?php endif; ?>
                                            <?php if ($row['ticket_type'] == 'installation'): ?>
                                                <button class="btn-icon btn-view" onclick="viewInstallation(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                            <?php elseif ($row['ticket_type'] == 'maintenance'): ?>
                                                <button class="btn-icon btn-view" onclick="viewMaintenanceDetails(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                            <?php elseif ($row['ticket_type'] == 'pullout'): ?>
                                                <button class="btn-icon btn-view" onclick="viewPulloutDetails(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                            <?php endif; ?>
                                            <?php if (canDelete()): ?>
                                                <button class="btn-icon btn-delete" onclick="<?= $row['ticket_type'] == 'installation' ? 'deleteTicket' : ($row['ticket_type'] == 'maintenance' ? 'deleteMaintenanceTicket' : 'deletePulloutTicket') ?>(<?= $row['id'] ?>)" title="Delete">🗑️</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ticket-number"><?= htmlspecialchars($row['ticket_number'] ?? '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['client_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                                    <td><?php echo $row['info'] ?? ''; ?></td>
                                    <td><?= htmlspecialchars($row['assigned_name'] ?? 'Unassigned') ?></td>
                                    <td><?= htmlspecialchars($row['created_at'] ?? '') ?></td>
                                    <td class="details-column"><div class="detail-item"><span class="priority-badge <?= $priority_class ?>"><?= htmlspecialchars($row['priority'] ?? 'Normal') ?></span></div><div class="detail-item"><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></div></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">No tickets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
            </div>
        </div>

        <!-- Installation Tickets Section -->
        <div id="installation-section" class="ticket-section">
            <!-- Filters Section -->
            <div class="filters-bar">
                <div class="filter-group search-group">
                    <label for="search-installation">🔍 Search Tickets</label>
                    <input type="text" id="search-installation" class="search-input" placeholder="Search by name, address, contact...">
                </div>
                <div class="bulk-actions">
                    <button class="bulk-delete-btn" onclick="bulkDeleteTickets('installation')" style="display: none;">🗑️ Delete Selected</button>
                </div>
                <button class="clear-filters-btn" onclick="resetTicketFilters()">Clear Filters</button>
            </div>

            <div class="actions-bar">
                <button class="btn-create-ticket" id="create-ticket-btn">+ Create Ticket</button>
                <button class="btn-delete-selected" id="delete-selected-installation-btn" style="display: none;">🗑️ Delete Selected</button>
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
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset result pointer for installation tickets
                        if ($installations_result && $installations_result->num_rows > 0) {
                            $installations_result->data_seek(0);
                        }
                        ?>
                        <?php if ($installations_result && $installations_result->num_rows > 0): ?>
                            <?php while ($row = $installations_result->fetch_assoc()): 
                                $assigned_name = $row['assigned_name'] ?? 'Unassigned';
                                $priority_class = '';
                                if ($row['priority'] == 'Urgent') $priority_class = 'priority-urgent';
                                elseif ($row['priority'] == 'Normal') $priority_class = 'priority-normal';
                                else $priority_class = 'priority-low';
                            ?>
                                <tr class="clickable-row" data-status="<?= $row['status'] ?>" data-priority="<?= $row['priority'] ?>" data-type="installation" data-id="<?= $row['id'] ?>">
                                    <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox" data-id="<?= $row['id'] ?>"></td>
                                    <td class="td-actions" onclick="event.stopPropagation()">
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-view" onclick="viewInstallation(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ticket-number"><?= htmlspecialchars($row['ticket_number'] ?? 'INST-' . $row['id']) ?></div>
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
                                        if (isset($row['created_at']) && $row['created_at']) {
                                            $created_date = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
                                            echo '<div class="created-date">' . $created_date->format('M j, Y') . '</div>';
                                            echo '<div class="created-time">' . $created_date->format('g:i A') . '</div>';
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
                                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span>
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
        </div>

        <!-- Maintenance Tickets Section -->
        <div id="maintenance-section" class="ticket-section">
            <!-- Filters Section -->
            <div class="filters-bar">
                <div class="filter-group search-group">
                    <label for="search-maintenance">🔍 Search Tickets</label>
                    <input type="text" id="search-maintenance" class="search-input" placeholder="Search by name, address, contact...">
                </div>
                <button class="clear-filters-btn" onclick="resetTicketFilters()">Clear Filters</button>
            </div>

            <!-- City Filter Buttons -->
            <div class="city-filters">
                <div class="city-filters-label">� Filter by City/Town:</div>
                <div id="city-buttons-container-maintenance" class="city-buttons-container">
                    <button class="city-btn active" data-city="">All Cities</button>
                    <?php
                    // Get all cities from service_areas table
                    $city_result = $conn->query("SELECT DISTINCT city FROM service_areas WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
                    
                    if ($city_result && $city_result->num_rows > 0) {
                        while ($row = $city_result->fetch_assoc()) {
                            echo '<button class="city-btn" data-city="' . htmlspecialchars($row['city']) . '">' . htmlspecialchars($row['city']) . '</button>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="actions-bar">
                <button class="btn-create-ticket" id="create-maintenance-btn">+ Create Maintenance Ticket</button>
                <button class="btn-delete-selected" id="delete-selected-maintenance-btn" style="display: none;">🗑️ Delete Selected</button>
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
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset result pointer for maintenance tickets
                        if ($maintenance_result && $maintenance_result->num_rows > 0) {
                            $maintenance_result->data_seek(0);
                        }
                        ?>
                        <?php if ($maintenance_result && $maintenance_result->num_rows > 0): ?>
                            <?php while ($row = $maintenance_result->fetch_assoc()): 
                                $assigned_name = $row['assigned_name'] ?? 'Unassigned';
                                $priority_class = '';
                                if ($row['priority'] == 'Urgent') $priority_class = 'priority-urgent';
                                elseif ($row['priority'] == 'Normal') $priority_class = 'priority-normal';
                                else $priority_class = 'priority-low';
                            ?>
                                <tr class="clickable-row" data-status="<?= $row['status'] ?>" data-priority="<?= $row['priority'] ?>" data-type="maintenance" data-id="<?= $row['id'] ?>">
                                    <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox" data-id="<?= $row['id'] ?>"></td>
                                    <td class="td-actions" onclick="event.stopPropagation()">
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-view" onclick="viewMaintenanceDetails(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ticket-number"><?= htmlspecialchars($row['ticket_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['issue_type']) ?></td>
                                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                                    <td><?= htmlspecialchars($row['address']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                    <td class="description-cell"><?= htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '') ?></td>
                                    <td class="created-cell">
                                        <?php 
                                        if (isset($row['created_at']) && $row['created_at']) {
                                            $created_date = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
                                            echo '<div class="created-date">' . $created_date->format('M j, Y') . '</div>';
                                            echo '<div class="created-time">' . $created_date->format('g:i A') . '</div>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="details-column">
                                        <div class="detail-item">
                                            <span class="priority-badge <?= $priority_class ?>"><?= htmlspecialchars($row['priority']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span>
                                        </div>
                                        <div class="detail-item assigned-to">
                                            👤 <?= htmlspecialchars($assigned_name) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-data">No maintenance tickets found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pull Out Tickets Section -->
        <div id="pullout-section" class="ticket-section">
            <!-- Filters Section -->
            <div class="filters-bar">
                <div class="filter-group search-group">
                    <label for="search-pullout">🔍 Search Tickets</label>
                    <input type="text" id="search-pullout" class="search-input" placeholder="Search by name, address, contact...">
                </div>
                <button class="clear-filters-btn" onclick="resetTicketFilters()">Clear Filters</button>
            </div>

            <!-- City Filter Buttons -->
            <div class="city-filters">
                <div class="city-filters-label">� Filter by City/Town:</div>
                <div id="city-buttons-container-pullout" class="city-buttons-container">
                    <button class="city-btn active" data-city="">All Cities</button>
                    <?php
                    // Get all cities from service_areas table
                    $city_result = $conn->query("SELECT DISTINCT city FROM service_areas WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
                    
                    if ($city_result && $city_result->num_rows > 0) {
                        while ($row = $city_result->fetch_assoc()) {
                            echo '<button class="city-btn" data-city="' . htmlspecialchars($row['city']) . '">' . htmlspecialchars($row['city']) . '</button>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="actions-bar">
                <button class="btn-create-ticket" id="create-pullout-btn">+ Create Pull Out Ticket</button>
                <button class="btn-delete-selected" id="delete-selected-pullout-btn" style="display: none;">🗑️ Delete Selected</button>
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
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset result pointer for pullout tickets
                        if ($pullout_result && $pullout_result->num_rows > 0) {
                            $pullout_result->data_seek(0);
                        }
                        ?>
                        <?php if ($pullout_result && $pullout_result->num_rows > 0): ?>
                            <?php while ($row = $pullout_result->fetch_assoc()): 
                                $assigned_name = $row['assigned_name'] ?? 'Unassigned';
                                $priority_class = '';
                                if ($row['priority'] == 'Urgent') $priority_class = 'priority-urgent';
                                elseif ($row['priority'] == 'Normal') $priority_class = 'priority-normal';
                                else $priority_class = 'priority-low';
                            ?>
                                <tr class="clickable-row" data-status="<?= $row['status'] ?>" data-priority="<?= $row['priority'] ?>" data-type="pullout" data-id="<?= $row['id'] ?>">
                                    <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox" data-id="<?= $row['id'] ?>"></td>
                                    <td class="td-actions" onclick="event.stopPropagation()">
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-view" onclick="viewPulloutDetails(<?= $row['id'] ?>)" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ticket-number"><?= htmlspecialchars($row['ticket_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                                    <td><?= htmlspecialchars($row['address']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                    <td class="description-cell"><?= htmlspecialchars(substr($row['reason'], 0, 50)) . (strlen($row['reason']) > 50 ? '...' : '') ?></td>
                                    <td class="created-cell">
                                        <?php 
                                        if (isset($row['created_at']) && $row['created_at']) {
                                            $created_date = new DateTime($row['created_at'], new DateTimeZone('Asia/Manila'));
                                            echo '<div class="created-date">' . $created_date->format('M j, Y') . '</div>';
                                            echo '<div class="created-time">' . $created_date->format('g:i A') . '</div>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td class="details-column">
                                        <div class="detail-item">
                                            <span class="priority-badge <?= $priority_class ?>"><?= htmlspecialchars($row['priority']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span>
                                        </div>
                                        <div class="detail-item assigned-to">
                                            👤 <?= htmlspecialchars($assigned_name) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-data">No pull out tickets found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Create Installation Ticket Modal -->
    <div id="create-ticket-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
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
                            <label for="ticket_account">Account Number</label>
                            <input type="text" id="ticket_account" name="account_number">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <div class="address-cascade">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Province</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_city_input" type="text" placeholder="Select province first..." autocomplete="off" name="city" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Barangay</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_barangay_input" type="text" placeholder="Select city first..." autocomplete="off" name="barangay" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Purok/Zone</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="ticket_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="ticket_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="ticket_zip_code" name="zip_code" readonly>
                                </div>
                            </div>
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
                            <label for="ticket_nap">NAP Assignment</label>
                            <input type="text" id="ticket_nap" name="nap_assignment">
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
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticket_contract">Contract Duration <span class="required">*</span></label>
                            <select id="ticket_contract" name="contract_duration" required>
                                <option value="">--Select Duration--</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticket_prepaid">Prepaid Amount Paid</label>
                            <input type="number" id="ticket_prepaid" name="prepaid_amount" step="0.01" min="0">
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

    <!-- Create Maintenance Ticket Modal -->
    <div id="create-maintenance-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-create-maintenance">×</button>
            <h2>Create Maintenance Ticket</h2>
            <form id="create-maintenance-form" action="process_maintenance.php" method="POST">
                <div class="form-section">
                    <h3>Client Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maint_name">Client Name <span class="required">*</span></label>
                            <input type="text" id="maint_name" name="client_name" required>
                        </div>
                        <div class="form-group">
                            <label for="maint_number">Contact Number <span class="required">*</span></label>
                            <input type="tel" id="maint_number" name="contact_number" required>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <div class="address-cascade">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Province</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="maint_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="maint_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="maint_city_input" type="text" placeholder="Select province first..." autocomplete="off" name="city" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="maint_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Barangay</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="maint_barangay_input" type="text" placeholder="Select city first..." autocomplete="off" name="barangay" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="maint_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Purok/Zone</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="maint_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="maint_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="maint_zip_code" name="zip_code" readonly>
                                </div>
                            </div>
                            <input type="hidden" id="maint_address" name="address">
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h3>Maintenance Details</h3>
                    <div class="form-row">
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
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="maint_description">Description <span class="required">*</span></label>
                            <textarea id="maint_description" name="description" rows="4" required placeholder="Describe the maintenance issue in detail..."></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="maint_scheduled_date">Scheduled Date</label>
                            <input type="date" id="maint_scheduled_date" name="scheduled_date">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-create-maintenance">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Installation Ticket Modal -->
    <div id="edit-ticket-modal" class="modal" style="display:none;">
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
                            <label for="edit_account_number">Account Number</label>
                            <input type="text" id="edit_account_number" name="account_number">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <div class="address-cascade">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Province</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_province_input" type="text" placeholder="Type or select province..." autocomplete="off" name="province" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_province_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:1050;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_city_input" type="text" placeholder="Select province first..." autocomplete="off" name="city" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_city_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:1050;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Barangay</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_barangay_input" type="text" placeholder="Select city first..." autocomplete="off" name="barangay" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_barangay_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:1050;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Purok/Zone</label>
                                    <div class="input-dropdown-group" style="position:relative;">
                                        <input id="edit_purok_zone_input" type="text" placeholder="Type or select purok..." autocomplete="off" name="purok_zone" style="padding-right:30px;">
                                        <span class="dropdown-arrow" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:1em;color:#888;">▼</span>
                                        <ul id="edit_purok_zone_dropdown" class="custom-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:1050;background:#fff;border:1px solid #ccc;border-top:none;max-height:180px;overflow-y:auto;margin:0;padding:0;list-style:none;"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <input type="text" id="edit_zip_code" name="zip_code" readonly>
                                </div>
                            </div>
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
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_contract_duration">Contract Duration <span class="required">*</span></label>
                            <select id="edit_contract_duration" name="contract_duration" required>
                                <option value="">--Select Duration--</option>
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

    <!-- View Installation Modal -->
    <div id="view-installation-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-installation">×</button>
            <h2>Installation Details</h2>

            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-installation" onclick="editInstallationFromView()">Edit Installation Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToTechnicalDetails()">⚙️ Jump to Technical Details</button>
                <button type="button" class="btn-subscription" onclick="openSubscriptionAgreement()">📄 Generate Subscription Agreement</button>
            </div>

            <div id="view-installation-content">
                <div class="details-section">
                    <h3>Installation Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><label>Ticket Number:</label><span id="view-inst-ticket-number" class="ticket-number"></span></div>
                        <div class="info-item"><label>Status:</label><span id="view-inst-status"></span></div>
                        <div class="info-item"><label>Priority:</label><span id="view-inst-priority"></span></div>
                        <div class="info-item"><label>Service Type:</label><span id="view-inst-service-type"></span></div>
                        <div class="info-item"><label>Connection Type:</label><span id="view-inst-connection"></span></div>
                        <div class="info-item"><label>Plan:</label><span id="view-inst-plan"></span></div>
                        <div class="info-item"><label>Contract Duration:</label><span id="view-inst-contract"></span></div>
                        <div class="info-item"><label>Installation Date:</label><span id="view-inst-date"></span></div>
                        <div class="info-item"><label>Assigned To:</label><span id="view-inst-assigned"></span></div>
                        <div class="info-item"><label>Created:</label><span id="view-inst-created"></span></div>
                        <div class="info-item"><label>Prepaid Amount:</label><span id="view-inst-prepaid"></span></div>
                    </div>
                </div>
            <div class="details-section">
            <h3>Client Information</h3>
            <div class="info-grid">
         <div class="info-item"><label>Name:</label><span id="view-inst-client-name"></span></div>
         <div class="info-item"><label>Contact Number:</label><span id="view-inst-contact"></span></div>
        <div class="info-item"><label>Email:</label><span id="view-inst-email"></span></div>
        <div class="info-item"><label>Account Number:</label><span id="view-inst-account"></span></div>
        <div class="info-item full-width"><label>Address: <button type="button" onclick="copyAddress(this)" style="margin-left: 8px; padding: 2px 6px; background: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; vertical-align: middle;">Copy</button></label><span id="view-inst-address"></span></div>
        <div class="info-item" style="margin-top: -60px; visibility: hidden;"><label></label><span></span></div>
        <div class="info-item" style="margin-top: -60px;"><label>Service Connection Info: <button type="button" onclick="copyServiceConnectionInfo(this)" style="margin-left: 8px; padding: 2px 6px; background: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; vertical-align: middle;">Copy</button></label><span id="view-inst-service-connection-info" style="padding: 0; background: transparent; border: none; display: none;">
                <div style="font-weight: 600; color: #1e40af; margin-bottom: 4px;" id="view-inst-service-connection-title"></div>
                <div style="color: #6b7280; font-size: 0.9rem;" id="view-inst-prepaid-info"></div>
            </span></div>
    </div>
</div>

                <div class="details-section" id="technical-details-section">
                    <h3>Technical Details</h3>
                    <form id="technical-details-form">
                        <input type="hidden" id="tech-installation-id" name="installation_id">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tech-nap-assignment">NAP Assignment</label>
                                <input type="text" id="tech-nap-assignment" name="nap_assignment">
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

                <div class="details-section">
                    <h3>Installation Images</h3>
                    <form id="inst-upload-form" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" id="inst-upload-id" name="installation_id">
                        <div class="upload-area">
                            <input type="file" id="inst-image-upload" name="images[]" multiple accept="image/*">
                            <label for="inst-image-upload" class="upload-label">
                                <span class="upload-icon">📷</span>
                                <span>Click to select images</span>
                                <span class="upload-hint">You can select multiple images</span>
                            </label>
                        </div>
                        <div id="inst-preview-container" class="preview-container"></div>
                        <button type="submit" class="btn-upload" id="inst-upload-btn" style="display: none;">Upload Images</button>
                    </form>
                    <div id="inst-images-gallery" class="images-gallery"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Maintenance Modal -->
    <div id="view-maintenance-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-maintenance">×</button>
            <h2>Maintenance Ticket Details</h2>
            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-maintenance" onclick="editMaintenanceFromView()">Edit Maintenance Details</button>
                <button type="button" class="btn-jump-tech" onclick="scrollToTechnicalDetailsMaintenance()">⚙️ Jump to Technical Details</button>
            </div>
            <div id="view-maintenance-content">
                <div class="details-section">
                    <h3>Ticket Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><label>Ticket Number:</label><span id="view-maintenance-ticket-number" class="ticket-number"></span></div>
                        <div class="info-item"><label>Status:</label><span id="view-maintenance-status"></span></div>
                        <div class="info-item"><label>Priority:</label><span id="view-maintenance-priority"></span></div>
                        <div class="info-item"><label>Issue Type:</label><span id="view-issue-type"></span></div>
                        <div class="info-item"><label>Created:</label><span id="view-maintenance-created"></span></div>
                        <div class="info-item"><label>Assigned To:</label><span id="view-maintenance-assigned"></span></div>
                    </div>
                </div>
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><label>Name:</label><span id="view-maintenance-client-name"></span></div>
                        <div class="info-item"><label>Contact Number:</label><span id="view-maintenance-contact"></span></div>
                        <div class="info-item"><label>Email:</label><span id="view-email"></span></div>
                        <div class="info-item"><label>Account Number:</label><span id="view-account"></span></div>
                        <div class="info-item full-width"><label>Address:</label><span id="view-maintenance-address"></span></div>
                    </div>
                </div>
                <div class="details-section">
                    <h3>Issue Description</h3>
                    <div class="description-box" id="view-maintenance-description"></div>
                </div>
                <div class="details-section" id="technician-notes-section" style="display:none;">
                    <h3>Technician Notes</h3>
                    <div class="notes-box" id="view-technician-notes"></div>
                </div>
                <div class="details-section" id="customer-feedback-section" style="display:none;">
                    <h3>Customer Feedback</h3>
                    <div class="feedback-box">
                        <div id="view-customer-feedback"></div>
                        <div class="rating" id="view-rating-section" style="display:none;">Rating: <span id="view-rating-stars"></span></div>
                    </div>
                </div>
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
                            <div class="form-group" id="fiber-core-group" style="display:none;">
                                <label for="modal-fiber-core">Fiber Core Count</label>
                                <select id="modal-fiber-core" name="fiber_core_count">
                                    <option value="">--Select Core Count--</option>
                                    <option value="1 Core">1 Core</option>
                                    <option value="2 Core">2 Core</option>
                                    <option value="4 Core">4 Core</option>
                                    <option value="6 Core">6 Core</option>
                                    <option value="12 Core">12 Core</option>
                                    <option value="24 Core">24 Core</option>
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
                        </div>
                        <div class="form-row">
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
                    <div class="clearance-section">
                        <h3>NOC Clearance</h3>
                        <div class="clearance-item">
                            <button type="button" class="btn-clear-noc" id="btn-modal-noc-clear" onclick="clearModalMaintenanceNOC(this)">NOC CLEAR</button>
                            <div id="modal-noc-clear-info" class="clearance-info"></div>
                        </div>
                    </div>
                </div>
                <div class="details-section" id="modal-images-section">
                    <h3>Images</h3>
                    
                    <!-- Upload Form -->
                    <form id="modal-maintenance-upload-form" enctype="multipart/form-data" class="upload-form">
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

    <!-- View Pull Out Modal -->
    <div id="view-pullout-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-view-pullout">×</button>
            <h2>Pull Out Ticket Details</h2>
            <div class="modal-header-actions">
                <button type="button" class="btn-edit-full" id="btn-edit-pullout" onclick="editPulloutFromView()">Edit Pull Out Details</button>
            </div>
            <div id="view-pullout-content">
                <div class="details-section">
                    <h3>Ticket Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><label>Ticket Number:</label><span id="view-pullout-ticket-number" class="ticket-number"></span></div>
                        <div class="info-item"><label>Status:</label><span id="view-pullout-status"></span></div>
                        <div class="info-item"><label>Priority:</label><span id="view-pullout-priority"></span></div>
                        <div class="info-item"><label>Created:</label><span id="view-pullout-created"></span></div>
                        <div class="info-item"><label>Assigned To:</label><span id="view-pullout-assigned"></span></div>
                    </div>
                </div>
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><label>Name:</label><span id="view-pullout-client-name"></span></div>
                        <div class="info-item"><label>Contact Number:</label><span id="view-pullout-contact"></span></div>
                        <div class="info-item full-width"><label>Address:</label><span id="view-pullout-address"></span></div>
                    </div>
                </div>
                <div class="details-section">
                    <h3>Pull Out Reason</h3>
                    <div class="description-box" id="view-pullout-reason"></div>
                </div>
                <div class="details-section">
                    <h3>Update Status</h3>
                    <form id="modal-pullout-status-form">
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
                <div class="details-section">
                    <div class="clearance-section">
                        <h3>NOC Clearance</h3>
                        <div class="clearance-item">
                            <button type="button" class="btn-clear-noc" id="btn-pullout-noc-clear" onclick="clearModalPulloutNOC()">NOC CLEAR</button>
                            <div id="pullout-noc-clear-info" class="clearance-info"></div>
                        </div>
                    </div>
                </div>
                <div class="details-section">
                    <h3>Upload Images</h3>
                    <form id="modal-pullout-upload-form" enctype="multipart/form-data">
                        <input type="hidden" id="modal-pullout-upload-id" name="pullout_id">
                        <div class="upload-area" onclick="document.getElementById('modal-pullout-image-upload').click()">
                            <input type="file" id="modal-pullout-image-upload" name="images[]" multiple accept="image/*" style="display:none;">
                            <label class="upload-label" style="pointer-events:none;">
                                <span class="upload-icon">📷</span>
                                <span>Click to select images</span>
                            </label>
                        </div>
                        <div id="modal-pullout-preview-container" class="preview-container"></div>
                        <button type="submit" class="btn-upload" id="modal-pullout-upload-btn" style="display: none;">Upload Images</button>
                    </form>
                    <div id="modal-pullout-images-gallery" class="images-gallery"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Pull Out Modal -->
    <div id="create-pullout-modal" class="modal" style="display:none;">
        <div class="modal-content modal-large">
            <button class="close-btn" id="close-create-pullout">×</button>
            <h2>Create Pull Out Ticket</h2>
            <form id="create-pullout-form" action="process_pullout.php" method="POST">
                <div class="form-section">
                    <h3>Client Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pullout_name">Client Name <span class="required">*</span></label>
                            <input type="text" id="pullout_name" name="client_name" required>
                        </div>
                        <div class="form-group">
                            <label for="pullout_number">Contact Number</label>
                            <input type="tel" id="pullout_number" name="contact_number">
                        </div>
                    </div>
                    <div class="form-group full-width">
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
                            <input type="hidden" id="pullout_address" name="address">
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h3>Pull Out Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pullout_priority">Priority <span class="required">*</span></label>
                            <select id="pullout_priority" name="priority" required>
                                <option value="">--Select Priority--</option>
                                <option value="Less Priority">Less Priority</option>
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pullout_reason">Reason <span class="required">*</span></label>
                        <textarea id="pullout_reason" name="reason" rows="4" required placeholder="Describe the pull out reason in detail..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Ticket</button>
                    <button type="button" class="btn-cancel" id="cancel-create-pullout">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="image-modal" class="image-modal" onclick="if(event.target===this)closeImageModal()">
        <span class="close-modal" onclick="event.stopPropagation();closeImageModal()" style="z-index:10001;">&times;</span>
        <button class="modal-nav-btn modal-prev-btn" onclick="event.stopPropagation();showPrevImage()">&#10094;</button>
        <span style="position:absolute; bottom:18px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,0.55); font-size:0.78rem; pointer-events:none; white-space:nowrap;">Pinch or scroll to zoom &bull; Double-tap to toggle</span>
        <img class="modal-image" id="modal-image" src="">
        <button class="modal-nav-btn modal-next-btn" onclick="event.stopPropagation();showNextImage()">&#10095;</button>
    </div>

    <script src="JavaScript.js?v=6"></script>
    <script src="image_zoom.js?v=1"></script>
    <script src="dialog.js?v=1"></script>
    <script src="notification_bar.js?v=6"></script>
    <script src="db_events.js?v=1"></script>
    <script src="installation_view.js?v=27"></script>
    <script src="tickets_maintenance.js?v=25"></script>
    <script src="tickets_pullout.js?v=33"></script>
    <script src="tickets_unified.js?v=26"></script>
    <script src="bulk_actions.js?v=8"></script>
    <script src="permissions.js"></script>
    <script src="service_plans_loader.js?v=8"></script>
    <script src="ticket_filters.js?v=8"></script>
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Select2 Integration -->
    <script src="select2-integration.js"></script>
    <!-- Address Formatting -->
    <script src="format_address.js"></script>
    <script src="create_ticket_ajax.js?v=2"></script>

    <script>
    // --- Custom Address Dropdowns for Ticket Modals (Installation, Maintenance, Pull Out) ---
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
                    // Create Installation Modal
                    setupCustomDropdown('ticket_province', ticket_provinces, function(selectedProvince) {
                        const cityList = ticket_cities[selectedProvince] || [];
                        setupCustomDropdown('ticket_city', cityList, function(selectedCity) {
                            const cityKey = selectedProvince + '|' + selectedCity;
                            const barangayList = ticket_barangays[cityKey] || [];
                            setupCustomDropdown('ticket_barangay', barangayList, function(selectedBarangay) {
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
                    // Create Maintenance Modal
                    setupCustomDropdown('maint_province', ticket_provinces, function(selectedProvince) {
                        const cityList = ticket_cities[selectedProvince] || [];
                        setupCustomDropdown('maint_city', cityList, function(selectedCity) {
                            const cityKey = selectedProvince + '|' + selectedCity;
                            const barangayList = ticket_barangays[cityKey] || [];
                            setupCustomDropdown('maint_barangay', barangayList, function(selectedBarangay) {
                                const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                document.getElementById('maint_zip_code').value = area ? area.zip_code : '';
                            });
                            document.getElementById('maint_barangay_input').value = '';
                            document.getElementById('maint_zip_code').value = '';
                        });
                        document.getElementById('maint_city_input').value = '';
                        document.getElementById('maint_barangay_input').value = '';
                        document.getElementById('maint_zip_code').value = '';
                    });
                    // Create Pull Out Modal
                    setupCustomDropdown('pullout_province', ticket_provinces, function(selectedProvince) {
                        const cityList = ticket_cities[selectedProvince] || [];
                        setupCustomDropdown('pullout_city', cityList, function(selectedCity) {
                            const cityKey = selectedProvince + '|' + selectedCity;
                            const barangayList = ticket_barangays[cityKey] || [];
                            setupCustomDropdown('pullout_barangay', barangayList, function(selectedBarangay) {
                                const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                document.getElementById('pullout_zip_code').value = area ? area.zip_code : '';
                            });
                            document.getElementById('pullout_barangay_input').value = '';
                            document.getElementById('pullout_zip_code').value = '';
                        });
                        document.getElementById('pullout_city_input').value = '';
                        document.getElementById('pullout_barangay_input').value = '';
                        document.getElementById('pullout_zip_code').value = '';
                    });
                    // Edit Installation Modal — wire address dropdowns inside fetch callback so data is ready
                    setupCustomDropdown('edit_province', ticket_provinces, function(selectedProvince) {
                        const cityList = ticket_cities[selectedProvince] || [];
                        setupCustomDropdown('edit_city', cityList, function(selectedCity) {
                            const cityKey = selectedProvince + '|' + selectedCity;
                            const barangayList = ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_barangay', barangayList, function(selectedBarangay) {
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
                    // Purok/Zone dropdowns
                    const ticketPurokOptions = [
                        'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5',
                        'Purok 6', 'Purok 7', 'Purok 8', 'Purok 9'
                    ];
                    setupCustomDropdown('ticket_purok_zone', ticketPurokOptions);
                    setupCustomDropdown('maint_purok_zone', ticketPurokOptions);
                    setupCustomDropdown('pullout_purok_zone', ticketPurokOptions);
                    setupCustomDropdown('edit_purok_zone', ticketPurokOptions);
                    // Edit Maintenance Modal Dropdowns
                    setupCustomDropdown('edit_maintenance_province', ticket_provinces, function(selectedProvince) {
            const cityList = ticket_cities[selectedProvince] || [];
            setupCustomDropdown('edit_maintenance_city', cityList, function(selectedCity) {
                const cityKey = selectedProvince + '|' + selectedCity;
                const barangayList = ticket_barangays[cityKey] || [];
                setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                    // Auto-fill zip code from service area
                    const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                    document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                });
                document.getElementById('edit_maintenance_barangay_input').value = '';
                document.getElementById('edit_maintenance_zip_code').value = '';
            });
                        document.getElementById('edit_maintenance_city_input').value = '';
                        document.getElementById('edit_maintenance_barangay_input').value = '';
                        document.getElementById('edit_maintenance_zip_code').value = '';
                    });
                    setupCustomDropdown('edit_maintenance_purok_zone', ticketPurokOptions);
                    // Edit Pullout Modal Dropdowns
                    setupCustomDropdown('edit_pullout_province', ticket_provinces, function(selectedProvince) {
            const cityList = ticket_cities[selectedProvince] || [];
            setupCustomDropdown('edit_pullout_city', cityList, function(selectedCity) {
                const cityKey = selectedProvince + '|' + selectedCity;
                const barangayList = ticket_barangays[cityKey] || [];
                setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                    // Auto-fill zip code from service area
                    const area = ticket_serviceAreas.find(a => a.province === selectedProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                    document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                });
                        document.getElementById('edit_pullout_barangay_input').value = '';
                        document.getElementById('edit_pullout_zip_code').value = '';
                    });
                    document.getElementById('edit_pullout_city_input').value = '';
                    document.getElementById('edit_pullout_barangay_input').value = '';
                    document.getElementById('edit_pullout_zip_code').value = '';
                });
                setupCustomDropdown('edit_pullout_purok_zone', ticketPurokOptions);
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
        });

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
                if (onSelect) onSelect(item);
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(li);
        });
        input.addEventListener('focus', function() {
            dropdown.style.display = items.length ? 'block' : 'none';
        });
        input.addEventListener('input', function() {
            const val = input.value.toLowerCase();
            Array.from(dropdown.children).forEach(li => {
                li.style.display = li.textContent.toLowerCase().includes(val) ? '' : 'none';
            });
            dropdown.style.display = items.length ? 'block' : 'none';
        });
        input.addEventListener('blur', function() {
            setTimeout(() => { dropdown.style.display = 'none'; }, 150);
        });
    }

    // ---- All-Tickets City Filter + Search ----
    (function() {
        let selectedCity = '';

        function extractCity(address) {
            if (!address || !address.includes(',')) return '';
            const PROVINCES = ['isabela','cagayan','nueva vizcaya','quirino','aurora','ifugao','kalinga','apayao','abra','mountain province','benguet','ilocos norte','ilocos sur','la union','pangasinan'];
            const parts = address.split(',').map(p => p.trim()).filter(Boolean);
            if (/^\d+$/.test(parts[parts.length - 1])) parts.pop();
            if (parts.length > 0 && PROVINCES.includes(parts[parts.length - 1].toLowerCase())) parts.pop();
            while (parts.length > 0 && /^(purok|zone|blk|block|lot)\b/i.test(parts[0])) parts.shift();
            return parts.length > 0 ? parts[parts.length - 1].toLowerCase() : '';
        }

        function applyAllFilters() {
            const searchVal = (document.getElementById('search-all') || {}).value?.toLowerCase() || '';
            const cityFilter = selectedCity.toLowerCase();
            const tbody = document.querySelector('#all-section .tickets-table tbody');
            if (!tbody) return;
            let visible = 0;
            tbody.querySelectorAll('tr').forEach(row => {
                if (row.querySelector('.no-data') || row.classList.contains('no-results-row')) { row.style.display = 'none'; return; }
                const cells = row.querySelectorAll('td');
                if (!cells.length) return;
                const address = cells[4] ? cells[4].textContent.trim() : '';
                const city = extractCity(address);
                const cityMatch = !cityFilter || city.includes(cityFilter) || address.toLowerCase().includes(cityFilter);
                const searchMatch = !searchVal || row.textContent.toLowerCase().includes(searchVal);
                row.style.display = (cityMatch && searchMatch) ? '' : 'none';
                if (cityMatch && searchMatch) visible++;
            });
            // no-results row
            let noRow = tbody.querySelector('.no-results-row');
            if (visible === 0) {
                if (!noRow) {
                    noRow = document.createElement('tr');
                    noRow.className = 'no-results-row';
                    const cols = document.querySelectorAll('#all-section .tickets-table thead th').length || 10;
                    noRow.innerHTML = `<td colspan="${cols}" class="no-data">No tickets match your filters</td>`;
                    tbody.appendChild(noRow);
                }
                noRow.style.display = '';
            } else if (noRow) {
                noRow.style.display = 'none';
            }
        }

        function selectCity(city) {
            selectedCity = city;
            document.querySelectorAll('#city-buttons-container-all .city-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.city === city);
            });
            applyAllFilters();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Wire up city buttons (already rendered by PHP)
            document.querySelectorAll('#city-buttons-container-all .city-btn').forEach(btn => {
                btn.addEventListener('click', function() { selectCity(this.dataset.city); });
            });
            // Wire up search
            const searchEl = document.getElementById('search-all');
            if (searchEl) searchEl.addEventListener('input', applyAllFilters);
        });

        // Expose reset for the Clear Filters button
        window.resetTicketFilters = function() {
            selectedCity = '';
            document.querySelectorAll('#city-buttons-container-all .city-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.city === '');
            });
            const searchEl = document.getElementById('search-all');
            if (searchEl) searchEl.value = '';
            applyAllFilters();
        };
    })();

    // ---- All-Tickets Bulk Delete ----
    document.addEventListener('DOMContentLoaded', function() {
        // Select-all checkbox
        const selectAllAll = document.getElementById('select-all-all');
        if (selectAllAll) {
            selectAllAll.addEventListener('change', function() {
                document.querySelectorAll('.row-checkbox-all').forEach(cb => {
                    const row = cb.closest('tr');
                    if (row && row.style.display !== 'none') cb.checked = this.checked;
                });
                updateAllBulkDeleteBtn();
            });
        }

        // Individual checkbox change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('row-checkbox-all')) {
                updateAllBulkDeleteBtn();
            }
        });
    });

    function updateAllBulkDeleteBtn() {
        const checked = document.querySelectorAll('.row-checkbox-all:checked');
        const btn = document.getElementById('bulk-delete-all-btn');
        const countEl = document.getElementById('bulk-delete-all-count');
        if (!btn) return;
        if (checked.length > 0) {
            btn.style.display = 'inline-block';
            if (countEl) countEl.textContent = checked.length;
        } else {
            btn.style.display = 'none';
        }
    }

    function prepareAllTicketsBulkDelete() {
        const checked = document.querySelectorAll('.row-checkbox-all:checked');
        if (checked.length === 0) return false;
        Dialog.confirm(`Are you sure you want to delete ${checked.length} ticket(s)? This cannot be undone.`, { type: 'danger', okText: 'Delete' }).then(ok => {
            if (!ok) return;
            const container = document.getElementById('bulk-delete-tickets-inputs');
            container.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tickets[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            document.getElementById('bulk-delete-all-form').submit();
        });
        return false;
    }
    // Handle edit form submissions via AJAX
    document.addEventListener('DOMContentLoaded', function() {
        // Installation ticket edit form
        const editForm = document.getElementById('edit-ticket-form');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (typeof getFullAddress === 'function') {
                    document.getElementById('edit_address').value = getFullAddress('edit_');
                }
                
                const formData = new FormData(this);
                const ticketId = document.getElementById('edit_ticket_id').value;
                
                fetch('update_ticket.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit-ticket-modal').style.display = 'none';
                        document.body.style.overflow = '';
                        // Refresh the ticket list
                        if (typeof window.triggerUnifiedRefresh === 'function') {
                            window.triggerUnifiedRefresh(true);
                        } else if (typeof refresh === 'function') {
                            refresh(true);
                        }
                        // Reopen view modal with updated data
                        if (typeof viewTicketDetails === 'function') {
                            viewTicketDetails(parseInt(ticketId, 10));
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
        }
        
        // Maintenance ticket edit form
        const editMaintenanceForm = document.getElementById('edit-maintenance-form');
        if (editMaintenanceForm) {
            editMaintenanceForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (typeof getFullAddress === 'function') {
                    document.getElementById('edit_maintenance_address').value = getFullAddress('edit_maintenance_');
                }
                
                const formData = new FormData(this);
                const ticketId = document.getElementById('edit_maintenance_id').value;
                
                fetch('update_maintenance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit-maintenance-modal').style.display = 'none';
                        document.body.style.overflow = '';
                        // Refresh the ticket list
                        if (typeof window.triggerUnifiedRefresh === 'function') {
                            window.triggerUnifiedRefresh(true);
                        } else if (typeof refresh === 'function') {
                            refresh(true);
                        }
                        // Reopen view modal with updated data
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
        }
        
        // Pullout ticket edit form
        const editPulloutForm = document.getElementById('edit-pullout-form');
        if (editPulloutForm) {
            editPulloutForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (typeof getFullAddress === 'function') {
                    document.getElementById('edit_pullout_address').value = getFullAddress('edit_pullout_');
                }
                
                const formData = new FormData(this);
                const ticketId = document.getElementById('edit_pullout_id').value;
                
                fetch('update_pullout.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit-pullout-modal').style.display = 'none';
                        document.body.style.overflow = '';
                        // Refresh the ticket list
                        if (typeof window.triggerUnifiedRefresh === 'function') {
                            window.triggerUnifiedRefresh(true);
                        } else if (typeof refresh === 'function') {
                            refresh(true);
                        }
                        // Reopen view modal with updated data
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
        }
    });
    </script>

    <!-- Assign Maintenance Modal -->
    <div id="assign-maintenance-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="document.getElementById('assign-maintenance-modal').style.display='none'">×</button>
            <h2>Assign User</h2>
            <form id="assign-maintenance-form">
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
                    <button type="button" class="btn-cancel" onclick="document.getElementById('assign-maintenance-modal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Pullout Modal -->
    <div id="assign-pullout-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="document.getElementById('assign-pullout-modal').style.display='none'">×</button>
            <h2>Assign User</h2>
            <form id="assign-pullout-form">
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
                    <button type="button" class="btn-cancel" onclick="document.getElementById('assign-pullout-modal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Installation Modal -->
    <div id="assign-modal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="document.getElementById('assign-modal').style.display='none'">×</button>
            <h2>Assign User</h2>
            <form id="assign-form">
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
                    <button type="button" class="btn-cancel" onclick="document.getElementById('assign-modal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

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
</script>
<script>
// Open Assign Modal functions
function openAssignModal(ticketId, clientName) {
    document.getElementById('assign_ticket_id').value = ticketId;
    document.getElementById('assign_ticket_name').textContent = 'Assigning ticket for: ' + clientName;
    document.getElementById('assign-modal').style.display = 'block';
}

function openAssignMaintenanceModal(ticketId, clientName) {
    document.getElementById('assign_maintenance_id').value = ticketId;
    document.getElementById('assign_maintenance_name').textContent = 'Assigning maintenance ticket for: ' + clientName;
    document.getElementById('assign-maintenance-modal').style.display = 'block';
}

function openAssignPulloutModal(ticketId, clientName) {
    document.getElementById('assign_pullout_id').value = ticketId;
    document.getElementById('assign_pullout_name').textContent = 'Assigning pull out ticket for: ' + clientName;
    document.getElementById('assign-pullout-modal').style.display = 'block';
}

// AJAX form handling for SPA updates
document.addEventListener('DOMContentLoaded', function() {
    // Installation assign form
    const installForm = document.getElementById('assign-form');
    if (installForm) {
        installForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('assign_user.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(() => {
                    document.getElementById('assign-modal').style.display = 'none';
                    setTimeout(() => {
                        if (typeof refresh === 'function') refresh(true);
                        else if (typeof window.triggerUnifiedRefresh === 'function') window.triggerUnifiedRefresh(true);
                    }, 500);
                })
                .catch(() => { 
                    document.getElementById('assign-modal').style.display = 'none';
                    window.location.reload();
                });
        });
    }
    
    // Maintenance assign form
    const maintForm = document.getElementById('assign-maintenance-form');
    if (maintForm) {
        maintForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('assign_maintenance.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(() => {
                    document.getElementById('assign-maintenance-modal').style.display = 'none';
                    setTimeout(() => {
                        if (typeof refresh === 'function') refresh(true);
                        else if (typeof window.triggerUnifiedRefresh === 'function') window.triggerUnifiedRefresh(true);
                    }, 500);
                })
                .catch(() => { 
                    document.getElementById('assign-maintenance-modal').style.display = 'none';
                    window.location.reload();
                });
        });
    }
    
    // Pullout assign form
    const pulloutForm = document.getElementById('assign-pullout-form');
    if (pulloutForm) {
        pulloutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('assign_pullout.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(() => {
                    document.getElementById('assign-pullout-modal').style.display = 'none';
                    setTimeout(() => {
                        if (typeof refresh === 'function') refresh(true);
                        else if (typeof window.triggerUnifiedRefresh === 'function') window.triggerUnifiedRefresh(true);
                    }, 500);
                })
                .catch(() => { 
                    document.getElementById('assign-pullout-modal').style.display = 'none';
                    window.location.reload();
                });
        });
    }

    // Global flags to prevent duplicate uploads
    window._maintenanceUploadInProgress = false;
    window._pulloutUploadInProgress = false;

    // Pullout upload - handle via button click instead of form submit
    const pulloutUploadBtn = document.getElementById('modal-pullout-upload-btn');
    if (pulloutUploadBtn && !pulloutUploadBtn._hasClickHandler) {
        pulloutUploadBtn._hasClickHandler = true;
        pulloutUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Prevent duplicate uploads
            if (pulloutUploadBtn._isUploading) {
                console.log('Pullout upload already in progress');
                return;
            }
            
            // Check timestamp
            const now = Date.now();
            if (pulloutUploadBtn._lastClick && (now - pulloutUploadBtn._lastClick < 3000)) {
                console.log('Pullout upload too soon');
                return;
            }
            pulloutUploadBtn._lastClick = now;
            pulloutUploadBtn._isUploading = true;
            
            const form = document.getElementById('modal-pullout-upload-form');
            const formData = new FormData(form);
            
            pulloutUploadBtn.disabled = true;
            pulloutUploadBtn.textContent = 'Uploading...';
            
            fetch('upload_pullout_images.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast('Images uploaded successfully!', 'success');
                    const pulloutId = document.getElementById('modal-pullout-upload-id').value;
                    if (typeof viewPulloutDetails === 'function') viewPulloutDetails(pulloutId);
                    form.reset();
                    document.getElementById('modal-pullout-preview-container').innerHTML = '';
                    selectedPulloutFiles = [];
                    pulloutUploadBtn.style.display = 'none';
                } else {
                    Dialog.error('Error uploading images: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Dialog.error('An error occurred while uploading images.');
            })
            .finally(() => {
                pulloutUploadBtn.disabled = false;
                pulloutUploadBtn.textContent = 'Upload Images';
                pulloutUploadBtn._isUploading = false;
            });
        });
    }

    // Maintenance upload - handle via button click instead of form submit
    const maintUploadBtn = document.getElementById('modal-upload-btn');
    if (maintUploadBtn && !maintUploadBtn._hasClickHandler) {
        maintUploadBtn._hasClickHandler = true;
        maintUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Prevent duplicate uploads
            if (maintUploadBtn._isUploading) {
                console.log('Maintenance upload already in progress');
                return;
            }
            
            // Check timestamp
            const now = Date.now();
            if (maintUploadBtn._lastClick && (now - maintUploadBtn._lastClick < 3000)) {
                console.log('Maintenance upload too soon');
                return;
            }
            maintUploadBtn._lastClick = now;
            maintUploadBtn._isUploading = true;
            
            const form = document.getElementById('modal-maintenance-upload-form');
            const formData = new FormData(form);
            
            maintUploadBtn.disabled = true;
            maintUploadBtn.textContent = 'Uploading...';
            
            fetch('upload_maintenance_images.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Dialog.toast('Images uploaded successfully!', 'success');
                    const maintenanceId = document.getElementById('modal-maintenance-id').value;
                    if (typeof viewMaintenanceDetails === 'function') viewMaintenanceDetails(maintenanceId);
                    form.reset();
                    document.getElementById('modal-preview-container').innerHTML = '';
                    selectedMaintFiles = [];
                    maintUploadBtn.style.display = 'none';
                } else {
                    Dialog.error('Error uploading images: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Dialog.error('An error occurred while uploading images.');
            })
            .finally(() => {
                maintUploadBtn.disabled = false;
                maintUploadBtn.textContent = 'Upload Images';
                maintUploadBtn._isUploading = false;
            });
        });
    }

    // Maintenance file upload - handle file selection and previews
    const maintImageUpload = document.getElementById('modal-image-upload');
    if (maintImageUpload && !maintImageUpload._hasChangeListener) {
        maintImageUpload._hasChangeListener = true;
        maintImageUpload.addEventListener('change', function(e) {
            // Prevent duplicate processing with timestamp check
            const now = Date.now();
            if (maintImageUpload._lastProcessed && (now - maintImageUpload._lastProcessed < 1000)) {
                console.log('Maintenance upload blocked - too soon');
                return;
            }
            maintImageUpload._lastProcessed = now;
            const files = Array.from(e.target.files);
            
            // Deduplicate files by name + size
            const seen = new Set();
            const uniqueFiles = [];
            files.forEach(file => {
                const key = file.name + '|' + file.size;
                if (!seen.has(key)) {
                    seen.add(key);
                    uniqueFiles.push(file);
                }
            });
            
            // Validate files
            const validFiles = [];
            const invalidFiles = [];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
            const allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.jfif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            uniqueFiles.forEach(file => {
                const fileExt = '.' + file.name.split('.').pop().toLowerCase();
                const isValidType = allowedTypes.includes(file.type);
                const isValidExt = allowedExtensions.includes(fileExt);
                
                if (!isValidType && !isValidExt) {
                    invalidFiles.push(file.name + ' (invalid file type)');
                } else if (file.size > maxSize) {
                    invalidFiles.push(file.name + ' (too large, max 5MB)');
                } else {
                    validFiles.push(file);
                }
            });
            
            // Show error for invalid files
            if (invalidFiles.length > 0) {
                Dialog.warning('The following files were rejected:\n' + invalidFiles.join('\n') + '\n\nOnly image files (JPG, PNG, GIF, WEBP) under 5MB are allowed.');
            }
            
            selectedMaintFiles = validFiles;
            // Clear preview container first
            document.getElementById('modal-preview-container').innerHTML = '';
            displayMaintPreviews(validFiles);
            
            if (validFiles.length > 0) {
                document.getElementById('modal-upload-btn').style.display = 'block';
            } else {
                document.getElementById('modal-upload-btn').style.display = 'none';
            }
            
            // Reset processing flag
            maintImageUpload._isProcessing = false;
        });
    }
});
function displayMaintPreviews(files) {
    const previewContainer = document.getElementById('modal-preview-container');
    previewContainer.innerHTML = '';

    files.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="preview-remove" onclick="removeMaintPreview(${index})">×</button>
            `;
            previewContainer.appendChild(previewItem);
        };
        
        reader.readAsDataURL(file);
    });
}

// Remove maintenance preview
function removeMaintPreview(index) {
    selectedMaintFiles.splice(index, 1);
    
    const dataTransfer = new DataTransfer();
    selectedMaintFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('modal-image-upload').files = dataTransfer.files;
    
    displayMaintPreviews(selectedMaintFiles);
    
    if (selectedMaintFiles.length === 0) {
        document.getElementById('modal-upload-btn').style.display = 'none';
    }
}

// Track selected maintenance files
let selectedMaintFiles = [];

// Track selected pullout files (must match tickets_pullout.js)
let selectedPulloutFiles = [];

// Note: Pullout file upload is handled by tickets_pullout.js
// The displayPulloutPreviews function below is kept for compatibility

// Display pullout image previews
function displayPulloutPreviews(files) {
    const previewContainer = document.getElementById('modal-pullout-preview-container');
    previewContainer.innerHTML = '';

    files.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="preview-remove" onclick="removePulloutPreview(${index})">×</button>
            `;
            previewContainer.appendChild(previewItem);
        };
        
        reader.readAsDataURL(file);
    });
}

// Remove pullout preview - uses selectedPulloutModalFiles to match tickets_pullout.js
function removePulloutPreview(index) {
    selectedPulloutModalFiles.splice(index, 1);
    
    const dataTransfer = new DataTransfer();
    selectedPulloutModalFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('modal-pullout-image-upload').files = dataTransfer.files;
    
    displayPulloutPreviews(selectedPulloutModalFiles);
    
    if (selectedPulloutModalFiles.length === 0) {
        document.getElementById('modal-pullout-upload-btn').style.display = 'none';
    }
}

// Image navigation variables and functions for tickets.php
var currentImageIndex = 0;
var currentImageList = [];

// Open image modal with navigation
function openImageModal(imagePath) {
    const modal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    if (!modal || !modalImage) return;
    
    // Get all images from the gallery
    const gallery = document.getElementById('modal-images-gallery');
    if (gallery) {
        const images = gallery.querySelectorAll('img');
        currentImageList = Array.from(images).map(img => img.src);
        // Try to find the index - handle both absolute and relative paths
        currentImageIndex = currentImageList.findIndex(src => {
            return src === imagePath || 
                   src.endsWith(imagePath) || 
                   imagePath.endsWith(src) ||
                   src.replace(window.location.origin, '') === imagePath.replace(window.location.origin, '');
        });
        if (currentImageIndex === -1) currentImageIndex = 0;
    }
    
    modal.style.display = 'block';
    modalImage.src = imagePath;
    updateNavButtons();
}

// Close image modal
function closeImageModal() {
    const modal = document.getElementById('image-modal');
    if (modal) modal.style.display = 'none';
}

// Show previous image
function showPrevImage() {
    if (currentImageList.length === 0) return;
    if (currentImageIndex === 0) return;
    currentImageIndex = (currentImageIndex - 1 + currentImageList.length) % currentImageList.length;
    const modalImage = document.getElementById('modal-image');
    if (modalImage) {
        modalImage.src = currentImageList[currentImageIndex];
    }
    updateNavButtons();
}

// Show next image
function showNextImage() {
    if (currentImageList.length === 0) return;
    if (currentImageIndex === currentImageList.length - 1) return;
    currentImageIndex = (currentImageIndex + 1) % currentImageList.length;
    const modalImage = document.getElementById('modal-image');
    if (modalImage) {
        modalImage.src = currentImageList[currentImageIndex];
    }
    updateNavButtons();
}

// Update navigation button visibility and state
function updateNavButtons() {
    const modal = document.getElementById('image-modal');
    if (!modal) return;
    
    const prevBtn = modal.querySelector('.modal-prev-btn');
    const nextBtn = modal.querySelector('.modal-next-btn');
    
    // Show/hide buttons based on image count
    if (prevBtn) prevBtn.style.display = currentImageList.length > 1 ? 'flex' : 'none';
    if (nextBtn) nextBtn.style.display = currentImageList.length > 1 ? 'flex' : 'none';
    
    // Disable/enable based on position
    if (prevBtn) {
        prevBtn.disabled = currentImageIndex === 0;
        prevBtn.style.opacity = currentImageIndex === 0 ? '0.3' : '1';
        prevBtn.style.cursor = currentImageIndex === 0 ? 'not-allowed' : 'pointer';
    }
    if (nextBtn) {
        nextBtn.disabled = currentImageIndex === currentImageList.length - 1;
        nextBtn.style.opacity = currentImageIndex === currentImageList.length - 1 ? '0.3' : '1';
        nextBtn.style.cursor = currentImageIndex === currentImageList.length - 1 ? 'not-allowed' : 'pointer';
    }
}

// Keyboard navigation for image modal
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('image-modal');
    const isOpen = modal && modal.style.display === 'block';
    
    if (!isOpen) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        showPrevImage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        showNextImage();
    } else if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>
<script>
  // Force white eye icon on urgent rows
  function fixUrgentEyeIcons() {
    document.querySelectorAll('tr[data-priority="Urgent"] .btn-view img, tr[data-priority="urgent"] .btn-view img').forEach(img => {
      img.style.filter = 'brightness(0) invert(1)';
      img.style.webkitFilter = 'brightness(0) invert(1)';
    });
  }
  setTimeout(fixUrgentEyeIcons, 100);
  setTimeout(fixUrgentEyeIcons, 500);
  setTimeout(fixUrgentEyeIcons, 1000);
</script>
<script>
// Function to construct full address from separate fields
function getFullAddress(prefix = '') {
    const province = document.getElementById(prefix + 'province_input')?.value || document.getElementById(prefix + 'province')?.value || '';
    const city = document.getElementById(prefix + 'city_input')?.value || document.getElementById(prefix + 'city')?.value || '';
    const barangay = document.getElementById(prefix + 'barangay_input')?.value || document.getElementById(prefix + 'barangay')?.value || '';
    const purok = document.getElementById(prefix + 'purok_zone_input')?.value || document.getElementById(prefix + 'purok_zone')?.value || '';
    const zipCode = document.getElementById(prefix + 'zip_code')?.value || '';
    return [purok, barangay, city, province, zipCode].filter(p => p).join(', ');
}

// Parse address string and populate cascading dropdowns
function parseAndPopulateAddress(addressString, prefix) {
    if (!addressString) return;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };

    const sAreas    = window.ticket_serviceAreas || [];
    const sCities   = window.ticket_cities       || {};
    const sBarangay = window.ticket_barangays    || {};

    // Try to match address parts against known service area data
    const parts = addressString.split(',').map(p => p.trim()).filter(Boolean);

    let province = '', city = '', barangay = '', purok = '', zipCode = '';

    // Walk through known provinces/cities/barangays to find a match
    const allProvinces = Object.keys(sCities);
    for (const prov of allProvinces) {
        if (parts.includes(prov)) {
            province = prov;
            const provCities = sCities[prov] || [];
            for (const c of provCities) {
                if (parts.includes(c)) {
                    city = c;
                    const cityKey = prov + '|' + c;
                    const cityBarangays = sBarangay[cityKey] || [];
                    for (const b of cityBarangays) {
                        if (parts.includes(b)) {
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
        let remaining = parts.filter(p => !known.includes(p));
        
        // A zip code is purely numeric (4-5 digits)
        const zipPart = remaining.find(p => /^\d{4,5}$/.test(p));
        if (zipPart) zipCode = zipPart;
        remaining = remaining.filter(p => p !== zipPart);
        
        // Try to identify barangay from remaining if not found (for addresses not in service areas)
        if (!barangay && remaining.length > 0) {
            // Look for a part that looks like a barangay (not starting with Purok/Prk)
            const possibleBarangay = remaining.find(p => 
                !p.toLowerCase().startsWith('purok') && 
                !p.toLowerCase().startsWith('prk') &&
                !p.toLowerCase().startsWith('zone') &&
                !/^\d+$/.test(p)
            );
            if (possibleBarangay) {
                barangay = possibleBarangay;
                remaining = remaining.filter(p => p !== barangay);
            }
        }
        
        // What's left is purok (should be things starting with Purok/Prk/Zone or just numbers)
        purok = remaining.join(', ');
    } else {
        // Fallback: positional — "Purok, Barangay, City, Province, Zip"
        purok     = parts[0] || '';
        barangay  = parts[1] || '';
        city      = parts[2] || '';
        province  = parts[3] || '';
        zipCode   = parts[4] || '';
    }

    // Auto-fill zip from service areas if not in address string
    if (!zipCode && province && city && barangay) {
        const area = sAreas.find(a => a.province === province && a.city === city && a.barangay === barangay);
        if (area) zipCode = area.zip_code || '';
    }

    // Populate city dropdown options for the saved province
    const cityDropdown = document.getElementById(prefix + 'city_dropdown');
    if (cityDropdown && province) {
        cityDropdown.innerHTML = '';
        (sCities[province] || []).forEach(c => {
            const li = document.createElement('li');
            li.textContent = c;
            cityDropdown.appendChild(li);
        });
    }

    // Populate barangay dropdown options for the saved city
    const barangayDropdown = document.getElementById(prefix + 'barangay_dropdown');
    if (barangayDropdown && province && city) {
        barangayDropdown.innerHTML = '';
        const cityKey = province + '|' + city;
        (sBarangay[cityKey] || []).forEach(b => {
            const li = document.createElement('li');
            li.textContent = b;
            barangayDropdown.appendChild(li);
        });
    }

    // Set all input values
    set(prefix + 'province_input',   province);
    set(prefix + 'city_input',       city);
    set(prefix + 'barangay_input',   barangay);
    set(prefix + 'purok_zone_input', purok);
    set(prefix + 'zip_code',         zipCode);

    // Legacy select fallback
    set(prefix + 'province',   province);
    set(prefix + 'city',       city);
    set(prefix + 'barangay',   barangay);
    set(prefix + 'purok_zone', purok);
}

// Edit ticket function for all ticket types
function editTicket(ticketId, type) {
    if (!ticketId) return;
    
    const ticketType = type || 'installation';
    
    // Fetch ticket data
    fetch('get_ticket_details.php?type=' + ticketType + '&id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ticket = data.ticket;
                
                if (ticketType === 'installation') {
                    // Populate installation form
                    document.getElementById('edit_ticket_id').value = ticket.id;
                    document.getElementById('edit_client_name').value = ticket.client_name || '';
                    document.getElementById('edit_contact_number').value = ticket.contact_number || '';
                    document.getElementById('edit_email').value = ticket.email || '';
                    document.getElementById('edit_account_number').value = ticket.account_number || '';
                    
                    // Parse address using smart matching against service areas
                    if (ticket.address && typeof parseAndPopulateAddress === 'function') {
                        parseAndPopulateAddress(ticket.address, 'edit_');
                    }
                    
                    // Setup cascading dropdowns for the pre-filled values
                    const editProvince = document.getElementById('edit_province_input').value;
                    const editCity = document.getElementById('edit_city_input').value;
                    const editBarangay = document.getElementById('edit_barangay_input').value;
                    if (editProvince && window.ticket_cities) {
                        const cityList = window.ticket_cities[editProvince] || [];
                        setupCustomDropdown('edit_city', cityList, function(selectedCity) {
                            const cityKey = editProvince + '|' + selectedCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === editProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_zip_code').value = area ? area.zip_code : '';
                            });
                        });
                        // Restore city value
                        document.getElementById('edit_city_input').value = editCity;
                        if (editCity) {
                            const cityKey = editProvince + '|' + editCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === editProvince && a.city === editCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_zip_code').value = area ? area.zip_code : '';
                            });
                            // Restore barangay value
                            document.getElementById('edit_barangay_input').value = editBarangay;
                        }
                    }
                    
                    // Populate additional fields
                    document.getElementById('edit_service_type').value = ticket.service_type || '';
                    document.getElementById('edit_connection_type').value = ticket.connection_type || '';
                    document.getElementById('edit_plan').value = ticket.plan_id || '';
                    // Trigger change event to populate contract duration
                    document.getElementById('edit_plan').dispatchEvent(new Event('change'));
                    
                    // Set contract duration after a short delay to allow dropdown to populate
                    setTimeout(() => {
                        document.getElementById('edit_contract_duration').value = ticket.contract_duration || '';
                    }, 100);
                    
                    document.getElementById('edit_prepaid_amount').value = ticket.prepaid_amount || '';
                    document.getElementById('edit_priority').value = ticket.priority || '';
                    
                    // Show installation modal
                    document.getElementById('edit-ticket-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else if (ticketType === 'maintenance') {
                    // Populate maintenance form
                    document.getElementById('edit_maintenance_id').value = ticket.id;
                    document.getElementById('edit_maintenance_client_name').value = ticket.client_name || '';
                    document.getElementById('edit_maintenance_contact_number').value = ticket.contact_number || '';
                    
                    // Parse address using smart matching against service areas
                    if (ticket.address && typeof parseAndPopulateAddress === 'function') {
                        parseAndPopulateAddress(ticket.address, 'edit_maintenance_');
                    }
                    
                    // Setup cascading dropdowns for the pre-filled values
                    const maintProvince = document.getElementById('edit_maintenance_province_input').value;
                    const maintCity = document.getElementById('edit_maintenance_city_input').value;
                    const maintBarangay = document.getElementById('edit_maintenance_barangay_input').value;
                    if (maintProvince && window.ticket_cities) {
                        const cityList = window.ticket_cities[maintProvince] || [];
                        setupCustomDropdown('edit_maintenance_city', cityList, function(selectedCity) {
                            const cityKey = maintProvince + '|' + selectedCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === maintProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                            });
                        });
                        // Restore city value
                        document.getElementById('edit_maintenance_city_input').value = maintCity;
                        if (maintCity) {
                            const cityKey = maintProvince + '|' + maintCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_maintenance_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === maintProvince && a.city === maintCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_maintenance_zip_code').value = area ? area.zip_code : '';
                            });
                            // Restore barangay value
                            document.getElementById('edit_maintenance_barangay_input').value = maintBarangay;
                        }
                    }
                    
                    document.getElementById('edit_maintenance_issue_type').value = ticket.issue_type || '';
                    document.getElementById('edit_maintenance_issue').value = ticket.description || '';
                    document.getElementById('edit_maintenance_priority').value = ticket.priority || '';
                    
                    // Show maintenance modal
                    document.getElementById('edit-maintenance-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else if (ticketType === 'pullout') {
                    // Populate pull out form
                    document.getElementById('edit_pullout_id').value = ticket.id;
                    document.getElementById('edit_pullout_client_name').value = ticket.client_name || '';
                    document.getElementById('edit_pullout_contact_number').value = ticket.contact_number || '';
                    
                    // Parse address using smart matching against service areas
                    if (ticket.address && typeof parseAndPopulateAddress === 'function') {
                        parseAndPopulateAddress(ticket.address, 'edit_pullout_');
                    }
                    
                    // Setup cascading dropdowns for the pre-filled values
                    const pulloutProvince = document.getElementById('edit_pullout_province_input').value;
                    const pulloutCity = document.getElementById('edit_pullout_city_input').value;
                    const pulloutBarangay = document.getElementById('edit_pullout_barangay_input').value;
                    if (pulloutProvince && window.ticket_cities) {
                        const cityList = window.ticket_cities[pulloutProvince] || [];
                        setupCustomDropdown('edit_pullout_city', cityList, function(selectedCity) {
                            const cityKey = pulloutProvince + '|' + selectedCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === pulloutProvince && a.city === selectedCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                            });
                        });
                        // Restore city value
                        document.getElementById('edit_pullout_city_input').value = pulloutCity;
                        if (pulloutCity) {
                            const cityKey = pulloutProvince + '|' + pulloutCity;
                            const barangayList = window.ticket_barangays[cityKey] || [];
                            setupCustomDropdown('edit_pullout_barangay', barangayList, function(selectedBarangay) {
                                const area = window.ticket_serviceAreas.find(a => a.province === pulloutProvince && a.city === pulloutCity && a.barangay === selectedBarangay);
                                document.getElementById('edit_pullout_zip_code').value = area ? area.zip_code : '';
                            });
                            // Restore barangay value
                            document.getElementById('edit_pullout_barangay_input').value = pulloutBarangay;
                        }
                    }
                    
                    document.getElementById('edit_pullout_reason').value = ticket.reason || '';
                    document.getElementById('edit_pullout_priority').value = ticket.priority || '';
                    
                    // Show pull out modal
                    document.getElementById('edit-pullout-modal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                console.error('Server returned error:', data.message);
            }
        })
        .catch(error => console.error('Error fetching ticket:', error));
}

// Close edit ticket modals
document.getElementById('close-edit-ticket').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-ticket-modal').style.display = 'none';
    document.body.style.overflow = '';
});

document.getElementById('cancel-edit-ticket').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('edit-ticket-modal').style.display = 'none';
    document.body.style.overflow = '';
});

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
