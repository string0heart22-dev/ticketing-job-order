<?php
/**
 * presence_menu.php — Employee online/offline status topbar dropdown.
 * Include this inside the <header class="topbar"> on admin pages.
 * Requires: session_init.php + config.php already loaded.
 */
?>
<div class="presence-menu" id="presenceMenu">
    <button class="presence-menu-btn" id="presenceMenuBtn" onclick="togglePresenceMenu()" aria-haspopup="true" aria-expanded="false">
        <span class="presence-dot online"></span>
        <span>Staff Status</span>
        <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor" style="margin-left:4px;opacity:.7"><path d="M1 3l4 4 4-4"/></svg>
    </button>
    <div class="presence-dropdown" id="presenceDropdown" style="display:none;">
        <div class="presence-dropdown-header">Employee Status</div>
        <div id="presence-list"><div class="presence-loading">Loading…</div></div>
    </div>
</div>

<style>
.presence-menu { position:relative; }
.presence-menu-btn {
    display:flex;align-items:center;gap:6px;
    background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);
    color:#fff;border-radius:6px;padding:6px 12px;font-size:13px;
    cursor:pointer;white-space:nowrap;
}
.presence-menu-btn:hover { background:rgba(255,255,255,0.18); }
.presence-dot {
    width:8px;height:8px;border-radius:50%;flex-shrink:0;
}
.presence-dot.online  { background:#22c55e; }
.presence-dot.idle    { background:#f59e0b; }
.presence-dot.offline { background:#6b7280; }
.presence-dropdown {
    position:absolute;top:calc(100% + 8px);right:0;
    background:#1e1e1e;border:1px solid #333;border-radius:10px;
    min-width:260px;max-height:360px;overflow-y:auto;
    box-shadow:0 8px 32px rgba(0,0,0,0.5);z-index:8000;
}
.presence-dropdown-header {
    padding:10px 14px 8px;font-size:11px;font-weight:700;
    color:#888;text-transform:uppercase;letter-spacing:.06em;
    border-bottom:1px solid #2a2a2a;
}
.presence-loading { padding:14px;color:#666;font-size:13px;text-align:center; }
.presence-item {
    display:flex;align-items:center;gap:10px;
    padding:9px 14px;border-bottom:1px solid #222;
}
.presence-item:last-child { border-bottom:none; }
.presence-avatar {
    width:30px;height:30px;border-radius:50%;
    background:#333;color:#ccc;font-size:12px;font-weight:700;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.presence-info { flex:1;min-width:0; }
.presence-name { color:#eee;font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.presence-time { color:#666;font-size:11px;margin-top:1px; }
.presence-status-badge {
    font-size:11px;font-weight:600;padding:2px 7px;border-radius:10px;flex-shrink:0;
}
.presence-status-badge.online  { background:#22c55e22;color:#22c55e; }
.presence-status-badge.idle    { background:#f59e0b22;color:#f59e0b; }
.presence-status-badge.offline { background:#6b728022;color:#9ca3af; }
</style>

<script>
(function(){
    let presenceOpen = false;

    window.togglePresenceMenu = function() {
        presenceOpen = !presenceOpen;
        const dd = document.getElementById('presenceDropdown');
        const btn = document.getElementById('presenceMenuBtn');
        dd.style.display = presenceOpen ? 'block' : 'none';
        btn.setAttribute('aria-expanded', presenceOpen);
        if (presenceOpen) loadPresence();
    };

    document.addEventListener('click', function(e) {
        if (!document.getElementById('presenceMenu').contains(e.target)) {
            presenceOpen = false;
            document.getElementById('presenceDropdown').style.display = 'none';
        }
    });

    function formatOffline(last_seen) {
        if (!last_seen) return 'Never seen';
        // last_seen is server local time — parse without Z to avoid UTC shift
        const diff = Math.floor((Date.now() - new Date(last_seen.replace(' ','T'))) / 1000);
        if (diff < 0)    return 'Just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ' + Math.floor((diff%3600)/60) + 'm ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    function loadPresence() {
        fetch('get_presence.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const list = document.getElementById('presence-list');
                if (!data.users.length) { list.innerHTML = '<div class="presence-loading">No employees found.</div>'; return; }
                list.innerHTML = data.users.map(u => {
                    const initial = u.name.charAt(0).toUpperCase();
                    const timeStr = u.status === 'online'
                        ? 'Active now'
                        : (u.status === 'idle' ? 'Idle · ' + formatOffline(u.last_seen) : 'Offline · ' + formatOffline(u.last_seen));
                    return `<div class="presence-item">
                        <div class="presence-avatar">${initial}</div>
                        <div class="presence-info">
                            <div class="presence-name">${u.name}</div>
                            <div class="presence-time">${timeStr}</div>
                        </div>
                        <span class="presence-status-badge ${u.status}">${u.status}</span>
                    </div>`;
                }).join('');
            });
    }

    // Auto-refresh every 30s while open
    setInterval(function(){ if(presenceOpen) loadPresence(); }, 30000);
})();
</script>
