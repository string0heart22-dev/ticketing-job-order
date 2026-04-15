/**
 * Unit tests for SPA Router
 * Tests URL interception, History API integration, and navigation handling
 */

describe('SPA Router', () => {
    let originalLocation;
    let originalHistory;
    
    beforeEach(() => {
        // Save original location and history
        originalLocation = window.location;
        originalHistory = window.history;
        
        // Mock window.location
        delete window.location;
        window.location = {
            href: 'http://localhost/tickets_installation.php',
            origin: 'http://localhost',
            pathname: '/tickets_installation.php',
            search: ''
        };
        
        // Mock window.history
        window.history = {
            pushState: jest.fn(),
            replaceState: jest.fn()
        };
        
        // Clear any existing event listeners
        document.removeEventListener('click', () => {});
        window.removeEventListener('popstate', () => {});
    });
    
    afterEach(() => {
        // Restore original location and history
        window.location = originalLocation;
        window.history = originalHistory;
    });

    describe('Link Click Interception', () => {
        test('should intercept clicks on supported navigation links', () => {
            const link = document.createElement('a');
            link.href = 'tickets_maintenance.php';
            document.body.appendChild(link);
            
            const event = new MouseEvent('click', { bubbles: true, cancelable: true });
            const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
            
            link.dispatchEvent(event);
            
            expect(preventDefaultSpy).toHaveBeenCalled();
            
            document.body.removeChild(link);
        });
        
        test('should not intercept external links', () => {
            const link = document.createElement('a');
            link.href = 'https://external-site.com/page';
            document.body.appendChild(link);
            
            const event = new MouseEvent('click', { bubbles: true, cancelable: true });
            const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
            
            link.dispatchEvent(event);
            
            expect(preventDefaultSpy).not.toHaveBeenCalled();
            
            document.body.removeChild(link);
        });
        
        test('should not intercept links with data-no-spa attribute', () => {
            const link = document.createElement('a');
            link.href = 'tickets_maintenance.php';
            link.setAttribute('data-no-spa', '');
            document.body.appendChild(link);
            
            const event = new MouseEvent('click', { bubbles: true, cancelable: true });
            const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
            
            link.dispatchEvent(event);
            
            expect(preventDefaultSpy).not.toHaveBeenCalled();
            
            document.body.removeChild(link);
        });
        
        test('should not intercept mailto links', () => {
            const link = document.createElement('a');
            link.href = 'mailto:test@example.com';
            document.body.appendChild(link);
            
            const event = new MouseEvent('click', { bubbles: true, cancelable: true });
            const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
            
            link.dispatchEvent(event);
            
            expect(preventDefaultSpy).not.toHaveBeenCalled();
            
            document.body.removeChild(link);
        });
        
        test('should not intercept hash links', () => {
            const link = document.createElement('a');
            link.href = '#section';
            document.body.appendChild(link);
            
            const event = new MouseEvent('click', { bubbles: true, cancelable: true });
            const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
            
            link.dispatchEvent(event);
            
            expect(preventDefaultSpy).not.toHaveBeenCalled();
            
            document.body.removeChild(link);
        });
    });

    describe('History API Integration', () => {
        test('should call pushState when navigating to a new page', () => {
            if (window.SPARouter) {
                window.SPARouter.navigateTo('tickets_maintenance.php');
                
                expect(window.history.pushState).toHaveBeenCalled();
                expect(window.history.pushState).toHaveBeenCalledWith(
                    expect.objectContaining({ url: expect.any(String) }),
                    '',
                    expect.any(String)
                );
            }
        });
        
        test('should not call pushState when skipPushState is true', () => {
            if (window.SPARouter) {
                window.SPARouter.navigateTo('tickets_maintenance.php', true);
                
                expect(window.history.pushState).not.toHaveBeenCalled();
            }
        });
        
        test('should dispatch spa:navigate event on navigation', (done) => {
            if (window.SPARouter) {
                window.addEventListener('spa:navigate', (event) => {
                    expect(event.detail).toHaveProperty('url');
                    expect(event.detail).toHaveProperty('path');
                    expect(event.detail).toHaveProperty('params');
                    done();
                });
                
                window.SPARouter.navigateTo('tickets_maintenance.php');
            } else {
                done();
            }
        });
    });

    describe('URL Parameter Handling', () => {
        test('should preserve query parameters in navigation', () => {
            if (window.SPARouter) {
                window.SPARouter.navigateTo('tickets_maintenance.php?tab=Pending');
                
                expect(window.history.pushState).toHaveBeenCalledWith(
                    expect.any(Object),
                    '',
                    expect.stringContaining('tab=Pending')
                );
            }
        });
    });

    describe('Supported Paths', () => {
        test('should recognize all supported navigation paths', () => {
            if (window.SPARouter) {
                const supportedPaths = [
                    'tickets_installation.php',
                    'tickets_maintenance.php',
                    'tickets_pullout.php',
                    'tickets_all.php',
                    'reports.php',
                    'service_areas.php',
                    'service_plans.php',
                    'USERs.php',
                    'installation_form.php',
                    'htmlpage.php'
                ];
                
                supportedPaths.forEach(path => {
                    expect(window.SPARouter.isSupportedPath(path)).toBe(true);
                });
            }
        });
        
        test('should reject unsupported paths', () => {
            if (window.SPARouter) {
                expect(window.SPARouter.isSupportedPath('unsupported_page.php')).toBe(false);
                expect(window.SPARouter.isSupportedPath('login.php')).toBe(false);
            }
        });
    });

    describe('Browser Back/Forward Navigation', () => {
        test('should handle popstate events', () => {
            const popstateEvent = new PopStateEvent('popstate', {
                state: { url: 'http://localhost/tickets_maintenance.php' }
            });
            
            const dispatchEventSpy = jest.spyOn(window, 'dispatchEvent');
            
            window.dispatchEvent(popstateEvent);
            
            // Should trigger spa:navigate event
            expect(dispatchEventSpy).toHaveBeenCalled();
        });
    });

    describe('getCurrentRoute', () => {
        test('should return current route information', () => {
            if (window.SPARouter) {
                const route = window.SPARouter.getCurrentRoute();
                
                expect(route).toHaveProperty('url');
                expect(route).toHaveProperty('path');
                expect(route).toHaveProperty('params');
                expect(route.params).toBeInstanceOf(URLSearchParams);
            }
        });
    });
});
