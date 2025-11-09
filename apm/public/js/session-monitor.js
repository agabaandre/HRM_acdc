/**
 * Session Monitor
 * Monitors session expiry and shows warning dialogs
 */
class SessionMonitor {
    constructor() {
        try {
            this.warningTime = 300; // 5 minutes before expiry
            this.checkInterval = 30; // Check every 30 seconds
            this.countdownInterval = null;
            this.warningShown = false;
            this.sessionLifetime = 120 * 60; // 120 minutes in seconds
            this.lastActivity = Date.now();
            this.apiBaseUrl = document.querySelector('meta[name="api-base-url"]')?.getAttribute('content') || '/api';
            this.useFallbackMode = false; // Will be set to true if session API fails
            this.apiTimeout = 5000; // 5 second timeout for API calls
            
            // Get base URL for logout redirect (from meta tag or session data)
            // Using logout endpoint to destroy both Laravel and CodeIgniter sessions
            const baseUrlMeta = document.querySelector('meta[name="base-url"]')?.getAttribute('content');
            const baseUrlFromSession = window.baseUrl || baseUrlMeta || '';
            this.logoutUrl = baseUrlFromSession ? (baseUrlFromSession + '/auth/logout') : '/staff/auth/logout';
            
            this.init();
        } catch (error) {
            console.error('SessionMonitor: Error in constructor:', error);
        }
    }

    init() {
        try {
            console.log('SessionMonitor: Initializing with settings:', {
                warningTime: this.warningTime / 60 + ' minutes',
                checkInterval: this.checkInterval + ' seconds',
                sessionLifetime: this.sessionLifetime / 60 + ' minutes',
                apiBaseUrl: this.apiBaseUrl
            });
            
            // Track user activity
            this.trackActivity();
            
            // Start session monitoring
            this.startMonitoring();
            
            // Bind event listeners
            this.bindEvents();
            
            // Test session status immediately
            this.testSessionStatus();
            
            // Show status message
            console.log('SessionMonitor: ✅ Session monitor is now active and monitoring your session');
        } catch (error) {
            console.error('SessionMonitor: Error in init:', error);
        }
    }

    trackActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
                this.resetWarning();
            }, true);
        });
    }

    startMonitoring() {
        setInterval(() => {
            this.checkSession();
        }, this.checkInterval * 1000);
    }

    checkSession() {
        const now = Date.now();
        const timeSinceActivity = (now - this.lastActivity) / 1000;
        const timeUntilExpiry = this.sessionLifetime - timeSinceActivity;

        // Only log detailed info when warning is about to show or every 5 minutes
        const shouldLog = timeUntilExpiry <= this.warningTime + 60 || Math.floor(timeSinceActivity) % 300 === 0;
        if (shouldLog) {
            console.log('SessionMonitor: Checking session:', {
                timeUntilExpiry: Math.floor(timeUntilExpiry / 60) + ' minutes',
                warningShown: this.warningShown,
                useFallbackMode: this.useFallbackMode
            });
        }

        // Show warning if we're within warning time and haven't shown it yet
        if (timeUntilExpiry <= this.warningTime && timeUntilExpiry > 0 && !this.warningShown) {
            console.log('SessionMonitor: Showing warning - time until expiry:', timeUntilExpiry);
            this.showWarning(timeUntilExpiry);
        }
        
        // If session has expired, show expired modal
        if (timeUntilExpiry <= 0) {
            console.log('SessionMonitor: Session expired - time until expiry:', timeUntilExpiry);
            this.showExpired();
        }
    }

    showWarning(timeUntilExpiry) {
        try {
            this.warningShown = true;
            const modalElement = document.getElementById('sessionExpiryModal');
            if (!modalElement) {
                console.error('SessionMonitor: sessionExpiryModal element not found');
                return;
            }
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
            this.startCountdown(timeUntilExpiry);
        } catch (error) {
            console.error('SessionMonitor: Error showing warning:', error);
        }
    }

    startCountdown(timeUntilExpiry) {
        let timeLeft = Math.floor(timeUntilExpiry);
        const countdownElement = document.getElementById('countdownTimer');
        const progressBar = document.getElementById('sessionProgressBar');
        
        this.countdownInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Update progress bar
            const progress = (timeLeft / this.warningTime) * 100;
            progressBar.style.width = `${Math.max(0, progress)}%`;
            
            if (timeLeft <= 0) {
                clearInterval(this.countdownInterval);
                this.hideWarning();
                this.showExpired();
            }
            
            timeLeft--;
        }, 1000);
    }

    async showExpired() {
        try {
            this.hideWarning();
            
            // First, destroy Laravel session via API
            try {
                console.log('SessionMonitor: Destroying Laravel session via API...');
                await fetch(this.apiBaseUrl + '/logout', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'include' // Include cookies
                }).catch(err => {
                    console.warn('SessionMonitor: Failed to destroy Laravel session via API:', err);
                });
            } catch (error) {
                console.warn('SessionMonitor: Error destroying Laravel session:', error);
            }
            
            const modalElement = document.getElementById('sessionExpiredModal');
            if (!modalElement) {
                console.error('SessionMonitor: sessionExpiredModal element not found');
                // Redirect to logout immediately if modal not found (to destroy both sessions)
                window.location.href = this.logoutUrl;
                return;
            }
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
            // Redirect to logout after 3 seconds (to destroy both Laravel and CodeIgniter sessions)
            setTimeout(() => {
                window.location.href = this.logoutUrl;
            }, 3000);
        } catch (error) {
            console.error('SessionMonitor: Error showing expired modal:', error);
            // Even on error, redirect to logout
            window.location.href = this.logoutUrl;
        }
    }

    hideWarning() {
        try {
            const modalElement = document.getElementById('sessionExpiryModal');
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
            
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
            }
        } catch (error) {
            console.error('SessionMonitor: Error hiding warning:', error);
        }
    }

    resetWarning() {
        this.warningShown = false;
        this.hideWarning();
    }

    async extendSession() {
        try {
            if (this.useFallbackMode) {
                // In fallback mode, just reset the activity timer
                console.log('SessionMonitor: Extending session in fallback mode');
                this.lastActivity = Date.now();
                this.resetWarning();
                this.showSuccessMessage('Session extended successfully (fallback mode)');
                return;
            }

            console.log('SessionMonitor: Attempting to extend session via POST to:', this.apiBaseUrl + '/extend-session');
            
            // Create a timeout promise
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('API timeout')), this.apiTimeout);
            });
            
            // Create the fetch promise
            const fetchPromise = fetch(this.apiBaseUrl + '/extend-session', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            // Race between fetch and timeout
            const response = await Promise.race([fetchPromise, timeoutPromise]);

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.lastActivity = Date.now();
                    this.resetWarning();
                    this.showSuccessMessage('Session extended successfully');
                } else {
                    this.showExpired();
                }
            } else {
                console.error('Failed to extend session:', response.status, response.statusText);
                this.showExpired();
            }
        } catch (error) {
            console.error('Failed to extend session:', error);
            // If API fails, fall back to local mode
            console.log('SessionMonitor: API failed, switching to fallback mode');
            this.useFallbackMode = true;
            this.lastActivity = Date.now();
            this.resetWarning();
            this.showSuccessMessage('Session extended successfully (fallback mode)');
        }
    }

    async logoutNow() {
        // First, destroy Laravel session via API
        try {
            console.log('SessionMonitor: Destroying Laravel session before logout...');
            await fetch(this.apiBaseUrl + '/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include' // Include cookies
            }).catch(err => {
                console.warn('SessionMonitor: Failed to destroy Laravel session:', err);
            });
        } catch (error) {
            console.warn('SessionMonitor: Error destroying Laravel session:', error);
        }
        
        // Redirect to logout to destroy both Laravel and CodeIgniter sessions
        window.location.href = this.logoutUrl;
    }

    async redirectToLogin() {
        // First, destroy Laravel session via API
        try {
            console.log('SessionMonitor: Destroying Laravel session before redirect...');
            await fetch(this.apiBaseUrl + '/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include' // Include cookies
            }).catch(err => {
                console.warn('SessionMonitor: Failed to destroy Laravel session:', err);
            });
        } catch (error) {
            console.warn('SessionMonitor: Error destroying Laravel session:', error);
        }
        
        // Redirect to logout to destroy both Laravel and CodeIgniter sessions
        window.location.href = this.logoutUrl;
    }

    showSuccessMessage(message) {
        // You can integrate with your notification system here
        if (typeof Lobibox !== 'undefined') {
            Lobibox.notify('success', {
                msg: message,
                position: 'top right'
            });
        } else {
            alert(message);
        }
    }

    async testSessionStatus() {
        try {
            console.log('SessionMonitor: Testing session status API...');
            
            // First try the debug endpoint to see what session data is available
            const debugResponse = await fetch(this.apiBaseUrl + '/session-debug', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            
            if (debugResponse.ok) {
                const debugData = await debugResponse.json();
                console.log('SessionMonitor: Session debug data:', {
                    has_user_session: debugData.debug?.has_user_session,
                    staff_id: debugData.debug?.staff_id,
                    session_keys: debugData.debug?.all_session_keys?.length || 0
                });
                
                if (debugData.success && debugData.debug.has_user_session) {
                    console.log('SessionMonitor: User session found, proceeding with normal mode');
                    this.useFallbackMode = false;
                } else {
                    console.log('SessionMonitor: No user session found, using fallback mode');
                    this.useFallbackMode = true;
                }
            }
            
            // Now try the session status endpoint
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('API timeout')), this.apiTimeout);
            });
            
            const fetchPromise = fetch(this.apiBaseUrl + '/session-status', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            
            const response = await Promise.race([fetchPromise, timeoutPromise]);
            
            if (response.ok) {
                const data = await response.json();
                console.log('SessionMonitor: Session status response:', data);
                
                if (data.success && data.authenticated) {
                    // Update session lifetime from server
                    this.sessionLifetime = data.time_until_expiry || this.sessionLifetime;
                    console.log('SessionMonitor: Updated session lifetime from server:', this.sessionLifetime);
                    this.useFallbackMode = false;
                } else {
                    console.log('SessionMonitor: Session API not available, using fallback mode');
                    this.useFallbackMode = true;
                }
            } else {
                console.error('SessionMonitor: Failed to get session status:', response.status, response.statusText);
                this.useFallbackMode = true;
            }
        } catch (error) {
            console.error('SessionMonitor: Error testing session status:', error);
            console.log('SessionMonitor: Using fallback mode due to API error');
            this.useFallbackMode = true;
        }
    }

    bindEvents() {
        try {
            // Extend session button
            const extendBtn = document.getElementById('extendSessionBtn');
            if (extendBtn) {
                extendBtn.addEventListener('click', () => {
                    this.extendSession();
                });
            }

            // Logout now button
            const logoutBtn = document.getElementById('logoutNowBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => {
                    this.logoutNow();
                });
            }

            // Redirect to login button
            const redirectBtn = document.getElementById('redirectToLoginBtn');
            if (redirectBtn) {
                redirectBtn.addEventListener('click', () => {
                    this.redirectToLogin();
                });
            }

        // Handle modal hidden events
        const modalElement = document.getElementById('sessionExpiryModal');
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', () => {
                this.resetWarning();
            });
        }

        // Add keyboard shortcuts for testing
        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+S to test session warning
            if (e.ctrlKey && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                console.log('SessionMonitor: Manual test triggered');
                this.showWarning(this.warningTime);
            }
            
            // Ctrl+Shift+E to test session expired
            if (e.ctrlKey && e.shiftKey && e.key === 'E') {
                e.preventDefault();
                console.log('SessionMonitor: Manual expired test triggered');
                this.showExpired();
            }
            
            // Ctrl+Shift+T to simulate 3-minute warning (for testing)
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                console.log('SessionMonitor: Simulating 3-minute warning test');
                // Simulate that we're 3 minutes away from expiry
                this.lastActivity = Date.now() - (this.sessionLifetime - this.warningTime) * 1000;
                this.checkSession();
            }
        });
        } catch (error) {
            console.error('SessionMonitor: Error binding events:', error);
        }
    }
}

