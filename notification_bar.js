/**
 * Notification Bell - renders inside .controls in the topbar
 * Supports desktop (browser) push notifications for new in-progress tickets
 */
class NotificationBar {
    constructor() {
        this.isOpen = false;
        this.refreshInterval = 3 * 1000; // poll every 3s for near-instant updates
        this.tickets = [];
        this.knownTicketNumbers = new Set(); // track already-notified tickets
        this.isFirstLoad = true;
        this.init();
    }

    init() {
        this.injectUI();
        this.attachEventListeners();
        this.requestDesktopPermission();
        this.loadNotifications();
        setInterval(() => this.loadNotifications(), this.refreshInterval);
    }

    // ── Desktop notification permission ──────────────────────────────────────

    requestDesktopPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            // Ask on first user interaction to avoid browser blocking auto-prompts
            const askOnce = () => {
                Notification.requestPermission();
                document.removeEventListener('click', askOnce);
            };
            document.addEventListener('click', askOnce);
        }
    }

    sendDesktopNotification(ticket) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;

        const icon = ticket.type === 'Installation' ? '📦'
                   : ticket.type === 'Maintenance'  ? '🔧' : '🔌';

        const n = new Notification(`${icon} ${ticket.type} — In Progress`, {
            body: `${ticket.ticket_number}  •  ${ticket.client_name}\nAssigned: ${ticket.assigned_to || 'Unassigned'}`,
            icon: 'favicon-96x96.png',
            tag:  ticket.ticket_number, // prevents duplicate toasts for same ticket
        });

        // Clicking the desktop notification focuses the tab
        n.onclick = () => {
            window.focus();
            const typeKey = ticket.type.toLowerCase().replace(' ', '');
            const link = typeKey === 'installation' ? 'tickets_installation.php'
                       : typeKey === 'maintenance'  ? 'tickets_maintenance.php'
                       : 'tickets_pullout.php';
            const currentPage = window.location.pathname.split('/').pop();
            if (currentPage !== link) window.location.href = link;
            n.close();
        };

        // Auto-close after 8 seconds
        setTimeout(() => n.close(), 8000);
    }

    // ── UI injection ──────────────────────────────────────────────────────────

    injectUI() {
        const controls = document.querySelector('.controls');
        if (!controls) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'notif-bell-wrapper';
        wrapper.innerHTML = `
            <button class="notification-toggle-btn" id="notificationToggleBtn" title="In Progress Tickets">&#128276;</button>
            <div class="notification-bar" id="notificationBar">
                <div class="notification-bar-header">
                    <span>In Progress Tickets</span>
                    <button class="close-btn" id="closeNotificationBtn">&#10005;</button>
                </div>
                <div class="notification-bar-content" id="notificationContent">
                    <div class="notification-empty">Loading...</div>
                </div>
            </div>
        `;

        // Insert bell after user-profile (rightmost in the topbar)
        const userProfile = controls.querySelector('.user-profile');
        if (userProfile && userProfile.nextSibling) {
            controls.insertBefore(wrapper, userProfile.nextSibling);
        } else {
            controls.appendChild(wrapper);
        }
    }

    attachEventListeners() {
        const toggleBtn = document.getElementById('notificationToggleBtn');
        const closeBtn  = document.getElementById('closeNotificationBtn');
        if (!toggleBtn) return;

        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.isOpen ? this.closeBar() : this.openBar();
        });

        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.closeBar();
        });

        document.addEventListener('click', (e) => {
            const bar = document.getElementById('notificationBar');
            if (this.isOpen && bar && !bar.contains(e.target) && e.target !== toggleBtn) {
                this.closeBar();
            }
        });
    }

    openBar() {
        const bar = document.getElementById('notificationBar');
        if (!bar) return;
        this.isOpen = true;
        bar.classList.add('show');
        this.renderList();
    }

    closeBar() {
        const bar = document.getElementById('notificationBar');
        if (!bar) return;
        this.isOpen = false;
        bar.classList.remove('show');
    }

    // ── Data loading ──────────────────────────────────────────────────────────

    loadNotifications() {
        // Use relative path for cross-environment compatibility
        fetch('get_in_progress_notifications.php')
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                if (!data.success) {
                    console.error('Notification API returned error:', data.message || 'Unknown error');
                    return;
                }
                const incoming = data.tickets || [];

                // Detect NEW tickets (not seen before) — skip on very first load
                // so we don't spam notifications for all existing in-progress tickets
                if (!this.isFirstLoad) {
                    incoming.forEach(t => {
                        if (!this.knownTicketNumbers.has(t.ticket_number)) {
                            this.sendDesktopNotification(t);
                        }
                    });
                }

                // Update known set
                this.knownTicketNumbers = new Set(incoming.map(t => t.ticket_number));
                this.tickets = incoming;
                this.isFirstLoad = false;

                this.updateBadge(incoming.length);
                if (this.isOpen) this.renderList();
            })
            .catch(err => {
                console.error('Notification fetch error:', err);
            });
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    renderList() {
        const content = document.getElementById('notificationContent');
        if (!content) return;

        if (!this.tickets.length) {
            content.innerHTML = '<div class="notification-empty">No tickets in progress</div>';
            return;
        }

        content.innerHTML = this.tickets.map(t => {
            const typeKey = t.type.toLowerCase().replace(' ', '');
            const link = typeKey === 'installation' ? 'tickets_installation.php'
                       : typeKey === 'maintenance'  ? 'tickets_maintenance.php'
                       : 'tickets_pullout.php';
            // Check if already on the same page
            const rawPath = window.location.pathname;
            const currentPage = decodeURIComponent(rawPath);
            const pathParts = currentPage.split('/');
            const fileName = pathParts[pathParts.length - 1]; // Get just the filename
            const linkWithoutExt = link.replace('.php', ''); // Remove .php from link
            const isSamePage = fileName === linkWithoutExt;
            
            const clickHandler = isSamePage
                ? `onclick="window._notificationBar.handleSamePageTicket('${t.ticket_number}'); return false;"`
                : `onclick="window._notificationBar.navigateToTicket('${link}', '${t.ticket_number}'); return false;"`;
            return `
                <div class="notification-item" ${clickHandler}>
                    <div class="notification-icon ${typeKey}">${this.getIcon(t.type)}</div>
                    <div class="notification-content">
                        <div class="notification-title">
                            ${t.ticket_number || ''}
                            <span class="notification-badge">${t.type}</span>
                        </div>
                        <div class="notification-subtitle">${t.client_name || ''}</div>
                        <div class="notification-time">&#128100; ${t.assigned_to || 'Unassigned'}</div>
                        <div class="notification-time">&#128336; ${this.formatTime(t.start_time)}</div>
                    </div>
                </div>`;
        }).join('');
    }

    updateBadge(count) {
        const btn = document.getElementById('notificationToggleBtn');
        if (!btn) return;
        let badge = btn.querySelector('.notification-badge-count');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'notification-badge-count';
                btn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
        } else {
            if (badge) badge.remove();
        }
    }

    getIcon(type) {
        return type === 'Installation' ? '&#128230;'
             : type === 'Maintenance'  ? '&#128295;'
             : '&#128268;';
    }

    formatTime(dateString) {
        if (!dateString) return 'N/A';
        const diff = Date.now() - new Date(dateString);
        const m = Math.floor(diff / 60000);
        const h = Math.floor(diff / 3600000);
        const d = Math.floor(diff / 86400000);
        if (m < 1)  return 'Just now';
        if (m < 60) return `${m}m ago`;
        if (h < 24) return `${h}h ago`;
        if (d < 7)  return `${d}d ago`;
        return new Date(dateString).toLocaleDateString();
    }

    // Handle same-page ticket navigation
    handleSamePageTicket(ticketNumber) {
        // Close notification bar
        this.closeBar();
        
        // Switch to In Progress tab
        const tabButtons = document.querySelectorAll('.tab-btn');
        let targetTab = null;
        
        tabButtons.forEach(btn => {
            if (btn.getAttribute('data-status') === 'In Progress') {
                targetTab = btn;
            }
        });
        
        if (targetTab) {
            // Click the tab button
            targetTab.click();
            
            // Find and highlight the ticket after tab switch
            setTimeout(() => {
                this.findAndHighlightTicket(ticketNumber);
            }, 300);
        }
    }
    
    // Navigate to ticket page with shake effect
    navigateToTicket(link, ticketNumber) {
        // Store ticket number for shake effect
        sessionStorage.setItem('triggerShake', 'true');
        sessionStorage.setItem('targetTicketNumber', ticketNumber);
        // Store that we need to switch to In Progress tab
        sessionStorage.setItem('switchToTab', 'In Progress');
        
        // Navigate to the ticket page
        window.location.href = link;
    }
    
    // Find and highlight ticket (moved to class method)
    findAndHighlightTicket(targetTicketNumber) {
        const ticketRows = document.querySelectorAll('tbody tr');
        let targetRow = null;
        
        if (targetTicketNumber) {
            // Find row with matching ticket number
            ticketRows.forEach(row => {
                const ticketCell = row.querySelector('td:nth-child(3)'); // Ticket # is in 3rd column
                if (ticketCell && ticketCell.textContent.includes(targetTicketNumber)) {
                    targetRow = row;
                }
            });
        }
        
        // If specific ticket not found, use first row
        if (!targetRow && ticketRows.length > 0) {
            targetRow = ticketRows[0];
        }
        
        if (targetRow) {
            // Add simple bright highlight with shake only
            targetRow.style.backgroundColor = '#fbbf24';
            targetRow.style.animation = 'shake 0.5s ease-in-out';
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Remove animation after shake, keep highlight briefly
            setTimeout(() => {
                targetRow.style.animation = '';
                // Remove highlight after 2 seconds
                setTimeout(() => {
                    targetRow.style.backgroundColor = '';
                }, 2000);
            }, 500);
        }
    }
}

