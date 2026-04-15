// Patch: Save address to hidden field before form submit for all ticket modals
// Uses capture phase (true) to run before all other handlers and stop propagation.

document.addEventListener('DOMContentLoaded', function() {

    function assembleAddress(prefix) {
        var get = function(id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : '';
        };
        var purok    = get(prefix + 'purok_zone_input') || get(prefix + 'purok_zone');
        var barangay = get(prefix + 'barangay_input')   || get(prefix + 'barangay');
        var city     = get(prefix + 'city_input')       || get(prefix + 'city');
        var province = get(prefix + 'province_input')   || get(prefix + 'province');
        var zip      = get(prefix + 'zip_code');
        return [purok, barangay, city, province, zip].filter(function(v) { return v; }).join(', ');
    }

    // Shared error/warning/duplicate handler
    function handleTicketError(data, formEl) {
        var SC = {
            'Pending':     { bg:'#f59e0b22', bd:'#f59e0b55', tx:'#f59e0b' },
            'In Progress': { bg:'#3b82f622', bd:'#3b82f655', tx:'#60a5fa' },
            'Completed':   { bg:'#22c55e22', bd:'#22c55e55', tx:'#22c55e' },
            'Closed':      { bg:'#22c55e22', bd:'#22c55e55', tx:'#22c55e' },
            'Cancelled':   { bg:'#6b728022', bd:'#6b728055', tx:'#9ca3af' },
        };
        var s = data.status || 'Pending';
        var c = SC[s] || SC['Pending'];
        var card = '<div style="display:flex;align-items:center;gap:10px;background:#111;border:1px solid #333;border-radius:8px;padding:10px 14px;margin-top:12px;">'
            + '<span style="font-size:13px;font-weight:600;color:#e5e7eb;letter-spacing:.03em;">' + (data.ticket || '') + '</span>'
            + '<span style="margin-left:auto;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:' + c.bg + ';border:1px solid ' + c.bd + ';color:' + c.tx + ';">' + s + '</span>'
            + '</div>';

        if (data.duplicate) {
            Dialog.confirm((data.message || 'Duplicate ticket.') + card, {
                title: '⚠ Duplicate Ticket',
                type: 'warning',
                okText: 'Proceed Anyway',
                cancelText: 'Cancel'
            }).then(function(ok) {
                if (ok && formEl) {
                    var fi = formEl.querySelector('[name="force_create"]');
                    if (!fi) {
                        fi = document.createElement('input');
                        fi.type = 'hidden';
                        fi.name = 'force_create';
                        formEl.appendChild(fi);
                    }
                    fi.value = '1';
                    formEl.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            });
        } else if (data.warning) {
            var wcard = '<div style="display:flex;align-items:center;gap:10px;background:#111;border:1px solid #333;border-radius:8px;padding:10px 14px;margin-top:12px;">'
                + '<div><div style="font-size:13px;font-weight:600;color:#e5e7eb;">' + (data.ticket || '') + '</div>'
                + '<div style="font-size:11px;color:#888;margin-top:2px;">Created: ' + (data.created || '') + '</div></div>'
                + '<span style="margin-left:auto;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:' + c.bg + ';border:1px solid ' + c.bd + ';color:' + c.tx + ';">' + s + '</span>'
                + '</div>';
            Dialog.confirm((data.message || 'Pending ticket exists.') + wcard, {
                title: '⚠ Pending Ticket Exists',
                type: 'warning',
                okText: 'Create Anyway',
                cancelText: 'Cancel'
            }).then(function(ok) {
                if (ok && formEl) {
                    var fi = formEl.querySelector('[name="force_create"]');
                    if (!fi) {
                        fi = document.createElement('input');
                        fi.type = 'hidden';
                        fi.name = 'force_create';
                        formEl.appendChild(fi);
                    }
                    fi.value = '1';
                    formEl.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            });
        } else {
            Dialog.error(data.message || 'Failed to create ticket.');
        }
    }

    // ── Installation Ticket ───────────────────────────────────────────────────
    var createTicketForm = document.getElementById('create-ticket-form');
    if (createTicketForm) {
        createTicketForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var addrEl = document.getElementById('ticket_address');
            if (addrEl) addrEl.value = assembleAddress('ticket_');

            var submitBtn = createTicketForm.querySelector('[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating...'; }

            fetch('process_installation.php', { method: 'POST', body: new FormData(createTicketForm) })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        var modal = document.getElementById('create-ticket-modal');
                        if (modal) modal.style.display = 'none';
                        createTicketForm.reset();
                        if (typeof triggerInstallationRefresh === 'function') triggerInstallationRefresh();
                        else window.location.reload();
                    } else {
                        handleTicketError(data, createTicketForm);
                    }
                })
                .catch(function() {
                    Dialog.error('Network error. Please try again.');
                })
                .finally(function() {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Ticket'; }
                });
        }, true); // capture phase
    }

    // ── Maintenance Ticket ────────────────────────────────────────────────────
    var createMaintenanceForm = document.getElementById('create-maintenance-form');
    if (createMaintenanceForm) {
        createMaintenanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var addrEl = document.getElementById('maint_address');
            if (addrEl) addrEl.value = assembleAddress('maint_');

            var submitBtn = createMaintenanceForm.querySelector('[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating...'; }

            fetch('process_maintenance.php', { method: 'POST', body: new FormData(createMaintenanceForm) })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        var modal = document.getElementById('create-maintenance-modal');
                        if (modal) modal.style.display = 'none';
                        createMaintenanceForm.reset();
                        if (typeof triggerMaintenanceRefresh === 'function') triggerMaintenanceRefresh();
                        else window.location.reload();
                    } else {
                        handleTicketError(data, createMaintenanceForm);
                    }
                })
                .catch(function() {
                    Dialog.error('Network error. Please try again.');
                })
                .finally(function() {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Ticket'; }
                });
        }, true); // capture phase
    }

    // ── Pull Out Ticket ───────────────────────────────────────────────────────
    var createPulloutForm = document.getElementById('create-pullout-form');
    if (createPulloutForm) {
        createPulloutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var addrEl = document.getElementById('pullout_address');
            if (addrEl) addrEl.value = assembleAddress('pullout_');

            var submitBtn = createPulloutForm.querySelector('[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating...'; }

            fetch('process_pullout.php', { method: 'POST', body: new FormData(createPulloutForm) })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        var modal = document.getElementById('create-pullout-modal');
                        if (modal) modal.style.display = 'none';
                        createPulloutForm.reset();
                        if (typeof triggerPulloutRefresh === 'function') triggerPulloutRefresh();
                        else if (typeof triggerUnifiedRefresh === 'function') triggerUnifiedRefresh(true);
                        else window.location.reload();
                    } else {
                        handleTicketError(data, createPulloutForm);
                    }
                })
                .catch(function() {
                    Dialog.error('Network error. Please try again.');
                })
                .finally(function() {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Ticket'; }
                });
        }, true); // capture phase
    }

    // ── Edit Installation Ticket (address only) ───────────────────────────────
    var editTicketForm = document.getElementById('edit-ticket-form');
    if (editTicketForm) {
        editTicketForm.addEventListener('submit', function() {
            var addrEl = document.getElementById('edit_address');
            if (addrEl) addrEl.value = assembleAddress('edit_');
        });
    }
});
