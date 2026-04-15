<?php
require_once 'session_init.php';
require_once 'role_check.php';
requireAdminRole();

// Ensure the gate password row exists (seeds default on first visit)
require_once 'config.php';
$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$gateRow = $conn->query("SELECT `value` FROM app_settings WHERE `key` = 'users_page_password'")->fetch_assoc();
if (!$gateRow) {
    $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('users_page_password', ?)");
    $ins->bind_param('s', $defaultHash);
    $ins->execute();
    $ins->close();
}
// Gate: always require password on every page load
unset($_SESSION['users_page_unlocked']);
$gateUnlocked = false;

$errors = [
    'login'    => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$active_form = $_SESSION['active_form'] ?? 'login';
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);

function showError($error) {
    return $error ? "<p class='error'>$error</p>" : "";
}
function isActiveForm($form_name, $activeForm) {
    return $form_name === $activeForm ? "active" : "";
}

require_once 'config.php';
$sql    = "SELECT * FROM users ORDER BY name ASC";
$result = $conn->query($sql);
$users  = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $users[] = $row;
}

// Add permission columns if they don't exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_pages TINYINT(1) DEFAULT 1");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS can_delete TINYINT(1) DEFAULT 1");

$role_colors = [
    'Admin'    => 'badge-admin',
    'Employee' => 'badge-employee',
];

// Pages with both access and delete permissions
$modalPagesWithDelete = [
    'tickets_installation' => 'Tickets Install',
    'tickets' => 'All Tickets',
    'tickets_maintenance' => 'Maintenance',
    'tickets_pullout' => 'Pull Out',
    'service_areas' => 'Service Areas',
    'service_plans' => 'Service Plans',
    'users' => 'Users'
];

// Pages with only access permission (no delete)
$modalPagesNoDelete = [
    'inventory' => 'Inventory',
    'reports' => 'Reports',
    'installation_form' => 'Install Form',
    'olt' => 'OLT'
];

