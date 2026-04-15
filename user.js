document.addEventListener('DOMContentLoaded', function() {
    // ── Modals ──────────────────────────────────────────
    const registerModal = document.querySelector('#register-modal');
    const editModal     = document.querySelector('#edit-modal');

    document.querySelector('#show-register')?.addEventListener('click', () => {
        registerModal.style.display = 'block';
    });
    document.querySelector('#close-register')?.addEventListener('click', () => {
        registerModal.style.display = 'none';
    });
    document.querySelector('#close-edit')?.addEventListener('click', () => {
        editModal.style.display = 'none';
    });
    window.addEventListener('click', e => {
        if (e.target === registerModal) registerModal.style.display = 'none';
        if (e.target === editModal)     editModal.style.display = 'none';
    });

    // ── Search ───────────────────────────────────────────
    document.getElementById('user-search')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#users-table tbody tr[data-search]').forEach(row => {
            row.style.display = row.dataset.search.includes(term) ? '' : 'none';
        });
    });

    // ── Checkboxes & Bulk Delete ─────────────────────────
    const selectAll    = document.getElementById('select-all');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    function syncBulkBtn() {
        const checked = document.querySelectorAll('.user-checkbox:checked').length;
        const total   = document.querySelectorAll('.user-checkbox').length;
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checked === 0;
            bulkDeleteBtn.innerHTML = checked > 0
                ? `<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg> Delete Selected (${checked})`
                : `<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg> Delete Selected`;
        }
        if (selectAll) selectAll.checked = total > 0 && checked === total;
    }

    selectAll?.addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
        syncBulkBtn();
    });
    document.addEventListener('change', e => {
        if (e.target.classList.contains('user-checkbox')) syncBulkBtn();
    });

    bulkDeleteBtn?.addEventListener('click', function() {
        const ids = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (!ids.length) return;
        Dialog.confirm(`Delete ${ids.length} user(s)? This cannot be undone.`, { type: 'danger', confirmText: 'Delete' }).then(ok => {
            if (!ok) return;
            bulkDeleteBtn.disabled = true;
            const fd = new FormData();
            ids.forEach(id => fd.append('userIDs[]', id));
            fetch('bulk_delete_users.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { Dialog.toast(data.message, 'success'); setTimeout(() => location.reload(), 900); }
                    else { Dialog.toast('Error: ' + data.message, 'error'); syncBulkBtn(); }
                })
                .catch(() => { Dialog.toast('An error occurred.', 'error'); syncBulkBtn(); });
        });
    });
});

(() => {
    // Elements
    
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    // Find all submenu toggles by class and their controlled submenu via aria-controls
    const submenuButtons = Array.from(document.querySelectorAll('.submenu-toggle'));

    // Helpers to open/close sidebar
    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        sidebar.classList.remove('collapsed');
        sidebar.setAttribute('aria-hidden', 'false');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        if (overlay) {
            overlay.classList.add('show');
            overlay.hidden = false;
        }
        const firstFocusable = sidebar.querySelector('button, a');
        if (firstFocusable) firstFocusable.focus();
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        sidebar.classList.add('collapsed');
        sidebar.setAttribute('aria-hidden', 'true');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (overlay) {
            overlay.classList.remove('show');
            // allow animation to finish before hiding
            setTimeout(() => overlay.hidden = true, 220);
        }
        if (toggle) toggle.focus();
        // collapse all submenus for tidy UX
        submenuButtons.forEach(btn => {
            const sub = getControlledSubmenu(btn);
            if (sub) collapseSubmenu(btn, sub, true);
        });
    }

    // Toggle sidebar when topbar button clicked
    if (toggle) {
        toggle.addEventListener('click', () => {
            if (sidebar && sidebar.classList.contains('open')) closeSidebar();
            else openSidebar();
        });
    }

    // clicking overlay closes sidebar
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Escape closes sidebar or any open submenu
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            // close open submenu(s) first
            let handled = false;
            submenuButtons.forEach(btn => {
                const sub = getControlledSubmenu(btn);
                if (sub && !sub.hidden) {
                    collapseSubmenu(btn, sub);
                    handled = true;
                }
            });
            if (!handled && sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        }
    });

    // Utilities for submenu elements
    function getControlledSubmenu(button) {
        const id = button.getAttribute('aria-controls');
        if (!id) return null;
        return document.getElementById(id);
    }

    function expandSubmenu(button, submenu) {
        button.setAttribute('aria-expanded', 'true');
        // mark parent for styling
        const parent = button.closest('.has-submenu');
        if (parent) parent.classList.add('open');

        submenu.hidden = false;
        // animate via max-height
        const height = submenu.scrollHeight;
        submenu.style.maxHeight = height + 'px';
        submenu.classList.add('show');
    }

    function collapseSubmenu(button, submenu, forceHide = false) {
        // if already hidden and not forced to show/hide, set aria and return
        if (!submenu || submenu.hidden) {
            button.setAttribute('aria-expanded', 'false');
            const parent = button.closest('.has-submenu');
            if (parent) parent.classList.remove('open');
            return;
        }

        button.setAttribute('aria-expanded', 'false');
        const parent = button.closest('.has-submenu');
        if (parent) parent.classList.remove('open');

        // animate closed
        submenu.style.maxHeight = submenu.scrollHeight + 'px';
        requestAnimationFrame(() => {
            submenu.style.maxHeight = '0px';
        });
        submenu.classList.remove('show');
        setTimeout(() => {
            submenu.hidden = true;
            submenu.style.maxHeight = '';
            if (forceHide) submenu.hidden = true;
        }, 240);
    }

    function toggleSubmenu(button) {
        const submenu = getControlledSubmenu(button);
        if (!submenu) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            collapseSubmenu(button, submenu);
        } else {
            // close others
            submenuButtons.forEach(b => {
                if (b === button) return;
                const s = getControlledSubmenu(b);
                if (s) collapseSubmenu(b, s);
            });
            expandSubmenu(button, submenu);
        }
    }

    // Attach events to submenu buttons
    submenuButtons.forEach(btn => {
        const submenu = getControlledSubmenu(btn);
        if (!submenu) return;

        // ensure ARIA initial state
        if (!btn.hasAttribute('aria-expanded')) btn.setAttribute('aria-expanded', 'false');
        submenu.hidden = submenu.hidden === undefined ? true : submenu.hidden;
        submenu.style.overflow = 'hidden';
        submenu.style.transition = `max-height ${getComputedStyle(document.documentElement).getPropertyValue('--transition-time') || '240ms'} ease`;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSubmenu(btn);
        });

        btn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSubmenu(btn);
            }
        });

        // Close submenu when focus leaves it
        document.addEventListener('focusin', (e) => {

            
            if (!submenu.contains(e.target) && !btn.contains(e.target) && !submenu.hidden) {
                setTimeout(() => {
                    if (!submenu.contains(document.activeElement) && !btn.contains(document.activeElement)) {
                        collapseSubmenu(btn, submenu);
                    }
                }, 10);
            }
        });
    });

    // Clicking sidebar links closes sidebar on narrow screens
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            const anchor = e.target.closest('a');
            if (!anchor) return;
            if (window.innerWidth <= 900) closeSidebar();
        });
    }
})();