// Shake effect for tickets when opened
function addShakeEffect() {
    // Store the function in sessionStorage to trigger after page load
    sessionStorage.setItem('triggerShake', 'true');
}

document.addEventListener('DOMContentLoaded', () => { 
    window._notificationBar = new NotificationBar();
    
    // Check if we need to trigger shake effect (from notification click)
    if (sessionStorage.getItem('triggerShake') === 'true') {
        sessionStorage.removeItem('triggerShake');
        const targetTicketNumber = sessionStorage.getItem('targetTicketNumber');
        const switchToTab = sessionStorage.getItem('switchToTab');
        sessionStorage.removeItem('targetTicketNumber');
        sessionStorage.removeItem('switchToTab');
        
        // Switch to the specified tab first
        if (switchToTab) {
            // Wait longer for page to be fully loaded, then click the tab
            setTimeout(() => {
                const tabButtons = document.querySelectorAll('.tab-btn');
                let targetTab = null;
                
                tabButtons.forEach(btn => {
                    if (btn.getAttribute('data-status') === switchToTab) {
                        targetTab = btn;
                    }
                });
                
                if (targetTab) {
                    // Click the tab button to trigger its normal behavior
                    targetTab.click();
                    
                    // Wait for tab content to load, then find ticket
                    setTimeout(() => {
                        window._notificationBar.findAndHighlightTicket(targetTicketNumber);
                    }, 1000);
                } else {
                    // Tab not found, just try to find ticket
                    window._notificationBar.findAndHighlightTicket(targetTicketNumber);
                }
            }, 500);
        } else {
            // No tab switch needed, find and highlight immediately
            setTimeout(() => {
                window._notificationBar.findAndHighlightTicket(targetTicketNumber);
            }, 100);
        }
    }
});

// Add shake keyframes if not already present
if (!document.querySelector('#shake-keyframes')) {
    const style = document.createElement('style');
    style.id = 'shake-keyframes';
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes pulse {
            0%, 100% { 
                transform: scale(1.02);
                box-shadow: 0 0 20px rgba(251, 191, 36, 0.8);
            }
            50% { 
                transform: scale(1.05);
                box-shadow: 0 0 30px rgba(251, 191, 36, 1);
            }
        }
    `;
    document.head.appendChild(style);
}