// Combined for backward compatibility
$modalPages = array_merge($modalPagesWithDelete, $modalPagesNoDelete);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — UBILINK</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="user.css?v=3" />
    <link rel="stylesheet" href="StyleSheet.css?v=11" />
    <link rel="stylesheet" href="darkmode.css" />
    <link rel="stylesheet" href="mobile.css?v=1" />
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <span class="page-title-icon">👥</span>
                User Management
            </h1>
            <p class="page-subtitle"><?= count($users) ?> registered user<?= count($users) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="page-header-right">
            <button class="btn-add-user" id="show-change-password" onclick="document.getElementById('change-password-modal').style.display='flex'" style="background:#444;margin-right:8px;">
                🔑 Change Password
            </button>
            <button class="btn-add-user" id="show-register">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                Add User
            </button>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="users-toolbar">
        <div class="search-box">
            <svg class="search-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
            </svg>
            <input type="text" id="user-search" placeholder="Search by name, email or role…">
        </div>
        <button class="btn-bulk-delete" id="bulk-delete-btn" disabled>
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Delete Selected
        </button>
    </div>

    <!-- Users Table -->
    <div class="users-table-wrap">
        <table class="users-table" id="users-table">
            <thead>
            <tr>
                <th class="col-check"><input type="checkbox" id="selectAll"></th>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Pages (Access / Delete)</th>
                <th class="col-actions">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $pages = [
                'tickets_installation' => 'Tickets Install',
                'tickets' => 'All Tickets',
                'tickets_maintenance' => 'Maintenance',
                'tickets_pullout' => 'Pull Out',
                'service_areas' => 'Service Areas',
                'service_plans' => 'Service Plans',
                'inventory' => 'Inventory',
                'reports' => 'Reports',
                'users' => 'Users',
                'installation_form' => 'Install Form',
                'olt' => 'OLT'
            ];
            foreach ($users as $row):
                $name   = htmlspecialchars($row['name'], ENT_QUOTES);
                $email  = htmlspecialchars($row['email'], ENT_QUOTES);
                $role   = htmlspecialchars($row['role'], ENT_QUOTES);
                $roleCapitalized = ucfirst(strtolower($role)); // Capitalize first letter to match select options
                $initials = strtoupper(substr($row['name'], 0, 1));
                $badge  = $role_colors[$row['role']] ?? 'badge-default';
                $colorIndex = (crc32($row['name']) % 6 + 6) % 6;
            ?>
                <tr data-search="<?= strtolower($name . ' ' . $email . ' ' . $role) ?>">
                    <td class="col-check"><input type="checkbox" class="user-checkbox" value="<?= $row['userID'] ?>"></td>
                    <td>
                        <div class="user-cell">
                            <div class="avatar avatar-<?= $colorIndex ?>"><?= $initials ?></div>
                            <span class="user-fullname"><?= $name ?></span>
                        </div>
                    </td>
                    <td class="user-email"><?= $email ?></td>
                    <td><span class="role-badge <?= $badge ?>"><?= $role ?></span></td>
                    <td>
                        <div style="max-height:120px;overflow-y:auto;padding:4px;background:#f8f9fa;border-radius:4px;border:1px solid #e0e0e0;">
                            <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:4px;font-size:10px;">
                                <?php foreach ($pages as $pageKey => $pageLabel): ?>
                                    <div style="display:flex;align-items:center;gap:6px;padding:4px;background:#fff;border-radius:4px;">
                                        <input type="checkbox" id="perm_<?= $row['userID'] ?>_<?= $pageKey ?>"
                                            class="page-permission" data-userid="<?= $row['userID'] ?>" data-page="<?= $pageKey ?>" data-type="access"
                                            <?= ($row["can_$pageKey"] ?? 1) ? 'checked' : '' ?>
                                            onchange="updatePermission(<?= $row['userID'] ?>, '<?= $pageKey ?>', 'access', this.checked)"
                                            style="width:12px;height:12px;cursor:pointer;">
                                        <?php if (isset($modalPagesWithDelete[$pageKey])): ?>
                                            <input type="checkbox" id="perm_del_<?= $row['userID'] ?>_<?= $pageKey ?>"
                                                class="page-permission" data-userid="<?= $row['userID'] ?>" data-page="<?= $pageKey ?>" data-type="delete"
                                                <?= ($row["can_delete_$pageKey"] ?? 1) ? 'checked' : '' ?>
                                                onchange="updatePermission(<?= $row['userID'] ?>, '<?= $pageKey ?>', 'delete', this.checked)"
                                                style="width:12px;height:12px;cursor:pointer;">
                                        <?php endif; ?>
                                        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;color:#333;"><?= $pageLabel ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                    <td class="col-actions">
                        <button class="btn-action btn-edit"
                            onclick="openEditModal(<?= $row['userID'] ?>, '<?= $name ?>', '<?= $email ?>', '<?= $roleCapitalized ?>', {
                                'can_tickets_installation': <?= $row['can_tickets_installation'] ?? 1 ?>,
                                'can_delete_tickets_installation': <?= $row['can_delete_tickets_installation'] ?? 1 ?>,
                                'can_tickets': <?= $row['can_tickets'] ?? 1 ?>,
                                'can_delete_tickets': <?= $row['can_delete_tickets'] ?? 1 ?>,
                                'can_tickets_maintenance': <?= $row['can_tickets_maintenance'] ?? 1 ?>,
                                'can_delete_tickets_maintenance': <?= $row['can_delete_tickets_maintenance'] ?? 1 ?>,
                                'can_tickets_pullout': <?= $row['can_tickets_pullout'] ?? 1 ?>,
                                'can_delete_tickets_pullout': <?= $row['can_delete_tickets_pullout'] ?? 1 ?>,
                                'can_service_areas': <?= $row['can_service_areas'] ?? 1 ?>,
                                'can_delete_service_areas': <?= $row['can_delete_service_areas'] ?? 1 ?>,
                                'can_service_plans': <?= $row['can_service_plans'] ?? 1 ?>,
                                'can_delete_service_plans': <?= $row['can_delete_service_plans'] ?? 1 ?>,
                                'can_inventory': <?= $row['can_inventory'] ?? 1 ?>,
                                'can_delete_inventory': <?= $row['can_delete_inventory'] ?? 1 ?>,
                                'can_reports': <?= $row['can_reports'] ?? 1 ?>,
                                'can_delete_reports': <?= $row['can_delete_reports'] ?? 1 ?>,
                                'can_users': <?= $row['can_users'] ?? 1 ?>,
                                'can_delete_users': <?= $row['can_delete_users'] ?? 1 ?>,
                                'can_installation_form': <?= $row['can_installation_form'] ?? 1 ?>,
                                'can_delete_installation_form': <?= $row['can_delete_installation_form'] ?? 1 ?>,
                                'can_olt': <?= $row['can_olt'] ?? 1 ?>,
                                'can_delete_olt': <?= $row['can_delete_olt'] ?? 1 ?>
                            })">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            Edit
                        </button>
                        <button class="btn-action btn-delete"
                            onclick="deleteSingleUser(<?= $row['userID'] ?>, '<?= $name ?>')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            Delete
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" class="empty-state">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- Add User Modal -->
<div id="register-modal" class="modal <?= isActiveForm('register', $active_form) ?>">
    <div class="modal-content">
        <button class="modal-close" id="close-register" aria-label="Close">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
        <div class="modal-header">
            <div class="modal-icon modal-icon-add">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/></svg>
            </div>
            <h3>Add New User</h3>
        </div>
        <form action="login_register.php" method="post" class="modal-form">
            <?= showError($errors['register']) ?>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter full name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Set password" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="">— Select Role —</option>
                    <option value="Admin">Admin</option>
                    <option value="Employee">Employee</option>
                </select>
            </div>
            <div class="form-group">
                <label style="margin-bottom:8px;display:block;">Page Permissions <small style="color:#888;">(Access / Delete)</small></label>
                <div style="max-height:180px;overflow-y:auto;border:1px solid #ddd;border-radius:8px;padding:10px;background:#f8f9fa;">
                    <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:8px;font-size:12px;">
                        <?php foreach ($modalPages as $pageKey => $pageLabel): ?>
                            <div style="display:flex;align-items:center;gap:6px;padding:4px;background:#fff;border-radius:4px;">
                                <input type="checkbox" name="can_<?= $pageKey ?>" value="1" checked style="width:16px;height:16px;cursor:pointer;">
                                <?php if (isset($modalPagesWithDelete[$pageKey])): ?>
                                    <input type="checkbox" name="can_delete_<?= $pageKey ?>" value="1" checked style="width:16px;height:16px;cursor:pointer;">
                                <?php endif; ?>
                                <span style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333;"><?= $pageLabel ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button class="btn-submit" type="submit" name="register">Create User</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <button class="modal-close" id="close-edit" aria-label="Close">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
        <div class="modal-header">
            <div class="modal-icon modal-icon-edit">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
            </div>
            <h3>Edit User</h3>
        </div>
        <form id="edit-form" class="modal-form">
            <div id="edit-message"></div>
            <input type="hidden" id="edit-userID" name="userID">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="edit-name" name="name" placeholder="Full name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="edit-email" name="email" placeholder="Email" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="edit-role" name="role" required>
                    <option value="">— Select Role —</option>
                    <option value="Admin">Admin</option>
                    <option value="Employee">Employee</option>
                </select>
            </div>
            <div class="form-group">
                <label style="margin-bottom:8px;display:block;">Page Permissions <small style="color:#888;">(Access / Delete)</small></label>
                <div style="max-height:180px;overflow-y:auto;border:1px solid #ddd;border-radius:8px;padding:10px;background:#f8f9fa;">
                    <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:8px;font-size:12px;">
                        <?php foreach ($modalPages as $pageKey => $pageLabel): ?>
                            <div style="display:flex;align-items:center;gap:6px;padding:4px;background:#fff;border-radius:4px;">
                                <input type="checkbox" id="edit-can-<?= $pageKey ?>" name="can_<?= $pageKey ?>" value="1" checked style="width:16px;height:16px;cursor:pointer;">
                                <?php if (isset($modalPagesWithDelete[$pageKey])): ?>
                                    <input type="checkbox" id="edit-can-delete-<?= $pageKey ?>" name="can_delete_<?= $pageKey ?>" value="1" checked style="width:16px;height:16px;cursor:pointer;">
                                <?php endif; ?>
                                <span style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333;"><?= $pageLabel ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>New Password <span class="label-hint">(leave blank to keep current)</span></label>
                <input type="password" id="edit-password" name="password" placeholder="New password">
            </div>
            <button class="btn-submit" type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script src="dialog.js?v=1"></script>