// Single user delete
function deleteSingleUser(userID, name) {
    Dialog.confirm(`Delete user "${name}"? This cannot be undone.`, { type: 'danger', confirmText: 'Delete' }).then(ok => {
        if (!ok) return;
        const fd = new FormData();
        fd.append('userIDs[]', userID);
        fetch('bulk_delete_users.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { Dialog.toast('User deleted.', 'success'); setTimeout(() => location.reload(), 900); }
                else Dialog.toast('Error: ' + data.message, 'error');
            })
            .catch(() => Dialog.toast('An error occurred.', 'error'));
    });
}

// Edit Modal Functions
function openEditModal(userID, name, email, role, permissions = {}) {
    console.log('Opening edit modal for user:', userID, name, email, role);
    
    const modal = document.getElementById('edit-modal');
    const userIDInput = document.getElementById('edit-userID');
    const nameInput = document.getElementById('edit-name');
    const emailInput = document.getElementById('edit-email');
    const roleSelect = document.getElementById('edit-role');
    const passwordInput = document.getElementById('edit-password');
    const messageDiv = document.getElementById('edit-message');
    
    if (!modal) {
        console.error('Edit modal not found!');
        Dialog.error('Edit modal element not found in page');
        return;
    }
    
    if (!userIDInput || !nameInput || !emailInput || !roleSelect) {
        console.error('Edit modal form inputs not found!');
        Dialog.error('Edit modal form inputs not properly initialized');
        return;
    }
    
    userIDInput.value = userID;
    nameInput.value = name;
    emailInput.value = email;
    roleSelect.value = role || '';
    if (passwordInput) passwordInput.value = '';
    if (messageDiv) messageDiv.innerHTML = '';
    
    // Set page permission checkboxes
    const pages = ['tickets_installation', 'tickets', 'tickets_maintenance', 'tickets_pullout', 'service_areas', 'service_plans', 'inventory', 'reports', 'users', 'installation_form', 'olt'];
    pages.forEach(page => {
        const accessCheckbox = document.getElementById(`edit-can-${page}`);
        const deleteCheckbox = document.getElementById(`edit-can-delete-${page}`);
        if (accessCheckbox) accessCheckbox.checked = (permissions[`can_${page}`] ?? 1) == 1;
        if (deleteCheckbox) deleteCheckbox.checked = (permissions[`can_delete_${page}`] ?? 1) == 1;
    });
    
    // Force display with multiple methods
    modal.style.display = 'block';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.classList.add('active');
    
    console.log('Edit modal opened successfully');
    console.log('Modal display style:', modal.style.display);
    console.log('Modal computed display:', window.getComputedStyle(modal).display);
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing edit modal handlers');
    
    const closeEditBtn = document.getElementById('close-edit');
    const editForm = document.getElementById('edit-form');
    const editModal = document.getElementById('edit-modal');
    
    if (!editModal) {
        console.error('Edit modal not found in DOM!');
        return;
    }
    
    console.log('Edit modal found:', editModal);
    
    if (closeEditBtn) {
        closeEditBtn.addEventListener('click', function() {
            console.log('Close edit button clicked');
            editModal.style.display = 'none';
            editModal.classList.remove('active');
        });
    } else {
        console.error('Close edit button not found!');
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === editModal) {
            console.log('Clicked outside modal, closing');
            editModal.style.display = 'none';
            editModal.classList.remove('active');
        }
    });

    // Handle edit form submission
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Edit form submitted');
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('edit-message');
            
            // Log form data for debugging
            console.log('Form data:', {
                userID: formData.get('userID'),
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password') ? '***' : '(empty)'
            });
            
            messageDiv.innerHTML = '<p class="info">Updating user...</p>';
            
            fetch('edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    messageDiv.innerHTML = '<p class="success">' + data.message + '</p>';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    messageDiv.innerHTML = '<p class="error">' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                messageDiv.innerHTML = '<p class="error">An error occurred. Please try again. Check console for details.</p>';
            });
        });
    } else {
        console.error('Edit form not found!');
    }
});