// Initialize session monitor when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure all DOM elements are fully loaded
    setTimeout(function() {
        try {
            console.log('SessionMonitor: DOM loaded, checking if user is logged in...');
            console.log('SessionMonitor: user-logged-in meta:', document.querySelector('meta[name="user-logged-in"]')?.getAttribute('content'));
            console.log('SessionMonitor: body classes:', document.body?.className || 'body not found');
            
            // Check for CI-based login indicators
            const hasUserMeta = document.querySelector('meta[name="user-logged-in"]')?.getAttribute('content') === 'true';
            const hasLoggedInClass = document.body?.classList.contains('logged-in') || false;
            const hasUserSession = document.cookie.includes('laravel_session') || document.cookie.includes('PHPSESSID');
            
            console.log('SessionMonitor: Login checks:', {
                hasUserMeta,
                hasLoggedInClass,
                hasUserSession
            });
            
            // Check if required modal elements exist before initializing
            const sessionExpiryModal = document.getElementById('sessionExpiryModal');
            const sessionExpiredModal = document.getElementById('sessionExpiredModal');
            
            if (!sessionExpiryModal || !sessionExpiredModal) {
                console.error('SessionMonitor: Required modal elements not found. Skipping initialization.');
                console.log('SessionMonitor: sessionExpiryModal exists:', !!sessionExpiryModal);
                console.log('SessionMonitor: sessionExpiredModal exists:', !!sessionExpiredModal);
                return;
            }
            
            // Only initialize if user is logged in (check multiple indicators for CI-based login)
            if (hasUserMeta || hasLoggedInClass || hasUserSession) {
                console.log('SessionMonitor: User is logged in, initializing session monitor...');
                new SessionMonitor();
            } else {
                console.log('SessionMonitor: User is not logged in, skipping session monitor initialization');
            }
        } catch (error) {
            console.error('SessionMonitor: Error during initialization:', error);
        }
    }, 100); // 100ms delay to ensure DOM is fully ready
});

// Export for potential external use
window.SessionMonitor = SessionMonitor;