<script src="notification_bar.js?v=11"></script>
<script src="db_events.js?v=1"></script>
<script src="user.js?v=3"></script>

<!-- ── Page Gate Modal ─────────────────────────────────────────────────── -->
<div id="gate-overlay" style="
    display: <?= $gateUnlocked ? 'none' : 'flex' ?> !important;
    position:fixed;inset:0;z-index:9999;
    background:rgba(0,0,0,0.85);
    align-items:center;justify-content:center;">
  <div style="
      background:#1e1e1e;border:1px solid #333;border-radius:12px;
      padding:36px 32px;width:100%;max-width:400px;box-shadow:0 8px 40px rgba(0,0,0,0.6);">

    <div style="text-align:center;margin-bottom:20px;">
      <div style="font-size:2rem;margin-bottom:8px;">🔒</div>
      <h2 style="color:#f0f0f0;margin:0 0 4px;font-size:1.2rem;">Users Page</h2>
      <p style="color:#888;font-size:13px;margin:0;">Enter the access password to continue.</p>
    </div>

    <!-- Unlock section -->
    <div id="gate-error" style="display:none;background:#c0392b22;border:1px solid #c0392b55;color:#e74c3c;
         padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>
    <div style="position:relative;margin-bottom:16px;">
      <input id="gate-password-input" type="password" placeholder="Access password"
        autocomplete="off"
        style="width:100%;box-sizing:border-box;padding:10px 40px 10px 14px;
               background:#2a2a2a;border:1px solid #444;border-radius:7px;
               color:#eee;font-size:14px;outline:none;"
        onkeydown="if(event.key==='Enter')submitGate()">
      <span onclick="toggleGatePw('gate-password-input',this)"
        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
               cursor:pointer;color:#888;font-size:13px;user-select:none;">👁</span>
    </div>
    <button onclick="submitGate()" style="
        width:100%;padding:11px;background:#c0392b;color:#fff;border:none;
        border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;margin-bottom:16px;">
      Unlock
    </button>

    <!-- Reset password -->
    <div style="border-top:1px solid #333;padding-top:14px;">
      <div style="position:relative;margin-bottom:10px;">
        <input id="reset-code-input" type="text" placeholder="Enter reset code"
          autocomplete="off"
          style="width:100%;box-sizing:border-box;padding:8px 12px;
                 background:#2a2a2a;border:1px solid #444;border-radius:6px;
                 color:#eee;font-size:13px;outline:none;">
      </div>
      <button onclick="resetGatePassword()" style="
          width:100%;background:transparent;color:#e67e22;border:1px solid #e67e22;
          padding:8px;border-radius:6px;font-size:12px;cursor:pointer;
          transition:background 0.2s;" onmouseover="this.style.background='#e67e22';this.style.color='#fff'"
          onmouseout="this.style.background='transparent';this.style.color='#e67e22'">
        🔄 Reset to Default (admin123)
      </button>
    </div>

  </div>
