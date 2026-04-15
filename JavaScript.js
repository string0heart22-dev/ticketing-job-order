
(() => {
    // Elements
    
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    // Find all submenu toggles by class and their controlled submenu via aria-controls
    const submenuButtons = Array.from(document.querySelectorAll('.submenu-toggle'));

    // Sidebar state persistence key
    const SIDEBAR_STATE_KEY = 'ubilink_sidebar_open';

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
        // Save state
        localStorage.setItem(SIDEBAR_STATE_KEY, 'true');
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
        // Save state
        localStorage.setItem(SIDEBAR_STATE_KEY, 'false');
    }

    // Remove force-open class after page loads
    setTimeout(() => {
        document.documentElement.classList.remove('sidebar-force-open');
    }, 100);

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
        
        // Check if this is the tickets menu (should always stay open)
        const parent = btn.closest('.has-submenu');
        const isTicketsMenu = parent && parent.classList.contains('tickets-menu');
        
        // If it's the tickets menu, don't attach toggle behavior
        if (isTicketsMenu) {
            btn.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent navigation but don't toggle
            });
            return;
        }

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

    function showForm(formId) {
    
    document.getElementById(formId).classList.add("active");
    }
})();

/**
 * Assembles a full address string from the cascading address inputs.
 * @param {string} prefix - The ID prefix used for the form's address fields
 *   e.g. '' for installation_form, 'ticket_' for create ticket, 'maint_' for maintenance, 'pullout_' for pullout
 */
function getFullAddress(prefix) {
    const get = (id) => {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    };

    const purok    = get(prefix + 'purok_zone_input') || get(prefix + 'purok_zone');
    const barangay = get(prefix + 'barangay_input')   || get(prefix + 'barangay');
    const city     = get(prefix + 'city_input')        || get(prefix + 'city');
    const province = get(prefix + 'province_input')    || get(prefix + 'province');
    const zip      = get(prefix + 'zip_code');

    return [purok, barangay, city, province, zip].filter(v => v).join(', ');
}

// ── Presence heartbeat ───────────────────────────────────────────────────────
(function () {
    var PING_INTERVAL = 30000; // 30s
    var IDLE_TIMEOUT  = 120000; // 2 min idle = stop pinging
    var lastActivity  = Date.now();

    function resetActivity() { lastActivity = Date.now(); }

    ['mousemove', 'keydown', 'touchstart', 'click', 'scroll'].forEach(function (ev) {
        document.addEventListener(ev, resetActivity, { passive: true });
    });

    function ping() {
        if (Date.now() - lastActivity > IDLE_TIMEOUT) return;
        fetch('update_presence.php', { method: 'POST', credentials: 'same-origin' }).catch(function(){});
    }

    ping();
    setInterval(ping, PING_INTERVAL);
})();
