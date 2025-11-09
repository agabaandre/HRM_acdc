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
            // Silent error handling
        }
    }

    init() {
        try {
            // Track user activity
            this.trackActivity();
            
            // Start session monitoring
            this.startMonitoring();
            
            // Bind event listeners
            this.bindEvents();
            
            // Test session status immediately
            this.testSessionStatus();
        } catch (error) {
            // Silent error handling
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

        // Show warning if we're within warning time and haven't shown it yet
        if (timeUntilExpiry <= this.warningTime && timeUntilExpiry > 0 && !this.warningShown) {
            this.showWarning(timeUntilExpiry);
        }
        
        // If session has expired, show expired modal
        if (timeUntilExpiry <= 0) {
            this.showExpired();
        }
    }

    showWarning(timeUntilExpiry) {
        try {
            this.warningShown = true;
            const modalElement = document.getElementById('sessionExpiryModal');
            if (!modalElement) {
                return;
            }
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
            this.startCountdown(timeUntilExpiry);
        } catch (error) {
            // Silent error handling
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
                await fetch(this.apiBaseUrl + '/logout', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'include' // Include cookies
                }).catch(() => {
                    // Silent error handling
                });
            } catch (error) {
                // Silent error handling
            }
            
            const modalElement = document.getElementById('sessionExpiredModal');
            if (!modalElement) {
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
            // Silent error handling
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
                this.lastActivity = Date.now();
                this.resetWarning();
                this.showSuccessMessage('Session extended successfully (fallback mode)');
                return;
            }
            
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
                this.showExpired();
            }
        } catch (error) {
            // If API fails, fall back to local mode
            this.useFallbackMode = true;
            this.lastActivity = Date.now();
            this.resetWarning();
            this.showSuccessMessage('Session extended successfully (fallback mode)');
        }
    }

    async logoutNow() {
        // First, destroy Laravel session via API
        try {
            await fetch(this.apiBaseUrl + '/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include' // Include cookies
            }).catch(() => {
                // Silent error handling
            });
        } catch (error) {
            // Silent error handling
        }
        
        // Redirect to logout to destroy both Laravel and CodeIgniter sessions
        window.location.href = this.logoutUrl;
    }

    async redirectToLogin() {
        // First, destroy Laravel session via API
        try {
            await fetch(this.apiBaseUrl + '/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include' // Include cookies
            }).catch(() => {
                // Silent error handling
            });
        } catch (error) {
            // Silent error handling
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
                
                if (debugData.success && debugData.debug.has_user_session) {
                    this.useFallbackMode = false;
                } else {
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
                
                if (data.success && data.authenticated) {
                    // Update session lifetime from server
                    this.sessionLifetime = data.time_until_expiry || this.sessionLifetime;
                    this.useFallbackMode = false;
                } else {
                    this.useFallbackMode = true;
                }
            } else {
                this.useFallbackMode = true;
            }
        } catch (error) {
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
                this.showWarning(this.warningTime);
            }
            
            // Ctrl+Shift+E to test session expired
            if (e.ctrlKey && e.shiftKey && e.key === 'E') {
                e.preventDefault();
                this.showExpired();
            }
            
            // Ctrl+Shift+T to simulate 3-minute warning (for testing)
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                // Simulate that we're 3 minutes away from expiry
                this.lastActivity = Date.now() - (this.sessionLifetime - this.warningTime) * 1000;
                this.checkSession();
            }
        });
        } catch (error) {
            // Silent error handling
        }
    }
}

// Initialize session monitor when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure all DOM elements are fully loaded
    setTimeout(function() {
        try {
            // Check for CI-based login indicators
            const hasUserMeta = document.querySelector('meta[name="user-logged-in"]')?.getAttribute('content') === 'true';
            const hasLoggedInClass = document.body?.classList.contains('logged-in') || false;
            const hasUserSession = document.cookie.includes('laravel_session') || document.cookie.includes('PHPSESSID');
            
            // Check if required modal elements exist before initializing
            const sessionExpiryModal = document.getElementById('sessionExpiryModal');
            const sessionExpiredModal = document.getElementById('sessionExpiredModal');
            
            if (!sessionExpiryModal || !sessionExpiredModal) {
                return;
            }
            
            // Only initialize if user is logged in (check multiple indicators for CI-based login)
            if (hasUserMeta || hasLoggedInClass || hasUserSession) {
                new SessionMonitor();
            }
        } catch (error) {
            // Silent error handling
        }
    }, 100); // 100ms delay to ensure DOM is fully ready
});

// Export for potential external use
window.SessionMonitor = SessionMonitor;
