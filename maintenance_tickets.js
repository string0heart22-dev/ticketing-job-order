// Maintenance Tickets SPA Controller

let maintAllTickets = [];
let maintCurrentTab = 'available';
let maintCurrentCity = '';
let maintSearchQuery = '';
let maintRefreshTimer = null;

function maintLoadTickets() {
    fetch('get_maintenance_list.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        maintAllTickets = data.tickets;
        maintUpdateCounts(data.counts);
        maintBuildCityButtons();
        maintRenderTable();
    })
    .catch(err => console.error('maintLoadTickets error:', err));
}

function maintBuildCityButtons() {
    const container = document.querySelector('.city-buttons-container');
    if (!container) return;

    const PROVINCES = ['isabela','cagayan','nueva vizcaya','quirino','aurora','ifugao','kalinga','apayao','abra','mountain province','benguet','ilocos norte','ilocos sur','la union','pangasinan'];
    const cityMap = {};
    maintAllTickets.forEach(t => {
        if (t.status !== 'Pending' && t.status !== 'In Progress') return;
        const addr = (t.address || '').trim();
        if (!addr) return;
        const parts = addr.split(',').map(s => s.trim()).filter(Boolean);
        if (/^\d+$/.test(parts[parts.length - 1])) parts.pop();
        if (parts.length > 0 && PROVINCES.includes(parts[parts.length - 1].toLowerCase())) parts.pop();
        while (parts.length > 0 && /^(purok|zone|blk|block|lot)\b/i.test(parts[0])) parts.shift();
        const city = parts.length > 0 ? parts[parts.length - 1] : null;
        if (!city) return;
        cityMap[city] = (cityMap[city] || 0) + 1;
    });

    const sorted = Object.entries(cityMap).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

    const allBtn = container.querySelector('.city-btn[data-city=""]');
    container.innerHTML = '';
    if (allBtn) container.appendChild(allBtn);

    sorted.forEach(([city, count]) => {
        const btn = document.createElement('button');
        btn.className = 'city-btn' + (maintCurrentCity === city ? ' active' : '');
        btn.dataset.city = city;
        btn.innerHTML = escMaintHtml(city) + ' <span class="city-count">' + count + '</span>';
        btn.addEventListener('click', function () {
            maintCurrentCity = city;
            document.querySelectorAll('.city-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            maintRenderTable();
        });
        container.appendChild(btn);
    });

    const allBtnFinal = container.querySelector('.city-btn[data-city=""]');
    if (allBtnFinal) {
        allBtnFinal.onclick = function () {
            maintCurrentCity = '';
            document.querySelectorAll('.city-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            maintRenderTable();
        };
        if (!maintCurrentCity) allBtnFinal.classList.add('active');
    }
}

function maintUpdateCounts(counts) {
    const map = {
        'available':   'mnt-cnt-available',
        'In Progress': 'mnt-cnt-In Progress',
        'Completed':   'mnt-cnt-Completed',
        'Cancelled':   'mnt-cnt-Cancelled',
        'On Hold':     'mnt-cnt-On Hold',
        'all':         'mnt-cnt-all'
    };
    for (const [key, id] of Object.entries(map)) {
        const el = document.getElementById(id);
        if (el) el.textContent = counts[key] ?? 0;
    }
}

function maintGetFiltered() {
    return maintAllTickets.filter(t => {
        if (maintCurrentTab === 'available') {
            if (t.status !== 'Pending') return false;
            if (t.assigned_to && parseInt(t.assigned_to) !== 0) return false;
        } else if (maintCurrentTab !== 'all') {
            if (t.status !== maintCurrentTab) return false;
        }

        if (maintCurrentCity) {
            if (!(t.address || '').toLowerCase().includes(maintCurrentCity.toLowerCase())) return false;
        }

        if (maintSearchQuery) {
            const q = maintSearchQuery.toLowerCase();
            const hay = [t.client_name, t.address, t.contact_number, t.ticket_number, t.issue_type, t.description]
                .map(v => (v || '').toLowerCase()).join(' ');
            if (!hay.includes(q)) return false;
        }

        return true;
    });
}

function maintRenderTable() {
    const tbody = document.getElementById('maint-tbody');
    const mobileCards = document.getElementById('maint-mobile-cards');
    if (!tbody) return;

    const filtered = maintGetFiltered();

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-data">No tickets found.</td></tr>';
        if (mobileCards) mobileCards.innerHTML = '<div class="no-data" style="padding:30px;text-align:center;color:#6b7280;">No tickets found.</div>';
        return;
    }

    const role   = (window.currentUserRole || '').toLowerCase();
    const userId = parseInt(window.currentUserId || 0);

    tbody.innerHTML = filtered.map(t => {
        const isAssigned  = t.assigned_to && parseInt(t.assigned_to) !== 0;
        const isMine      = parseInt(t.assigned_to) === userId;
        const isAvailable = t.status === 'Pending' && !isAssigned;

        const priority = t.priority || 'Normal';
        const priorityClass = priority === 'Urgent' ? 'priority-urgent'
                            : priority === 'Less Priority' ? 'priority-low'
                            : 'priority-normal';
        const statusClass = 'status-' + (t.status || '').toLowerCase().replace(/ /g, '-');

        const isScheduled = t.status === 'Pending' && t.scheduled_date && new Date(t.scheduled_date + 'T00:00:00') > new Date();
        let actionBtns = isScheduled
            ? `<button class="btn-icon btn-view" disabled title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`
            : `<button class="btn-icon btn-view" onclick="viewMaintenanceDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
        if (isAvailable && (role === 'technician' || role === 'folder')) {
            actionBtns += ` <button class="btn-claim" onclick="claimMaintenanceDirect(${t.id})">Claim</button>`;
        }
        if (role === 'admin' || role === 'noc' || role === 'cs') {
            actionBtns += ` <button class="btn-assign" onclick="openAssignMaintenanceModal(${t.id}, '${escMaintHtml(t.client_name)}')">Assign</button>`;
        }

        const desc = escMaintHtml((t.description || '').substring(0, 50)) + ((t.description || '').length > 50 ? '...' : '');
        const assigned = escMaintHtml(t.assigned_name || 'Unassigned');
        const overlayRow = isScheduled
            ? `<tr class="sched-overlay-row"><td colspan="9"><div class="sched-overlay-inner">📅 SCHEDULED TO ${escMaintHtml(t.scheduled_date)}</div></td></tr>`
            : '';

        // Get display date based on status
        const displayInfo = getDisplayDate(t);
        const dateLabel = displayInfo.label;
        const dateValue = formatDateTime(displayInfo.date);

        return `<tr data-priority="${escMaintHtml(priority)}" data-status="${escMaintHtml(t.status)}">
            <td><div class="action-buttons">${actionBtns}</div></td>
            <td>${escMaintHtml(t.ticket_number)}</td>
            <td>${escMaintHtml(t.issue_type)}</td>
            <td>${escMaintHtml(t.client_name)}</td>
            <td>${escMaintHtml(t.address || '')}</td>
            <td>${escMaintHtml(t.contact_number)}</td>
            <td>${desc}</td>
            <td>
                <span class="priority-badge ${priorityClass}">${escMaintHtml(priority)}</span>
                <span class="status-badge ${statusClass}">${escMaintHtml(t.status)}</span>
                <div style="font-size:0.8rem;color:#6b7280;margin-top:4px;">👤 ${assigned}</div>
            </td>
            <td><small>${dateLabel}:</small><br>${dateValue}</td>
        </tr>${overlayRow}`;
    }).join('');

    // Fix overlay heights to match their target rows
    requestAnimationFrame(() => fixSchedOverlays(tbody));

    if (mobileCards) {
        mobileCards.innerHTML = filtered.map(t => {
            const isAssigned  = t.assigned_to && parseInt(t.assigned_to) !== 0;
            const isMine      = parseInt(t.assigned_to) === userId;
            const isAvailable = t.status === 'Pending' && !isAssigned;
            const priority = t.priority || 'Normal';
            const priorityClass = priority === 'Urgent' ? 'priority-urgent' : priority === 'Less Priority' ? 'priority-low' : 'priority-normal';
            const statusClass = 'status-' + (t.status || '').toLowerCase().replace(/ /g, '-');
            const desc = escMaintHtml((t.description || '').substring(0, 80)) + ((t.description || '').length > 80 ? '...' : '');

            const isScheduled = t.status === 'Pending' && t.scheduled_date && new Date(t.scheduled_date + 'T00:00:00') > new Date();
            const scheduledOverlay = isScheduled
                ? `<div class="mobile-sched-overlay">📅 SCHEDULED TO ${escMaintHtml(t.scheduled_date)}</div>`
                : '';
            let actionBtns = isScheduled
                ? `<button class="btn-icon btn-view" disabled title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`
                : `<button class="btn-icon btn-view" onclick="viewMaintenanceDetails(${t.id})" title="View"><img src="eye-icon.svg?v=1" width="24" height="24" alt="View"></button>`;
            if (isAvailable && (role === 'technician' || role === 'folder')) {
                actionBtns += ` <button class="btn-claim" onclick="claimMaintenanceDirect(${t.id})">Claim</button>`;
            }
            if (role === 'admin' || role === 'noc' || role === 'cs') {
                actionBtns += ` <button class="btn-assign" onclick="openAssignMaintenanceModal(${t.id}, '${escMaintHtml(t.client_name)}')">Assign</button>`;
            }

            return `<div class="mobile-ticket-card type-maintenance">
                ${scheduledOverlay}
                <div class="mobile-card-header">
                    <span class="mobile-ticket-number">${escMaintHtml(t.ticket_number)}</span>
                    <span class="type-badge type-maintenance">Maintenance</span>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-info-row"><span class="mobile-info-label">Client</span><span class="mobile-info-value">${escMaintHtml(t.client_name)}</span></div>
                    <div class="mobile-info-row"><span class="mobile-info-label">Issue</span><span class="mobile-info-value">${escMaintHtml(t.issue_type)}</span></div>
                    <div class="mobile-info-row"><span class="mobile-info-label">Address</span><span class="mobile-info-value">${escMaintHtml(t.address || '')}</span></div>
                    <div class="mobile-info-row"><span class="mobile-info-label">Contact</span><span class="mobile-info-value">${escMaintHtml(t.contact_number)}</span></div>
                    ${desc ? `<div class="mobile-info-row"><span class="mobile-info-label">Desc</span><span class="mobile-info-value">${desc}</span></div>` : ''}
                </div>
                <div class="mobile-card-footer">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                        <span class="priority-badge ${priorityClass}">${escMaintHtml(priority)}</span>
                        <span class="status-badge ${statusClass}">${escMaintHtml(t.status)}</span>
                        <span style="font-size:0.75rem;color:#6b7280;">👤 ${escMaintHtml(t.assigned_name || 'Unassigned')}</span>
                    </div>
                    <div class="mobile-card-actions">${actionBtns}</div>
                </div>
            </div>`;
        }).join('');
    }
}

function escMaintHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Format date/time for display
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

// Get display date based on status - created for pending/progress, completed for finished
function getDisplayDate(ticket) {
    const completedStatuses = ['Completed', 'Closed', 'Cancelled', 'On Hold', 'Installed'];
    if (completedStatuses.includes(ticket.status) && ticket.completed_time) {
        return { date: ticket.completed_time, label: 'Completed' };
    }
    return { date: ticket.created_at, label: 'Created' };
}

function fixSchedOverlays(tbody) {
    const overlayRows = tbody.querySelectorAll('tr.sched-overlay-row');
    overlayRows.forEach(overlayTr => {
        const prevTr = overlayTr.previousElementSibling;
        if (!prevTr) return;
        const h = prevTr.getBoundingClientRect().height;
        const inner = overlayTr.querySelector('.sched-overlay-inner');
        if (inner) {
            inner.style.height = h + 'px';
            inner.style.marginTop = '-' + h + 'px';
            inner.style.transform = 'none';
        }
    });
}

function resetTicketFilters() {
    maintCurrentTab  = 'available';
    maintCurrentCity = '';
    maintSearchQuery = '';

    document.querySelectorAll('#maint-status-tabs .tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.status === 'available');
    });
    document.querySelectorAll('.city-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.city === '');
    });
    const s = document.getElementById('search-input');
    if (s) s.value = '';

    maintRenderTable();
}

// Direct claim from table row (for technician/folder)
function claimMaintenanceDirect(ticketId) {
    showConfirm('Claim this ticket? It will be assigned to you and set to In Progress.', function () {
        _doClaimMaintenanceDirect(ticketId);
    }, { okText: 'Claim', icon: '🔧' });
}
function _doClaimMaintenanceDirect(ticketId) {
    const fd = new FormData();
    fd.append('ticket_id', ticketId);
    fd.append('ticket_type', 'maintenance');

    fetch('claim_ticket.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) maintLoadTickets();
        else showAlert('Error: ' + data.message);
    })
    .catch(err => console.error(err));
}

document.addEventListener('DOMContentLoaded', function () {
    // Tab clicks
    document.querySelectorAll('#maint-status-tabs .tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            maintCurrentTab = this.dataset.status;
            document.querySelectorAll('#maint-status-tabs .tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            maintRenderTable();
        });
    });

    // City filter
    document.querySelectorAll('.city-btn[data-city]').forEach(btn => {
        btn.addEventListener('click', function () {
            maintCurrentCity = this.dataset.city;
            document.querySelectorAll('.city-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            maintRenderTable();
        });
    });

    // Search
    const searchEl = document.getElementById('search-input');
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            maintSearchQuery = this.value.trim();
            maintRenderTable();
        });
    }

    maintLoadTickets();
    maintRefreshTimer = setInterval(maintLoadTickets, 30000);
});
