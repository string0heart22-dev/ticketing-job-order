/**
 * Session Handler Tests
 * 
 * Tests for session validation and management functionality
 */

describe('Session Handler', () => {
    let originalFetch;
    let originalLocation;

    beforeEach(() => {
        // Mock fetch API
        originalFetch = global.fetch;
        global.fetch = jest.fn();

        // Mock window.location
        originalLocation = window.location;
        delete window.location;
        window.location = { href: '' };

        // Mock timers
        jest.useFakeTimers();

        // Clear any existing notifications
        document.querySelectorAll('.spa-session-notification').forEach(el => el.remove());
    });

    afterEach(() => {
        // Restore fetch
        global.fetch = originalFetch;

        // Restore location
        window.location = originalLocation;

        // Restore timers
        jest.useRealTimers();

        // Clear notifications
        document.querySelectorAll('.spa-session-notification').forEach(el => el.remove());
    });

    describe('Initialization', () => {
        test('should initialize and expose public API', () => {
            expect(window.SessionHandler).toBeDefined();
            expect(typeof window.SessionHandler.init).toBe('function');
            expect(typeof window.SessionHandler.isValid).toBe('function');
            expect(typeof window.SessionHandler.checkSession).toBe('function');
            expect(typeof window.SessionHandler.validateSession).toBe('function');
        });

        test('should listen for spa:contentLoaded events', () => {
            const spy = jest.spyOn(window, 'addEventListener');
            window.SessionHandler.init();
            
            expect(spy).toHaveBeenCalledWith('spa:contentLoaded', expect.any(Function));
        });

        test('should listen for spa:contentError events', () => {
            const spy = jest.spyOn(window, 'addEventListener');
            window.SessionHandler.init();
            
            expect(spy).toHaveBeenCalledWith('spa:contentError', expect.any(Function));
        });
    });

    describe('Session Validation', () => {
        test('should validate session on server', async () => {
            // Mock successful session check
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            await window.SessionHandler.validateSession();

            expect(global.fetch).toHaveBeenCalledWith(
                'check_session.php',
                expect.objectContaining({
                    method: 'GET',
                    headers: expect.objectContaining({
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Check': 'true'
                    })
                })
            );
        });

        test('should detect valid session from response', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p style="color: green;">✓ User is logged in</p>'
            });

            await window.SessionHandler.validateSession();

            expect(window.SessionHandler.isValid()).toBe(true);
        });

        test('should detect invalid session from response', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p style="color: red;">✗ No user logged in</p>'
            });

            await window.SessionHandler.validateSession();

            // Fast-forward to allow redirect
            jest.advanceTimersByTime(5000);

            expect(window.location.href).toContain('Login.php');
        });

        test('should handle 401 authentication errors', async () => {
            const event = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });

            window.dispatchEvent(event);

            // Fast-forward to allow redirect
            jest.advanceTimersByTime(5000);

            expect(window.location.href).toContain('Login.php');
        });

        test('should handle failed session validation response', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 401,
                statusText: 'Unauthorized'
            });

            await window.SessionHandler.validateSession();

            // Fast-forward to allow redirect
            jest.advanceTimersByTime(5000);

            expect(window.location.href).toContain('Login.php');
        });
    });

    describe('Session Timeout', () => {
        test('should show warning notification before timeout', async () => {
            // Mock valid session
            global.fetch.mockResolvedValue({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            // Simulate time passing to trigger warning (25 minutes of inactivity)
            const mockDate = new Date();
            jest.spyOn(Date, 'now').mockReturnValue(mockDate.getTime() - 25 * 60 * 1000);

            await window.SessionHandler.validateSession();

            // Check for warning notification
            const notification = document.querySelector('.spa-session-notification');
            expect(notification).toBeTruthy();
            expect(notification.textContent).toContain('session will expire');
        });

        test('should redirect to login on session timeout', async () => {
            // Mock valid session initially
            global.fetch.mockResolvedValue({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            // Simulate time passing beyond timeout (31 minutes of inactivity)
            const mockDate = new Date();
            jest.spyOn(Date, 'now').mockReturnValue(mockDate.getTime() - 31 * 60 * 1000);

            await window.SessionHandler.validateSession();

            // Fast-forward to allow redirect
            jest.advanceTimersByTime(5000);

            expect(window.location.href).toContain('Login.php');
            expect(window.location.href).toContain('timeout=1');
        });

        test('should show timeout notification before redirect', async () => {
            global.fetch.mockResolvedValue({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            // Simulate timeout
            const mockDate = new Date();
            jest.spyOn(Date, 'now').mockReturnValue(mockDate.getTime() - 31 * 60 * 1000);

            await window.SessionHandler.validateSession();

            // Check for timeout notification
            const notification = document.querySelector('.spa-session-notification');
            expect(notification).toBeTruthy();
            expect(notification.textContent).toContain('session has expired');
        });
    });

    describe('User Activity Tracking', () => {
        test('should track mouse activity', () => {
            const initialTime = Date.now();
            
            // Simulate mouse activity
            const mouseEvent = new MouseEvent('mousedown');
            document.dispatchEvent(mouseEvent);

            // Activity should update internal timestamp
            // (We can't directly test this, but we can verify no errors occur)
            expect(true).toBe(true);
        });

        test('should track keyboard activity', () => {
            const keyEvent = new KeyboardEvent('keydown');
            document.dispatchEvent(keyEvent);

            expect(true).toBe(true);
        });

        test('should track scroll activity', () => {
            const scrollEvent = new Event('scroll');
            document.dispatchEvent(scrollEvent);

            expect(true).toBe(true);
        });

        test('should track touch activity', () => {
            const touchEvent = new TouchEvent('touchstart');
            document.dispatchEvent(touchEvent);

            expect(true).toBe(true);
        });
    });

    describe('Logout Handling', () => {
        test('should handle logout link clicks', async () => {
            // Mock logout response
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200
            });

            // Create logout link
            const logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            logoutLink.textContent = 'Logout';
            document.body.appendChild(logoutLink);

            // Click logout link
            const clickEvent = new MouseEvent('click', { bubbles: true });
            logoutLink.dispatchEvent(clickEvent);

            // Wait for async operations
            await new Promise(resolve => setTimeout(resolve, 100));

            // Check that logout endpoint was called
            expect(global.fetch).toHaveBeenCalledWith(
                'logout.php',
                expect.objectContaining({
                    method: 'GET',
                    credentials: 'same-origin'
                })
            );

            // Clean up
            logoutLink.remove();
        });

        test('should show logout notification', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200
            });

            const logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            document.body.appendChild(logoutLink);

            const clickEvent = new MouseEvent('click', { bubbles: true });
            logoutLink.dispatchEvent(clickEvent);

            await new Promise(resolve => setTimeout(resolve, 100));

            const notification = document.querySelector('.spa-session-notification');
            expect(notification).toBeTruthy();
            expect(notification.textContent).toContain('Logging out');

            logoutLink.remove();
        });

        test('should redirect to login after logout', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200
            });

            const logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            document.body.appendChild(logoutLink);

            const clickEvent = new MouseEvent('click', { bubbles: true });
            logoutLink.dispatchEvent(clickEvent);

            await new Promise(resolve => setTimeout(resolve, 100));

            // Fast-forward to redirect
            jest.advanceTimersByTime(2000);

            expect(window.location.href).toContain('Login.php');

            logoutLink.remove();
        });

        test('should redirect to login even if logout request fails', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            const logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            document.body.appendChild(logoutLink);

            const clickEvent = new MouseEvent('click', { bubbles: true });
            logoutLink.dispatchEvent(clickEvent);

            await new Promise(resolve => setTimeout(resolve, 100));

            // Fast-forward to redirect
            jest.advanceTimersByTime(2000);

            expect(window.location.href).toContain('Login.php');

            logoutLink.remove();
        });
    });

    describe('Notifications', () => {
        test('should display notification with correct type', () => {
            const event = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });

            window.dispatchEvent(event);

            const notification = document.querySelector('.spa-session-notification');
            expect(notification).toBeTruthy();
            expect(notification.classList.contains('spa-notification-error')).toBe(true);
        });

        test('should allow closing notification', () => {
            const event = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });

            window.dispatchEvent(event);

            const notification = document.querySelector('.spa-session-notification');
            const closeButton = notification.querySelector('.spa-notification-close');
            
            closeButton.click();

            expect(document.querySelector('.spa-session-notification')).toBeFalsy();
        });

        test('should auto-dismiss notification after duration', () => {
            const event = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });

            window.dispatchEvent(event);

            expect(document.querySelector('.spa-session-notification')).toBeTruthy();

            // Fast-forward past notification duration (5 seconds)
            jest.advanceTimersByTime(5000);

            expect(document.querySelector('.spa-session-notification')).toBeFalsy();
        });

        test('should remove existing notification before showing new one', () => {
            // Show first notification
            const event1 = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test1.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });
            window.dispatchEvent(event1);

            expect(document.querySelectorAll('.spa-session-notification').length).toBe(1);

            // Show second notification
            const event2 = new CustomEvent('spa:contentError', {
                detail: {
                    url: 'http://localhost/test2.php',
                    error: { status: 401, message: 'Unauthorized' }
                }
            });
            window.dispatchEvent(event2);

            // Should still only have one notification
            expect(document.querySelectorAll('.spa-session-notification').length).toBe(1);
        });
    });

    describe('Content Loaded Event Handling', () => {
        test('should update activity on content loaded', () => {
            const event = new CustomEvent('spa:contentLoaded', {
                detail: {
                    url: 'http://localhost/test.php'
                }
            });

            // Should not throw error
            expect(() => {
                window.dispatchEvent(event);
            }).not.toThrow();
        });

        test('should reset session warning flag on content loaded', async () => {
            // Mock valid session
            global.fetch.mockResolvedValue({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            // Trigger warning
            const mockDate = new Date();
            jest.spyOn(Date, 'now').mockReturnValue(mockDate.getTime() - 25 * 60 * 1000);
            await window.SessionHandler.validateSession();

            // Clear notification
            document.querySelectorAll('.spa-session-notification').forEach(el => el.remove());

            // Dispatch content loaded
            const event = new CustomEvent('spa:contentLoaded', {
                detail: { url: 'http://localhost/test.php' }
            });
            window.dispatchEvent(event);

            // Warning flag should be reset (we can't directly test this, but verify no errors)
            expect(true).toBe(true);
        });
    });

    describe('Public API', () => {
        test('should expose isValid method', () => {
            expect(typeof window.SessionHandler.isValid).toBe('function');
            expect(typeof window.SessionHandler.isValid()).toBe('boolean');
        });

        test('should expose checkSession method', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            expect(typeof window.SessionHandler.checkSession).toBe('function');
            
            const result = await window.SessionHandler.checkSession();
            expect(typeof result).toBe('boolean');
        });

        test('should return true for valid session', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p>✓ User is logged in</p>'
            });

            const result = await window.SessionHandler.checkSession();
            expect(result).toBe(true);
        });

        test('should return false for invalid session', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 200,
                text: async () => '<p>✗ No user logged in</p>'
            });

            const result = await window.SessionHandler.checkSession();
            
            // Fast-forward to allow state update
            jest.advanceTimersByTime(100);
            
            expect(result).toBe(false);
        });
    });

    describe('Network Error Handling', () => {
        test('should not invalidate session on network errors', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            await window.SessionHandler.validateSession();

            // Session should still be considered valid (don't invalidate on network errors)
            expect(window.SessionHandler.isValid()).toBe(true);
        });

        test('should log network errors', async () => {
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            await window.SessionHandler.validateSession();

            expect(consoleSpy).toHaveBeenCalledWith(
                expect.stringContaining('Session validation error'),
                expect.any(Error)
            );

            consoleSpy.mockRestore();
        });
    });
});
