/**
 * SPA Router - Client-side routing system for the ticket management application
 * 
 * This module handles:
 * - Link click interception for navigation
 * - History API integration (pushState/popstate)
 * - Browser back/forward button navigation
 * - URL parameter preservation
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 9.1, 9.2, 9.3
 */

(function() {
    'use strict';

    // Supported navigation paths
    const SUPPORTED_PATHS = [
        'tickets_installation.php',
        'tickets_maintenance.php',
        'tickets_pullout.php',
        'tickets_all.php',
        'reports.php',
        'service_areas.php',
        'service_plans.php',
        'USERs.php',
        'installation_form.php',
        'htmlpage.php',
        'spa_test.php',
        'spa_test2.php',
        'spa_test3.php'
    ];

    /**
     * Initialize the SPA router
     */
    function init() {
        // Handle browser back/forward navigation
        window.addEventListener('popstate', handlePopState);

        // Intercept navigation link clicks
        document.addEventListener('click', handleLinkClick);

        console.log('[SPA Router] Initialized');
    }

    /**
     * Handle link click events for navigation interception
     * Requirement 1.1: Intercept click events and prevent default navigation
     * 
     * @param {Event} event - The click event
     */
    function handleLinkClick(event) {
        // Find the closest anchor element
        const link = event.target.closest('a');
        
        if (!link) return;

        // Get the href attribute
        const href = link.getAttribute('href');
        
        if (!href) return;

        // Skip if link has data-no-spa attribute (Requirement 14.1)
        if (link.hasAttribute('data-no-spa')) {
            return;
        }

        // Skip external links (Requirement 14.2)
        if (isExternalLink(href)) {
            return;
        }

        // Skip non-HTTP protocols (mailto:, tel:, javascript:, etc.)
        if (href.startsWith('mailto:') || 
            href.startsWith('tel:') || 
            href.startsWith('javascript:') ||
            href.startsWith('#')) {
            return;
        }

        // Check if this is a supported navigation path
        if (!isSupportedPath(href)) {
            return;
        }

        // Prevent default navigation
        event.preventDefault();

        // Navigate using SPA router
        navigateTo(href);
    }

    /**
     * Check if a URL is an external link
     * 
     * @param {string} href - The URL to check
     * @returns {boolean} True if external, false otherwise
     */
    function isExternalLink(href) {
        // Absolute URLs with different origin
        if (href.startsWith('http://') || href.startsWith('https://')) {
            try {
                const url = new URL(href);
                return url.origin !== window.location.origin;
            } catch (e) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a path is supported by the SPA router
     * 
     * @param {string} href - The URL to check
     * @returns {boolean} True if supported, false otherwise
     */
    function isSupportedPath(href) {
        // Extract the pathname from the href
        let pathname;
        
        try {
            // Handle relative URLs
            if (href.startsWith('/')) {
                pathname = href.split('?')[0].substring(1); // Remove leading slash
            } else if (href.startsWith('http')) {
                const url = new URL(href);
                pathname = url.pathname.substring(1); // Remove leading slash
            } else {
                pathname = href.split('?')[0];
            }
        } catch (e) {
            pathname = href.split('?')[0];
        }

        // Check if pathname matches any supported path
        return SUPPORTED_PATHS.some(path => pathname === path || pathname.endsWith('/' + path));
    }

    /**
     * Navigate to a new URL using the SPA router
     * Requirement 1.2: Update browser URL using History API without page reload
     * 
     * @param {string} url - The URL to navigate to
     * @param {boolean} skipPushState - If true, don't add to history (used for popstate)
     */
    function navigateTo(url, skipPushState = false) {
        // Normalize the URL
        const fullUrl = normalizeUrl(url);

        // Update browser history (Requirement 9.1)
        if (!skipPushState) {
            window.history.pushState(
                { url: fullUrl, timestamp: Date.now() },
                '',
                fullUrl
            );
        }

        // Dispatch custom event for content loading
        // This will be handled by the content loader module (to be implemented in later tasks)
        const navigationEvent = new CustomEvent('spa:navigate', {
            detail: {
                url: fullUrl,
                path: getPathFromUrl(fullUrl),
                params: getParamsFromUrl(fullUrl)
            }
        });
        
        window.dispatchEvent(navigationEvent);

        console.log('[SPA Router] Navigating to:', fullUrl);
    }

    /**
     * Handle browser back/forward button navigation
     * Requirement 1.3, 9.2, 9.3: Load appropriate content for history navigation
     * 
     * @param {PopStateEvent} event - The popstate event
     */
    function handlePopState(event) {
        console.log('[SPA Router] Popstate event:', event.state);

        // Get the current URL
        const url = window.location.href;

        // Navigate without adding to history (we're already in history)
        navigateTo(url, true);
    }

    /**
     * Normalize a URL to a full URL
     * 
     * @param {string} url - The URL to normalize
     * @returns {string} The normalized full URL
     */
    function normalizeUrl(url) {
        // If already a full URL, return as-is
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }

        // If starts with /, it's relative to origin
        if (url.startsWith('/')) {
            return window.location.origin + url;
        }

        // Otherwise, relative to current path
        const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        return window.location.origin + currentPath + url;
    }

    /**
     * Extract the pathname from a URL
     * 
     * @param {string} url - The URL
     * @returns {string} The pathname
     */
    function getPathFromUrl(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.pathname;
        } catch (e) {
            return url.split('?')[0];
        }
    }

    /**
     * Extract query parameters from a URL
     * Requirement 7.1: Pass URL parameters to loaded content
     * 
     * @param {string} url - The URL
     * @returns {URLSearchParams} The query parameters
     */
    function getParamsFromUrl(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.searchParams;
        } catch (e) {
            const queryString = url.split('?')[1] || '';
            return new URLSearchParams(queryString);
        }
    }

    /**
     * Get the current route information
     * 
     * @returns {Object} Current route information
     */
    function getCurrentRoute() {
        return {
            url: window.location.href,
            path: window.location.pathname,
            params: new URLSearchParams(window.location.search)
        };
    }

    /**
     * Public API
     */
    window.SPARouter = {
        init: init,
        navigateTo: navigateTo,
        getCurrentRoute: getCurrentRoute,
        isSupportedPath: isSupportedPath
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
