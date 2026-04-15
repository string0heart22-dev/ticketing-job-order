// Permission checking system
// This file should be included on all pages that need permission checking

// Add CSS rules based on body data attributes for reliable permission enforcement
(function() {
    const style = document.createElement('style');
    style.textContent = `
        /* When user doesn't have access permission */
        body[data-can-access="0"] .btn-delete,
        body[data-can-access="0"] .btn-create,
        body[data-can-access="0"] .btn-edit,
        body[data-can-access="0"] .btn-assign,
        body[data-can-access="0"] .btn-subscription,
        body[data-can-access="0"] .btn-create-ticket,
        body[data-can-access="0"] button[onclick*="create"],
        body[data-can-access="0"] button[onclick*="edit"],
        body[data-can-access="0"] button[onclick*="assign"],
        body[data-can-access="0"] button[onclick*="subscription"],
        body[data-can-access="0"] button[onclick*="generate"],
        body[data-can-access="0"] button[onclick*="upload"],
        body[data-can-access="0"] button[onclick*="agreement"],
        body[data-can-access="0"] button[title*="Subscription"],
        body[data-can-access="0"] button[title*="Generate"],
        body[data-can-access="0"] button[title*="Agreement"],
        body[data-can-access="0"] input[type="file"],
        body[data-can-access="0"] select[name*="status"],
        body[data-can-access="0"] .btn-save,
        body[data-can-access="0"] .btn-submit,
        body[data-can-access="0"] button[type="submit"] {
            pointer-events: none !important;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* When user doesn't have delete permission */
        body[data-can-delete="0"] .btn-delete,
        body[data-can-delete="0"] button[onclick*="delete"],
        body[data-can-delete="0"] button[title*="Delete"],
        body[data-can-delete="0"] #bulk-delete-btn,
        body[data-can-delete="0"] #delete-selected-btn {
            pointer-events: none !important;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }
    `;
    document.head.appendChild(style);
})();

// Get current page name from URL
function getCurrentPageName() {
    const path = window.location.pathname;
    const filename = path.split('/').pop().replace('.php', '');
    
    // Map page names to permission keys
    const pageMap = {
        'tickets_installation': 'tickets_installation',
        'tickets': 'tickets',
        'tickets_maintenance': 'tickets_maintenance',
        'tickets_pullout': 'tickets_pullout',
        'service_areas': 'service_areas',
        'service_plans': 'service_plans',
        'inventory': 'inventory',
        'reports': 'reports',
        'USERs': 'users',
        'installation_form': 'installation_form',
        'olt': 'olt'
    };
    
    return pageMap[filename] || filename;
}

// Check if user has access permission for current page
function hasPageAccess() {
    const page = getCurrentPageName();
    // Permissions are stored in PHP session, we'll need to check via PHP
    // For now, we'll use a data attribute on the body that PHP sets
    const access = document.body.getAttribute('data-can-access');
    return access === '1';
}

// Check if user has delete permission for current page
function hasDeletePermission() {
    const page = getCurrentPageName();
    const canDelete = document.body.getAttribute('data-can-delete');
    return canDelete === '1';
}

// Track if we've already shown the message to avoid duplicates
let readOnlyMessageShown = false;

// Disable all action buttons based on permissions
function applyPermissions() {
    try {
        const canAccess = hasPageAccess();
        const canDelete = hasDeletePermission();

        // Don't disable sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');

        if (!canAccess) {
            // Disable Create Ticket buttons
            const createTicketBtns = document.querySelectorAll('.btn-create-ticket, button[onclick*="createTicket"], #create-ticket-btn, .btn-create, button[id*="create"], button[title*="Create"], .btn-add');
            createTicketBtns.forEach(btn => {
                if (btn !== sidebarToggle) {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                    btn.title = 'You do not have permission to create';
                }
            });

            // Disable Assign buttons
            const assignButtons = document.querySelectorAll('.btn-assign, button[onclick*="assign"], .assign-btn, button[title*="Assign"]');
            assignButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.title = 'You do not have permission to assign';
            });

            // Disable Edit buttons
            const editButtons = document.querySelectorAll('.btn-edit, button[onclick*="edit"], button[onclick*="Edit"], .edit-btn, button[title*="Edit"]');
            editButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.title = 'You do not have permission to edit';
            });

            // Disable Generate Subscription buttons
            const genSubButtons = document.querySelectorAll('.btn-generate-sub, button[onclick*="subscription"], button[onclick*="generate"], button[title*="Generate"]');
            genSubButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.title = 'You do not have permission';
            });

            // Disable Upload Pic buttons
            const uploadButtons = document.querySelectorAll('.btn-upload, button[onclick*="upload"], button[onclick*="image"], input[type="file"], button[title*="Upload"]');
            uploadButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                if (btn.type === 'file') btn.style.display = 'none';
            });

            // Disable Status dropdowns
            const statusDropdowns = document.querySelectorAll('select[name*="status"], .status-dropdown, #status-select, .status-select, .dropdown');
            statusDropdowns.forEach(dropdown => {
                dropdown.disabled = true;
                dropdown.style.opacity = '0.5';
                dropdown.style.cursor = 'not-allowed';
            });

            // Disable Save/Submit buttons in modals
            const saveButtons = document.querySelectorAll('.btn-save, .btn-submit, button[type="submit"], button[onclick*="save"], button[onclick*="submit"]');
            saveButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.title = 'You do not have permission to save';
            });

            // Show read-only message (only once)
            if (!readOnlyMessageShown) {
                showReadOnlyMessage();
                readOnlyMessageShown = true;
            }
        }

        if (!canDelete) {
            // Disable delete buttons specifically
            const deleteButtons = document.querySelectorAll('.btn-delete, .btn-action.btn-delete, button[onclick*="delete"], button[onclick*="Delete"], button[title*="Delete"]');
            deleteButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.title = 'You do not have delete permission';
            });

            // Disable bulk delete buttons (both IDs)
            const bulkDelete = document.getElementById('bulk-delete-btn') || document.getElementById('delete-selected-btn');
            if (bulkDelete) {
                bulkDelete.disabled = true;
                bulkDelete.style.opacity = '0.5';
                bulkDelete.style.cursor = 'not-allowed';
            }
        }
    } catch (e) {
        console.error('Error applying permissions:', e);
    }
}

// Show read-only message
function showReadOnlyMessage() {
    const message = document.createElement('div');
    message.style.cssText = 'position:fixed;top:80px;right:20px;background:#f39c12;color:#fff;padding:12px 20px;border-radius:6px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.2);font-weight:500;';
    message.innerHTML = '⚠️ Read-Only Mode: You do not have permission to edit on this page';
    document.body.appendChild(message);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        message.style.transition = 'opacity 0.5s';
        message.style.opacity = '0';
        setTimeout(() => message.remove(), 500);
    }, 5000);
}

// Apply permissions on page load - wait for other scripts to finish first
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(applyPermissions, 100));
} else {
    setTimeout(applyPermissions, 100);
}
