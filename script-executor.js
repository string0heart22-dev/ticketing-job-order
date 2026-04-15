/**
 * Script Executor - Dynamic script handling for SPA
 * 
 * This module handles:
 * - Extracting and executing inline scripts from loaded content
 * - Loading page-specific JavaScript files if not already present
 * - Triggering initialization for DOMContentLoaded-dependent code
 * - Preventing duplicate script execution on repeated navigation
 * - Ensuring dependency loading order
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        pageContainerSelector: '#Page_Container',
        scriptAttribute: 'data-spa-script-id', // Attribute to track loaded scripts
        initFunctionPrefix: 'initPage_', // Prefix for page-specific init functions
        maxScriptExecutionTime: 5000 // Maximum time to wait for script execution (5 seconds)
    };

    // State
    let initialized = false;
    let loadedScripts = new Set(); // Track loaded external scripts by URL
    let executedInlineScripts = new Set(); // Track executed inline scripts by hash
    let pageInitializers = new Map(); // Track page-specific initialization functions
    let pageCleanupHandlers = new Map(); // Track page-specific cleanup functions
    let currentPageType = null; // Track the current page type for cleanup
    let pageTypeInitializers = new Map(); // Track page-type-specific initializers (e.g., tickets_installation)
    let pageTypeCleanupHandlers = new Map(); // Track page-type-specific cleanup handlers

    /**
     * Initialize the script executor
     * Listens for spa:contentLoaded events to execute scripts in new content
     */
    function init() {
        if (initialized) {
            console.warn('[Script Executor] Already initialized');
            return;
        }

        // Listen for content loaded events
        window.addEventListener('spa:contentLoaded', handleContentLoaded);

        initialized = true;
        console.log('[Script Executor] Initialized');
    }

    /**
     * Handle content loaded events
     * Requirement 4.1, 4.2, 4.3: Execute scripts after content is loaded
     * Requirement 3.5: Re-initialize page-specific JavaScript after content injection
     * 
     * @param {CustomEvent} event - The content loaded event
     */
    function handleContentLoaded(event) {
        const { url } = event.detail;
        
        console.log('[Script Executor] Processing scripts for:', url);

        // Get the page container
        const container = document.querySelector(CONFIG.pageContainerSelector);
        if (!container) {
            console.error('[Script Executor] Page container not found');
            return;
        }

        // Extract page type from URL
        const newPageType = extractPageNameFromUrl(url);

        // Run cleanup for previous page if page type changed
        if (currentPageType && currentPageType !== newPageType) {
            console.log('[Script Executor] Page type changed, running cleanup for:', currentPageType);
            runPageCleanup(currentPageType);
        } else if (currentPageType === newPageType) {
            console.log('[Script Executor] Same page type, re-initializing:', newPageType);
            // Same page type - run cleanup before re-initialization
            runPageCleanup(currentPageType);
        }

        // Update current page type
        currentPageType = newPageType;

        // Process scripts in the loaded content
        processScripts(container, url, newPageType);
    }

    /**
     * Process all scripts in the loaded content
     * 
     * @param {HTMLElement} container - The container with loaded content
     * @param {string} url - The URL of the loaded content
     * @param {string} pageType - The page type extracted from URL
     */
    function processScripts(container, url, pageType) {
        // Extract inline scripts (Requirement 4.1)
        const inlineScripts = extractInlineScripts(container);

        // Extract external script references (Requirement 4.2)
        const externalScripts = extractExternalScripts(container);

        // Load external scripts first (to ensure dependencies are available)
        loadExternalScripts(externalScripts)
            .then(() => {
                console.log('[Script Executor] External scripts loaded');

                // Execute inline scripts (Requirement 4.1)
                executeInlineScripts(inlineScripts);

                // Trigger DOMContentLoaded-dependent initialization (Requirement 4.3)
                triggerPageInitialization(url, pageType);

                // Dispatch script execution complete event
                dispatchScriptsReadyEvent(url);
            })
            .catch(error => {
                console.error('[Script Executor] Error loading external scripts:', error);
                
                // Still try to execute inline scripts and initialization
                executeInlineScripts(inlineScripts);
                triggerPageInitialization(url, pageType);
                dispatchScriptsReadyEvent(url);
            });
    }

    /**
     * Extract inline scripts from container
     * Requirement 4.1: Extract inline scripts from loaded content
     * 
     * @param {HTMLElement} container - The container element
     * @returns {Array} Array of script elements
     */
    function extractInlineScripts(container) {
        const scripts = [];
        const scriptElements = container.querySelectorAll('script');

        scriptElements.forEach(script => {
            // Only process inline scripts (no src attribute)
            if (!script.src) {
                scripts.push({
                    element: script,
                    content: script.textContent || script.innerHTML,
                    type: script.type || 'text/javascript',
                    hash: hashString(script.textContent || script.innerHTML)
                });

                // Remove the script element from DOM (we'll execute it separately)
                script.remove();
            }
        });

        console.log(`[Script Executor] Found ${scripts.length} inline scripts`);
        return scripts;
    }

    /**
     * Extract external script references from container
     * Requirement 4.2: Load page-specific JavaScript files
     * 
     * @param {HTMLElement} container - The container element
     * @returns {Array} Array of script URLs
     */
    function extractExternalScripts(container) {
        const scripts = [];
        const scriptElements = container.querySelectorAll('script[src]');

        scriptElements.forEach(script => {
            const src = script.src;
            
            if (src) {
                scripts.push({
                    url: src,
                    async: script.async,
                    defer: script.defer,
                    type: script.type || 'text/javascript',
                    element: script
                });

                // Remove the script element from DOM (we'll load it separately)
                script.remove();
            }
        });

        console.log(`[Script Executor] Found ${scripts.length} external scripts`);
        return scripts;
    }

    /**
     * Load external scripts
     * Requirement 4.2: Load page-specific JavaScript files if not already present
     * Requirement 4.4: Prevent duplicate script execution
     * Requirement 4.5: Ensure dependency loading order
     * 
     * @param {Array} scripts - Array of script objects
     * @returns {Promise} Promise that resolves when all scripts are loaded
     */
    function loadExternalScripts(scripts) {
        // Filter out already loaded scripts (Requirement 4.4)
        const scriptsToLoad = scripts.filter(script => {
            if (loadedScripts.has(script.url)) {
                console.log('[Script Executor] Script already loaded, skipping:', script.url);
                return false;
            }
            return true;
        });

        if (scriptsToLoad.length === 0) {
            return Promise.resolve();
        }

        // Load scripts in order (Requirement 4.5)
        // Scripts without async/defer are loaded sequentially
        // Scripts with async/defer can be loaded in parallel
        const sequentialScripts = scriptsToLoad.filter(s => !s.async && !s.defer);
        const parallelScripts = scriptsToLoad.filter(s => s.async || s.defer);

        // Load sequential scripts first, then parallel scripts
        return loadScriptsSequentially(sequentialScripts)
            .then(() => loadScriptsInParallel(parallelScripts));
    }

    /**
     * Load scripts sequentially (one after another)
     * Requirement 4.5: Ensure dependency loading order
     * 
     * @param {Array} scripts - Array of script objects
     * @returns {Promise} Promise that resolves when all scripts are loaded
     */
    function loadScriptsSequentially(scripts) {
        if (scripts.length === 0) {
            return Promise.resolve();
        }

        // Load scripts one by one using reduce
        return scripts.reduce((promise, script) => {
            return promise.then(() => loadScript(script));
        }, Promise.resolve());
    }

    /**
     * Load scripts in parallel
     * 
     * @param {Array} scripts - Array of script objects
     * @returns {Promise} Promise that resolves when all scripts are loaded
     */
    function loadScriptsInParallel(scripts) {
        if (scripts.length === 0) {
            return Promise.resolve();
        }

        const promises = scripts.map(script => loadScript(script));
        return Promise.all(promises);
    }

    /**
     * Load a single external script
     * Requirement 4.2: Load page-specific JavaScript files
     * 
     * @param {Object} scriptInfo - Script information object
     * @returns {Promise} Promise that resolves when script is loaded
     */
    function loadScript(scriptInfo) {
        return new Promise((resolve, reject) => {
            const { url, async, defer, type } = scriptInfo;

            console.log('[Script Executor] Loading script:', url);

            // Create script element
            const script = document.createElement('script');
            script.src = url;
            script.type = type;
            
            if (async) script.async = true;
            if (defer) script.defer = true;

            // Set up load handlers
            script.onload = () => {
                console.log('[Script Executor] Script loaded successfully:', url);
                loadedScripts.add(url);
                resolve();
            };

            script.onerror = (error) => {
                console.error('[Script Executor] Failed to load script:', url, error);
                // Don't reject - continue with other scripts
                resolve();
            };

            // Add timeout to prevent hanging
            const timeout = setTimeout(() => {
                console.warn('[Script Executor] Script load timeout:', url);
                resolve();
            }, CONFIG.maxScriptExecutionTime);

            script.onload = () => {
                clearTimeout(timeout);
                console.log('[Script Executor] Script loaded successfully:', url);
                loadedScripts.add(url);
                resolve();
            };

            // Append to document head
            document.head.appendChild(script);
        });
    }

    /**
     * Execute inline scripts
     * Requirement 4.1: Execute inline scripts after DOM injection
     * Requirement 4.4: Prevent duplicate script execution
     * 
     * @param {Array} scripts - Array of script objects
     */
    function executeInlineScripts(scripts) {
        scripts.forEach(script => {
            // Skip if already executed (Requirement 4.4)
            if (executedInlineScripts.has(script.hash)) {
                console.log('[Script Executor] Inline script already executed, skipping');
                return;
            }

            // Skip non-JavaScript scripts
            if (script.type && script.type !== 'text/javascript' && script.type !== 'application/javascript') {
                console.log('[Script Executor] Skipping non-JavaScript script:', script.type);
                return;
            }

            try {
                console.log('[Script Executor] Executing inline script');
                
                // Execute the script in global scope
                // Using indirect eval to execute in global scope
                const globalEval = eval;
                globalEval(script.content);

                // Mark as executed
                executedInlineScripts.add(script.hash);

            } catch (error) {
                console.error('[Script Executor] Error executing inline script:', error);
                
                // Dispatch error event (Requirement 11.5)
                dispatchScriptErrorEvent(script, error);
            }
        });
    }

    /**
     * Trigger page initialization for DOMContentLoaded-dependent code
     * Requirement 4.3: Trigger initialization for DOMContentLoaded-dependent code
     * Requirement 3.5: Re-initialize page-specific JavaScript after content injection
     * 
     * @param {string} url - The URL of the loaded page
     * @param {string} pageType - The page type extracted from URL
     */
    function triggerPageInitialization(url, pageType) {
        console.log('[Script Executor] Triggering page initialization for:', pageType);

        // Dispatch a custom DOMContentLoaded-like event for the new content
        const contentReadyEvent = new CustomEvent('spa:contentReady', {
            detail: { url, pageType }
        });
        window.dispatchEvent(contentReadyEvent);
        document.dispatchEvent(contentReadyEvent);

        // Try to call page-specific initialization functions
        // Look for functions like initPage_tickets_installation()
        const initFunctionName = CONFIG.initFunctionPrefix + pageType;

        if (typeof window[initFunctionName] === 'function') {
            try {
                console.log('[Script Executor] Calling page init function:', initFunctionName);
                window[initFunctionName]();
            } catch (error) {
                console.error('[Script Executor] Error calling page init function:', error);
                dispatchScriptErrorEvent({ type: 'init', name: initFunctionName }, error);
            }
        }

        // Call page-type-specific initializers (Requirement 3.5)
        if (pageTypeInitializers.has(pageType)) {
            const initializers = pageTypeInitializers.get(pageType);
            console.log(`[Script Executor] Running ${initializers.length} page-type initializers for:`, pageType);
            
            initializers.forEach(({ name, handler }) => {
                try {
                    console.log('[Script Executor] Calling page-type initializer:', name);
                    handler(url, pageType);
                } catch (error) {
                    console.error('[Script Executor] Error calling page-type initializer:', error);
                    dispatchScriptErrorEvent({ type: 'page-type-initializer', name, pageType }, error);
                }
            });
        }

        // Call any registered global page initializers
        pageInitializers.forEach((initializer, name) => {
            try {
                console.log('[Script Executor] Calling registered initializer:', name);
                initializer(url, pageType);
            } catch (error) {
                console.error('[Script Executor] Error calling registered initializer:', error);
                dispatchScriptErrorEvent({ type: 'initializer', name }, error);
            }
        });

        // Trigger jQuery ready handlers if jQuery is present
        if (typeof jQuery !== 'undefined') {
            try {
                console.log('[Script Executor] Triggering jQuery ready handlers');
                jQuery(document).trigger('spa:ready');
            } catch (error) {
                console.error('[Script Executor] Error triggering jQuery ready:', error);
            }
        }
    }

    /**
     * Extract page name from URL for initialization function lookup
     * 
     * @param {string} url - The URL
     * @returns {string} The page name
     */
    function extractPageNameFromUrl(url) {
        try {
            const urlObj = new URL(url);
            const pathname = urlObj.pathname;
            
            // Get the filename without extension
            const filename = pathname.split('/').pop();
            const pageName = filename.replace(/\.php$/, '').replace(/\.html$/, '');
            
            // Convert to valid function name (replace special chars with underscore)
            return pageName.replace(/[^a-zA-Z0-9_]/g, '_');
        } catch (e) {
            return 'unknown';
        }
    }

    /**
     * Run cleanup for a page type
     * Requirement 3.5: Handle cleanup of previous page's JavaScript
     * 
     * @param {string} pageType - The page type to clean up
     */
    function runPageCleanup(pageType) {
        console.log('[Script Executor] Running cleanup for page type:', pageType);

        // Dispatch cleanup event
        const cleanupEvent = new CustomEvent('spa:pageCleanup', {
            detail: { pageType }
        });
        window.dispatchEvent(cleanupEvent);

        // Call page-type-specific cleanup handlers
        if (pageTypeCleanupHandlers.has(pageType)) {
            const cleanupHandlers = pageTypeCleanupHandlers.get(pageType);
            console.log(`[Script Executor] Running ${cleanupHandlers.length} page-type cleanup handlers for:`, pageType);
            
            cleanupHandlers.forEach(({ name, handler }) => {
                try {
                    console.log('[Script Executor] Calling page-type cleanup handler:', name);
                    handler(pageType);
                } catch (error) {
                    console.error('[Script Executor] Error calling page-type cleanup handler:', error);
                    dispatchScriptErrorEvent({ type: 'page-type-cleanup', name, pageType }, error);
                }
            });
        }

        // Call global cleanup handlers
        pageCleanupHandlers.forEach((handler, name) => {
            try {
                console.log('[Script Executor] Calling global cleanup handler:', name);
                handler(pageType);
            } catch (error) {
                console.error('[Script Executor] Error calling global cleanup handler:', error);
                dispatchScriptErrorEvent({ type: 'cleanup', name }, error);
            }
        });
    }

    /**
     * Generate a simple hash for a string
     * Used to track executed inline scripts
     * 
     * @param {string} str - The string to hash
     * @returns {string} The hash
     */
    function hashString(str) {
        let hash = 0;
        if (str.length === 0) return hash.toString();
        
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        
        return hash.toString();
    }

    /**
     * Dispatch scripts ready event
     * 
     * @param {string} url - The URL of the loaded page
     */
    function dispatchScriptsReadyEvent(url) {
        const event = new CustomEvent('spa:scriptsReady', {
            detail: { url }
        });
        window.dispatchEvent(event);
        console.log('[Script Executor] Scripts ready event dispatched');
    }

    /**
     * Dispatch script error event
     * Requirement 11.5: Log JavaScript errors without breaking SPA functionality
     * 
     * @param {Object} script - Script information
     * @param {Error} error - The error object
     */
    function dispatchScriptErrorEvent(script, error) {
        const event = new CustomEvent('spa:scriptError', {
            detail: {
                script: script,
                error: error,
                message: error.message,
                stack: error.stack
            }
        });
        window.dispatchEvent(event);
    }

    /**
     * Register a page initializer function
     * This allows other modules to register initialization code that runs after content loads
     * 
     * @param {string} name - Name of the initializer
     * @param {Function} initializer - Initialization function
     */
    function registerInitializer(name, initializer) {
        if (typeof initializer !== 'function') {
            console.error('[Script Executor] Initializer must be a function');
            return;
        }

        pageInitializers.set(name, initializer);
        console.log('[Script Executor] Registered initializer:', name);
    }

    /**
     * Unregister a page initializer
     * 
     * @param {string} name - Name of the initializer
     * @returns {boolean} True if removed, false otherwise
     */
    function unregisterInitializer(name) {
        const removed = pageInitializers.delete(name);
        if (removed) {
            console.log('[Script Executor] Unregistered initializer:', name);
        }
        return removed;
    }

    /**
     * Clear all registered initializers
     */
    function clearInitializers() {
        const count = pageInitializers.size;
        pageInitializers.clear();
        console.log(`[Script Executor] Cleared ${count} initializers`);
    }

    /**
     * Register a page-type-specific initializer
     * Requirement 3.5: Create initialization hooks for each page type
     * 
     * @param {string} pageType - The page type (e.g., 'tickets_installation', 'tickets_maintenance')
     * @param {string} name - Name of the initializer
     * @param {Function} handler - Initialization function (receives url and pageType)
     */
    function registerPageTypeInitializer(pageType, name, handler) {
        if (typeof handler !== 'function') {
            console.error('[Script Executor] Page-type initializer must be a function');
            return;
        }

        if (!pageTypeInitializers.has(pageType)) {
            pageTypeInitializers.set(pageType, []);
        }

        const initializers = pageTypeInitializers.get(pageType);
        
        // Check if already registered
        const existing = initializers.find(init => init.name === name);
        if (existing) {
            console.warn('[Script Executor] Page-type initializer already registered:', pageType, name);
            return;
        }

        initializers.push({ name, handler });
        console.log('[Script Executor] Registered page-type initializer:', pageType, name);
    }

    /**
     * Unregister a page-type-specific initializer
     * 
     * @param {string} pageType - The page type
     * @param {string} name - Name of the initializer
     * @returns {boolean} True if removed, false otherwise
     */
    function unregisterPageTypeInitializer(pageType, name) {
        if (!pageTypeInitializers.has(pageType)) {
            return false;
        }

        const initializers = pageTypeInitializers.get(pageType);
        const index = initializers.findIndex(init => init.name === name);
        
        if (index !== -1) {
            initializers.splice(index, 1);
            console.log('[Script Executor] Unregistered page-type initializer:', pageType, name);
            
            // Clean up empty arrays
            if (initializers.length === 0) {
                pageTypeInitializers.delete(pageType);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Register a cleanup handler for a specific page type
     * Requirement 3.5: Handle cleanup of previous page's JavaScript
     * 
     * @param {string} pageType - The page type (e.g., 'tickets_installation', 'tickets_maintenance')
     * @param {string} name - Name of the cleanup handler
     * @param {Function} handler - Cleanup function (receives pageType)
     */
    function registerPageTypeCleanup(pageType, name, handler) {
        if (typeof handler !== 'function') {
            console.error('[Script Executor] Page-type cleanup handler must be a function');
            return;
        }

        if (!pageTypeCleanupHandlers.has(pageType)) {
            pageTypeCleanupHandlers.set(pageType, []);
        }

        const cleanupHandlers = pageTypeCleanupHandlers.get(pageType);
        
        // Check if already registered
        const existing = cleanupHandlers.find(cleanup => cleanup.name === name);
        if (existing) {
            console.warn('[Script Executor] Page-type cleanup handler already registered:', pageType, name);
            return;
        }

        cleanupHandlers.push({ name, handler });
        console.log('[Script Executor] Registered page-type cleanup handler:', pageType, name);
    }

    /**
     * Unregister a page-type-specific cleanup handler
     * 
     * @param {string} pageType - The page type
     * @param {string} name - Name of the cleanup handler
     * @returns {boolean} True if removed, false otherwise
     */
    function unregisterPageTypeCleanup(pageType, name) {
        if (!pageTypeCleanupHandlers.has(pageType)) {
            return false;
        }

        const cleanupHandlers = pageTypeCleanupHandlers.get(pageType);
        const index = cleanupHandlers.findIndex(cleanup => cleanup.name === name);
        
        if (index !== -1) {
            cleanupHandlers.splice(index, 1);
            console.log('[Script Executor] Unregistered page-type cleanup handler:', pageType, name);
            
            // Clean up empty arrays
            if (cleanupHandlers.length === 0) {
                pageTypeCleanupHandlers.delete(pageType);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Register a global cleanup handler
     * Runs on every page transition
     * 
     * @param {string} name - Name of the cleanup handler
     * @param {Function} handler - Cleanup function (receives pageType)
     */
    function registerCleanupHandler(name, handler) {
        if (typeof handler !== 'function') {
            console.error('[Script Executor] Cleanup handler must be a function');
            return;
        }

        pageCleanupHandlers.set(name, handler);
        console.log('[Script Executor] Registered global cleanup handler:', name);
    }

    /**
     * Unregister a global cleanup handler
     * 
     * @param {string} name - Name of the cleanup handler
     * @returns {boolean} True if removed, false otherwise
     */
    function unregisterCleanupHandler(name) {
        const removed = pageCleanupHandlers.delete(name);
        if (removed) {
            console.log('[Script Executor] Unregistered global cleanup handler:', name);
        }
        return removed;
    }

    /**
     * Get the current page type
     * 
     * @returns {string|null} The current page type or null if no page loaded
     */
    function getCurrentPageType() {
        return currentPageType;
    }

    /**
     * Clear all page-type-specific initializers and cleanup handlers
     */
    function clearPageTypeHandlers() {
        const initCount = pageTypeInitializers.size;
        const cleanupCount = pageTypeCleanupHandlers.size;
        
        pageTypeInitializers.clear();
        pageTypeCleanupHandlers.clear();
        
        console.log(`[Script Executor] Cleared ${initCount} page-type initializers and ${cleanupCount} cleanup handlers`);
    }

    /**
     * Reset script tracking (useful for testing or manual cache clearing)
     */
    function resetScriptTracking() {
        loadedScripts.clear();
        executedInlineScripts.clear();
        console.log('[Script Executor] Script tracking reset');
    }

    /**
     * Check if a script URL is already loaded
     * 
     * @param {string} url - The script URL
     * @returns {boolean} True if loaded, false otherwise
     */
    function isScriptLoaded(url) {
        return loadedScripts.has(url);
    }

    /**
     * Manually mark a script as loaded
     * Useful when scripts are loaded by other means
     * 
     * @param {string} url - The script URL
     */
    function markScriptAsLoaded(url) {
        loadedScripts.add(url);
        console.log('[Script Executor] Marked script as loaded:', url);
    }

    /**
     * Public API
     */
    window.ScriptExecutor = {
        init: init,
        registerInitializer: registerInitializer,
        unregisterInitializer: unregisterInitializer,
        clearInitializers: clearInitializers,
        registerPageTypeInitializer: registerPageTypeInitializer,
        unregisterPageTypeInitializer: unregisterPageTypeInitializer,
        registerPageTypeCleanup: registerPageTypeCleanup,
        unregisterPageTypeCleanup: unregisterPageTypeCleanup,
        registerCleanupHandler: registerCleanupHandler,
        unregisterCleanupHandler: unregisterCleanupHandler,
        getCurrentPageType: getCurrentPageType,
        clearPageTypeHandlers: clearPageTypeHandlers,
        resetScriptTracking: resetScriptTracking,
        isScriptLoaded: isScriptLoaded,
        markScriptAsLoaded: markScriptAsLoaded
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
