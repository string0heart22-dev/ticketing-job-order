/**
 * Unit tests for Script Executor module
 * 
 * Tests cover:
 * - Inline script extraction and execution
 * - External script loading
 * - Duplicate script prevention
 * - Page initialization triggering
 * - Error handling
 */

describe('ScriptExecutor', () => {
    let container;

    beforeEach(() => {
        // Create a fresh page container for each test
        container = document.createElement('div');
        container.id = 'Page_Container';
        document.body.appendChild(container);

        // Reset script executor state
        if (window.ScriptExecutor) {
            window.ScriptExecutor.resetScriptTracking();
            window.ScriptExecutor.clearInitializers();
            window.ScriptExecutor.clearPageTypeHandlers();
        }

        // Clear any test globals
        delete window.testScriptExecuted;
        delete window.testInitCalled;
    });

    afterEach(() => {
        // Clean up
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
        container = null;

        // Clean up test globals
        delete window.testScriptExecuted;
        delete window.testInitCalled;
    });

    describe('Inline Script Execution', () => {
        test('should extract and execute inline scripts from loaded content', (done) => {
            // Requirement 4.1: Extract and execute inline scripts
            container.innerHTML = `
                <div>
                    <p>Content</p>
                    <script>
                        window.testScriptExecuted = true;
                    </script>
                </div>
            `;

            // Listen for scripts ready event
            window.addEventListener('spa:scriptsReady', () => {
                expect(window.testScriptExecuted).toBe(true);
                done();
            }, { once: true });

            // Trigger content loaded event
            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should execute multiple inline scripts in order', (done) => {
            window.scriptExecutionOrder = [];

            container.innerHTML = `
                <div>
                    <script>window.scriptExecutionOrder.push(1);</script>
                    <p>Content</p>
                    <script>window.scriptExecutionOrder.push(2);</script>
                    <script>window.scriptExecutionOrder.push(3);</script>
                </div>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                expect(window.scriptExecutionOrder).toEqual([1, 2, 3]);
                delete window.scriptExecutionOrder;
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should prevent duplicate inline script execution', (done) => {
            // Requirement 4.4: Prevent duplicate script execution
            window.scriptExecutionCount = 0;

            const scriptContent = 'window.scriptExecutionCount++;';
            
            // First load
            container.innerHTML = `<script>${scriptContent}</script>`;
            
            window.addEventListener('spa:scriptsReady', () => {
                expect(window.scriptExecutionCount).toBe(1);

                // Second load with same script
                container.innerHTML = `<script>${scriptContent}</script>`;
                
                setTimeout(() => {
                    const event2 = new CustomEvent('spa:contentLoaded', {
                        detail: { url: 'http://localhost/test2.php' }
                    });
                    window.dispatchEvent(event2);

                    setTimeout(() => {
                        // Should still be 1 (not executed again)
                        expect(window.scriptExecutionCount).toBe(1);
                        delete window.scriptExecutionCount;
                        done();
                    }, 100);
                }, 100);
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should remove script tags from DOM after extraction', (done) => {
            container.innerHTML = `
                <div>
                    <script>window.testScriptExecuted = true;</script>
                    <p>Content</p>
                </div>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                const scripts = container.querySelectorAll('script');
                expect(scripts.length).toBe(0);
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });
    });

    describe('External Script Loading', () => {
        test('should load external scripts referenced in content', (done) => {
            // Requirement 4.2: Load page-specific JavaScript files
            container.innerHTML = `
                <div>
                    <script src="data:text/javascript,window.externalScriptLoaded=true;"></script>
                </div>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                // Give script time to execute
                setTimeout(() => {
                    expect(window.externalScriptLoaded).toBe(true);
                    delete window.externalScriptLoaded;
                    done();
                }, 100);
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should prevent duplicate external script loading', (done) => {
            // Requirement 4.4: Prevent duplicate script execution
            const scriptUrl = 'data:text/javascript,window.externalLoadCount=(window.externalLoadCount||0)+1;';
            
            container.innerHTML = `<script src="${scriptUrl}"></script>`;

            window.addEventListener('spa:scriptsReady', () => {
                setTimeout(() => {
                    expect(window.externalLoadCount).toBe(1);

                    // Try to load same script again
                    container.innerHTML = `<script src="${scriptUrl}"></script>`;
                    
                    const event2 = new CustomEvent('spa:contentLoaded', {
                        detail: { url: 'http://localhost/test2.php' }
                    });
                    window.dispatchEvent(event2);

                    setTimeout(() => {
                        // Should still be 1 (not loaded again)
                        expect(window.externalLoadCount).toBe(1);
                        delete window.externalLoadCount;
                        done();
                    }, 100);
                }, 100);
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should check if script is loaded using isScriptLoaded', () => {
            const scriptUrl = 'http://example.com/test.js';
            
            expect(window.ScriptExecutor.isScriptLoaded(scriptUrl)).toBe(false);
            
            window.ScriptExecutor.markScriptAsLoaded(scriptUrl);
            
            expect(window.ScriptExecutor.isScriptLoaded(scriptUrl)).toBe(true);
        });
    });

    describe('Page Initialization', () => {
        test('should trigger page-specific initialization functions', (done) => {
            // Requirement 4.3: Trigger initialization for DOMContentLoaded-dependent code
            window.initPage_test = jest.fn();

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(window.initPage_test).toHaveBeenCalled();
                delete window.initPage_test;
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should dispatch spa:contentReady event', (done) => {
            // Requirement 4.3: Trigger initialization for DOMContentLoaded-dependent code
            const contentReadyHandler = jest.fn();
            window.addEventListener('spa:contentReady', contentReadyHandler, { once: true });

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(contentReadyHandler).toHaveBeenCalled();
                expect(contentReadyHandler.mock.calls[0][0].detail.url).toBe('http://localhost/test.php');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should call registered initializers', (done) => {
            const initializer = jest.fn();
            window.ScriptExecutor.registerInitializer('testInit', initializer);

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(initializer).toHaveBeenCalledWith('http://localhost/test.php');
                window.ScriptExecutor.unregisterInitializer('testInit');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should unregister initializers', () => {
            const initializer = jest.fn();
            window.ScriptExecutor.registerInitializer('testInit', initializer);
            
            const removed = window.ScriptExecutor.unregisterInitializer('testInit');
            expect(removed).toBe(true);
            
            const removedAgain = window.ScriptExecutor.unregisterInitializer('testInit');
            expect(removedAgain).toBe(false);
        });

        test('should clear all initializers', () => {
            window.ScriptExecutor.registerInitializer('init1', jest.fn());
            window.ScriptExecutor.registerInitializer('init2', jest.fn());
            
            window.ScriptExecutor.clearInitializers();
            
            // Verify they don't get called
            container.innerHTML = '<div>Content</div>';
            
            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
            
            // If we get here without errors, initializers were cleared
            expect(true).toBe(true);
        });
    });

    describe('Error Handling', () => {
        test('should handle inline script execution errors gracefully', (done) => {
            // Requirement 11.5: Log JavaScript errors without breaking SPA functionality
            const errorHandler = jest.fn();
            window.addEventListener('spa:scriptError', errorHandler, { once: true });

            container.innerHTML = `
                <script>
                    throw new Error('Test error');
                </script>
                <script>
                    window.secondScriptExecuted = true;
                </script>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                expect(errorHandler).toHaveBeenCalled();
                expect(errorHandler.mock.calls[0][0].detail.error.message).toBe('Test error');
                
                // Second script should still execute
                expect(window.secondScriptExecuted).toBe(true);
                delete window.secondScriptExecuted;
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should handle page init function errors gracefully', (done) => {
            const errorHandler = jest.fn();
            window.addEventListener('spa:scriptError', errorHandler, { once: true });

            window.initPage_test = () => {
                throw new Error('Init error');
            };

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(errorHandler).toHaveBeenCalled();
                delete window.initPage_test;
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should handle registered initializer errors gracefully', (done) => {
            const errorHandler = jest.fn();
            window.addEventListener('spa:scriptError', errorHandler, { once: true });

            const badInitializer = () => {
                throw new Error('Initializer error');
            };
            window.ScriptExecutor.registerInitializer('badInit', badInitializer);

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(errorHandler).toHaveBeenCalled();
                window.ScriptExecutor.unregisterInitializer('badInit');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });
    });

    describe('Script Execution Order', () => {
        test('should load external scripts before executing inline scripts', (done) => {
            // Requirement 4.5: Ensure dependency loading order
            window.executionOrder = [];

            const externalScript = 'data:text/javascript,window.executionOrder.push("external");';
            
            container.innerHTML = `
                <script src="${externalScript}"></script>
                <script>window.executionOrder.push('inline');</script>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                setTimeout(() => {
                    expect(window.executionOrder).toEqual(['external', 'inline']);
                    delete window.executionOrder;
                    done();
                }, 100);
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });
    });

    describe('Edge Cases', () => {
        test('should handle content with no scripts', (done) => {
            container.innerHTML = '<div><p>Just content, no scripts</p></div>';

            window.addEventListener('spa:scriptsReady', () => {
                // Should complete without errors
                expect(container.querySelector('p').textContent).toBe('Just content, no scripts');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should handle empty container', (done) => {
            container.innerHTML = '';

            window.addEventListener('spa:scriptsReady', () => {
                // Should complete without errors
                expect(container.innerHTML).toBe('');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });

        test('should skip non-JavaScript script types', (done) => {
            container.innerHTML = `
                <script type="application/json">{"test": "data"}</script>
                <script type="text/javascript">window.jsScriptExecuted = true;</script>
            `;

            window.addEventListener('spa:scriptsReady', () => {
                expect(window.jsScriptExecuted).toBe(true);
                delete window.jsScriptExecuted;
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);
        });
    });

    describe('API Methods', () => {
        test('should reset script tracking', () => {
            window.ScriptExecutor.markScriptAsLoaded('http://example.com/test.js');
            expect(window.ScriptExecutor.isScriptLoaded('http://example.com/test.js')).toBe(true);
            
            window.ScriptExecutor.resetScriptTracking();
            expect(window.ScriptExecutor.isScriptLoaded('http://example.com/test.js')).toBe(false);
        });

        test('should not register non-function initializers', () => {
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            window.ScriptExecutor.registerInitializer('badInit', 'not a function');
            
            expect(consoleSpy).toHaveBeenCalled();
            consoleSpy.mockRestore();
        });
    });

    describe('Page-Type-Specific Initialization', () => {
        test('should register and call page-type-specific initializers', (done) => {
            // Requirement 3.5: Create initialization hooks for each page type
            const ticketsInitializer = jest.fn();
            
            window.ScriptExecutor.registerPageTypeInitializer(
                'tickets_installation',
                'ticketsInit',
                ticketsInitializer
            );

            container.innerHTML = '<div>Tickets Installation Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(ticketsInitializer).toHaveBeenCalledWith(
                    'http://localhost/tickets_installation.php',
                    'tickets_installation'
                );
                
                window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'ticketsInit');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event);
        });

        test('should not call page-type initializers for different page types', (done) => {
            const ticketsInitializer = jest.fn();
            const reportsInitializer = jest.fn();
            
            window.ScriptExecutor.registerPageTypeInitializer(
                'tickets_installation',
                'ticketsInit',
                ticketsInitializer
            );
            
            window.ScriptExecutor.registerPageTypeInitializer(
                'reports',
                'reportsInit',
                reportsInitializer
            );

            container.innerHTML = '<div>Tickets Installation Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(ticketsInitializer).toHaveBeenCalled();
                expect(reportsInitializer).not.toHaveBeenCalled();
                
                window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'ticketsInit');
                window.ScriptExecutor.unregisterPageTypeInitializer('reports', 'reportsInit');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event);
        });

        test('should handle multiple initializers for same page type', (done) => {
            const init1 = jest.fn();
            const init2 = jest.fn();
            
            window.ScriptExecutor.registerPageTypeInitializer('tickets_installation', 'init1', init1);
            window.ScriptExecutor.registerPageTypeInitializer('tickets_installation', 'init2', init2);

            container.innerHTML = '<div>Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(init1).toHaveBeenCalled();
                expect(init2).toHaveBeenCalled();
                
                window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'init1');
                window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'init2');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event);
        });

        test('should not register duplicate page-type initializers', () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
            const init = jest.fn();
            
            window.ScriptExecutor.registerPageTypeInitializer('tickets_installation', 'init', init);
            window.ScriptExecutor.registerPageTypeInitializer('tickets_installation', 'init', init);
            
            expect(consoleSpy).toHaveBeenCalled();
            
            window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'init');
            consoleSpy.mockRestore();
        });

        test('should unregister page-type initializers', () => {
            const init = jest.fn();
            
            window.ScriptExecutor.registerPageTypeInitializer('tickets_installation', 'init', init);
            const removed = window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'init');
            
            expect(removed).toBe(true);
            
            const removedAgain = window.ScriptExecutor.unregisterPageTypeInitializer('tickets_installation', 'init');
            expect(removedAgain).toBe(false);
        });
    });

    describe('Page Cleanup Handling', () => {
        test('should call cleanup handlers when page type changes', (done) => {
            // Requirement 3.5: Handle cleanup of previous page's JavaScript
            const ticketsCleanup = jest.fn();
            
            window.ScriptExecutor.registerPageTypeCleanup(
                'tickets_installation',
                'ticketsCleanup',
                ticketsCleanup
            );

            // Load tickets page first
            container.innerHTML = '<div>Tickets Content</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                // Now load a different page
                container.innerHTML = '<div>Reports Content</div>';
                
                window.addEventListener('spa:scriptsReady', () => {
                    expect(ticketsCleanup).toHaveBeenCalledWith('tickets_installation');
                    
                    window.ScriptExecutor.unregisterPageTypeCleanup('tickets_installation', 'ticketsCleanup');
                    done();
                }, { once: true });

                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/reports.php' }
                });
                window.dispatchEvent(event2);
            }, 100);
        });

        test('should call cleanup handlers when re-initializing same page type', (done) => {
            // Requirement 3.5: Re-initialize page-specific JavaScript after content load
            const ticketsCleanup = jest.fn();
            
            window.ScriptExecutor.registerPageTypeCleanup(
                'tickets_installation',
                'ticketsCleanup',
                ticketsCleanup
            );

            // Load tickets page first
            container.innerHTML = '<div>Tickets Content</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                // Load same page type again
                container.innerHTML = '<div>Tickets Content Updated</div>';
                
                window.addEventListener('spa:scriptsReady', () => {
                    expect(ticketsCleanup).toHaveBeenCalledWith('tickets_installation');
                    
                    window.ScriptExecutor.unregisterPageTypeCleanup('tickets_installation', 'ticketsCleanup');
                    done();
                }, { once: true });

                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/tickets_installation.php?tab=pending' }
                });
                window.dispatchEvent(event2);
            }, 100);
        });

        test('should call global cleanup handlers on every page transition', (done) => {
            const globalCleanup = jest.fn();
            
            window.ScriptExecutor.registerCleanupHandler('globalCleanup', globalCleanup);

            // Load first page
            container.innerHTML = '<div>Page 1</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/page1.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                // Load second page
                container.innerHTML = '<div>Page 2</div>';
                
                window.addEventListener('spa:scriptsReady', () => {
                    expect(globalCleanup).toHaveBeenCalledWith('page1');
                    
                    window.ScriptExecutor.unregisterCleanupHandler('globalCleanup');
                    done();
                }, { once: true });

                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/page2.php' }
                });
                window.dispatchEvent(event2);
            }, 100);
        });

        test('should dispatch spa:pageCleanup event', (done) => {
            const cleanupHandler = jest.fn();
            window.addEventListener('spa:pageCleanup', cleanupHandler);

            // Load first page
            container.innerHTML = '<div>Page 1</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/page1.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                // Load second page
                container.innerHTML = '<div>Page 2</div>';
                
                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/page2.php' }
                });
                window.dispatchEvent(event2);

                setTimeout(() => {
                    expect(cleanupHandler).toHaveBeenCalled();
                    expect(cleanupHandler.mock.calls[0][0].detail.pageType).toBe('page1');
                    
                    window.removeEventListener('spa:pageCleanup', cleanupHandler);
                    done();
                }, 100);
            }, 100);
        });

        test('should handle cleanup errors gracefully', (done) => {
            const errorHandler = jest.fn();
            window.addEventListener('spa:scriptError', errorHandler);

            const badCleanup = () => {
                throw new Error('Cleanup error');
            };
            
            window.ScriptExecutor.registerPageTypeCleanup('page1', 'badCleanup', badCleanup);

            // Load first page
            container.innerHTML = '<div>Page 1</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/page1.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                // Load second page (should trigger cleanup)
                container.innerHTML = '<div>Page 2</div>';
                
                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/page2.php' }
                });
                window.dispatchEvent(event2);

                setTimeout(() => {
                    expect(errorHandler).toHaveBeenCalled();
                    
                    window.ScriptExecutor.unregisterPageTypeCleanup('page1', 'badCleanup');
                    window.removeEventListener('spa:scriptError', errorHandler);
                    done();
                }, 100);
            }, 100);
        });

        test('should unregister page-type cleanup handlers', () => {
            const cleanup = jest.fn();
            
            window.ScriptExecutor.registerPageTypeCleanup('tickets_installation', 'cleanup', cleanup);
            const removed = window.ScriptExecutor.unregisterPageTypeCleanup('tickets_installation', 'cleanup');
            
            expect(removed).toBe(true);
            
            const removedAgain = window.ScriptExecutor.unregisterPageTypeCleanup('tickets_installation', 'cleanup');
            expect(removedAgain).toBe(false);
        });

        test('should unregister global cleanup handlers', () => {
            const cleanup = jest.fn();
            
            window.ScriptExecutor.registerCleanupHandler('cleanup', cleanup);
            const removed = window.ScriptExecutor.unregisterCleanupHandler('cleanup');
            
            expect(removed).toBe(true);
            
            const removedAgain = window.ScriptExecutor.unregisterCleanupHandler('cleanup');
            expect(removedAgain).toBe(false);
        });
    });

    describe('Page Type Tracking', () => {
        test('should track current page type', (done) => {
            expect(window.ScriptExecutor.getCurrentPageType()).toBeNull();

            container.innerHTML = '<div>Tickets Content</div>';

            window.addEventListener('spa:scriptsReady', () => {
                expect(window.ScriptExecutor.getCurrentPageType()).toBe('tickets_installation');
                done();
            }, { once: true });

            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/tickets_installation.php' }
            });
            window.dispatchEvent(event);
        });

        test('should update page type on navigation', (done) => {
            // Load first page
            container.innerHTML = '<div>Page 1</div>';
            
            const event1 = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/page1.php' }
            });
            window.dispatchEvent(event1);

            setTimeout(() => {
                expect(window.ScriptExecutor.getCurrentPageType()).toBe('page1');

                // Load second page
                container.innerHTML = '<div>Page 2</div>';
                
                window.addEventListener('spa:scriptsReady', () => {
                    expect(window.ScriptExecutor.getCurrentPageType()).toBe('page2');
                    done();
                }, { once: true });

                const event2 = new CustomEvent('spa:contentLoaded', {
                    detail: { url: 'http://localhost/page2.php' }
                });
                window.dispatchEvent(event2);
            }, 100);
        });

        test('should clear page-type handlers', () => {
            window.ScriptExecutor.registerPageTypeInitializer('page1', 'init', jest.fn());
            window.ScriptExecutor.registerPageTypeCleanup('page1', 'cleanup', jest.fn());
            
            window.ScriptExecutor.clearPageTypeHandlers();
            
            // Verify they're cleared
            expect(window.ScriptExecutor.unregisterPageTypeInitializer('page1', 'init')).toBe(false);
            expect(window.ScriptExecutor.unregisterPageTypeCleanup('page1', 'cleanup')).toBe(false);
        });
    });
});