</div>

<script>
function submitGate() {
    const pw = document.getElementById('gate-password-input').value;
    const errEl = document.getElementById('gate-error');
    errEl.style.display = 'none';
    if (!pw) { errEl.textContent = 'Please enter the password.'; errEl.style.display = 'block'; return; }

    const fd = new FormData();
    fd.append('action', 'verify');
    fd.append('password', pw);

    fetch('page_gate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('gate-overlay').style.display = 'none';
            } else {
                errEl.textContent = data.message || 'Incorrect password.';
                errEl.style.display = 'block';
                document.getElementById('gate-password-input').value = '';
                document.getElementById('gate-password-input').focus();
            }
        });
}

function toggleGatePw(inputId, icon) {
    const inp = document.getElementById(inputId);
    if (inp.type === 'password') { inp.type = 'text'; icon.textContent = '🙈'; }
    else { inp.type = 'password'; icon.textContent = '👁'; }
}

function updatePermission(userID, page, type, value) {
    const fd = new FormData();
    fd.append('userID', userID);
    fd.append('page', page);
    fd.append('type', type);
    fd.append('value', value ? '1' : '0');

    fetch('update_permission.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Error updating permission: ' + data.message);
                // Revert checkbox
                const checkbox = document.querySelector(`[data-userid="${userID}"][data-page="${page}"][data-type="${type}"]`);
                if (checkbox) checkbox.checked = !value;
            } else {
                // Update body data attributes dynamically (SPA-like behavior)
                updateBodyPermissionAttributes();
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            const checkbox = document.querySelector(`[data-userid="${userID}"][data-page="${page}"][data-type="${type}"]`);
            if (checkbox) checkbox.checked = !value;
        });
}

