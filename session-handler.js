/**
 * Session Handler - Session validation and management for SPA (DISABLED)
 * 
 * This module is disabled to prevent automatic logouts.
 * Sessions now persist until browser is closed or manual logout.
 */

(function() {
    'use strict';

    console.log('[Session Handler] Session timeout disabled - no automatic logouts');

    // Minimal session handler - only handle manual logout clicks
    function setupLogoutHandler() {
        // Use event delegation for logout links
        document.addEventListener('click', (event) => {
            const target = event.target.closest('a[href*="logout"]');
            
            if (target) {
                event.preventDefault();
                handleLogout();
            }
        });
    }

    /**
     * Handle logout
     */
    async function handleLogout() {
        console.log('[Session Handler] Manual logout initiated');
        
        try {
            // Call logout endpoint
            const response = await fetch('logout.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                console.log('[Session Handler] Logout successful');
            }
            
        } catch (error) {
            console.error('[Session Handler] Logout error:', error);
        } finally {
            // Redirect to login
            window.location.href = 'Login.php';
        }
    }

    /**
     * Public API (minimal)
     */
    window.SessionHandler = {
        init: function() {
            setupLogoutHandler();
            console.log('[Session Handler] Minimal handler initialized (no timeouts)');
        },
        isValid: function() {
            return true; // Always valid - no timeout checks
        },
        checkSession: function() {
            return Promise.resolve(true); // Always valid
        },
        validateSession: function() {
            return Promise.resolve(true); // Always valid
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.SessionHandler.init);
    } else {
        window.SessionHandler.init();
    }

})();