function updateBodyPermissionAttributes() {
    // Fetch current permissions from database
    fetch('get_current_permissions.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.body.setAttribute('data-can-access', data.can_access);
                document.body.setAttribute('data-can-delete', data.can_delete);
                document.body.setAttribute('data-page', data.page);
            }
        })
        .catch(err => console.error('Error fetching permissions:', err));
}

function resetGatePassword() {
    const code = document.getElementById('reset-code-input').value;
    if (!code) {
        alert('Please enter the reset code');
        return;
    }
    if (!confirm('Reset gate password to default (admin123)?')) return;

    const fd = new FormData();
    fd.append('action', 'reset');
    fd.append('code', code);

    fetch('page_gate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Password reset to: admin123');
                document.getElementById('gate-password-input').value = '';
                document.getElementById('reset-code-input').value = '';
                document.getElementById('gate-password-input').focus();
            } else {
                alert('Error: ' + (data.message || 'Failed to reset password'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
}

// Clear any autofilled value and focus
window.addEventListener('load', function() {
    const overlay = document.getElementById('gate-overlay');
    if (overlay && overlay.style.display !== 'none') {
        const inp = document.getElementById('gate-password-input');
        inp.value = '';
        inp.focus();
    }
});
</script>

<!-- Change Password Modal (page header button) -->
<div id="change-password-modal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
  <div class="modal-content" style="max-width:380px;width:100%;">
    <button class="modal-close" onclick="document.getElementById('change-password-modal').style.display='none'" aria-label="Close">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </button>
    <div class="modal-header">
      <div class="modal-icon" style="background:#c0392b22;font-size:1.4rem;display:flex;align-items:center;justify-content:center;">🔑</div>
      <h3>Change Access Password</h3>
    </div>
    <div class="modal-form">
      <div id="cp-msg" style="display:none;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;"></div>
      <div class="form-group" style="position:relative;">
        <label>Current Password</label>
        <input id="cp-current" type="password" placeholder="Current password" autocomplete="new-password" style="padding-right:40px;">
        <span onclick="toggleGatePw('cp-current',this)" style="position:absolute;right:12px;top:38px;cursor:pointer;color:#888;font-size:13px;">👁</span>
      </div>
      <div class="form-group" style="position:relative;">
        <label>New Password</label>
        <input id="cp-new" type="password" placeholder="New password (min 6 chars)" autocomplete="new-password" style="padding-right:40px;">
        <span onclick="toggleGatePw('cp-new',this)" style="position:absolute;right:12px;top:38px;cursor:pointer;color:#888;font-size:13px;">👁</span>
      </div>
      <div class="form-group" style="position:relative;">
        <label>Confirm New Password</label>
        <input id="cp-confirm" type="password" placeholder="Confirm new password" autocomplete="new-password" style="padding-right:40px;">
        <span onclick="toggleGatePw('cp-confirm',this)" style="position:absolute;right:12px;top:38px;cursor:pointer;color:#888;font-size:13px;">👁</span>
      </div>
      <button class="btn-submit" onclick="submitPageChangePassword()">Save New Password</button>
    </div>
  </div>
</div>
<script>
function submitPageChangePassword() {
    const current = document.getElementById('cp-current').value;
    const newPw   = document.getElementById('cp-new').value;
    const confirm = document.getElementById('cp-confirm').value;
    const msgEl   = document.getElementById('cp-msg');

    const fd = new FormData();
    fd.append('action', 'change');
    fd.append('current_password', current);
    fd.append('new_password', newPw);
    fd.append('confirm_password', confirm);

    fetch('page_gate.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            msgEl.style.display = 'block';
            if (data.success) {
                msgEl.style.cssText = 'display:block;background:#27ae6022;border:1px solid #27ae6055;color:#58d68d;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;';
                msgEl.textContent = data.message;
                document.getElementById('cp-current').value = '';
                document.getElementById('cp-new').value = '';
                document.getElementById('cp-confirm').value = '';
                setTimeout(() => {
                    document.getElementById('change-password-modal').style.display = 'none';
                    msgEl.style.display = 'none';
                }, 1800);
            } else {
                msgEl.style.cssText = 'display:block;background:#c0392b22;border:1px solid #c0392b55;color:#e74c3c;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;';
                msgEl.textContent = data.message;
            }
        });
}
</script>
<script src="darkmode.js"></script>
</body>
</html>
