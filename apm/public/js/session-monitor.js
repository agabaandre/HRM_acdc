/**
 * Session Monitor
 * Monitors session expiry and shows warning dialogs
 */
class SessionMonitor {
    constructor() {
        this.warningTime = 30; // 30 seconds before expiry (for testing)
        this.checkInterval = 5; // Check every 5 seconds (for testing)
        this.countdownInterval = null;
        this.warningShown = false;
        this.sessionLifetime = 2 * 60; // 2 minutes in seconds (for testing)
        this.lastActivity = Date.now();
        this.apiBaseUrl = document.querySelector('meta[name="api-base-url"]')?.getAttribute('content') || '/api';
        
        this.init();
    }

    init() {
        // Show test mode indicator
        this.showTestModeIndicator();
        
        // Track user activity
        this.trackActivity();
        
        // Start session monitoring
        this.startMonitoring();
        
        // Bind event listeners
        this.bindEvents();
    }

    showTestModeIndicator() {
        // Create a test mode indicator
        const indicator = document.createElement('div');
        indicator.id = 'session-test-indicator';
        indicator.innerHTML = `
            <div style="position: fixed; top: 10px; right: 10px; background: #ff6b6b; color: white; padding: 8px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; z-index: 9999; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                🧪 SESSION TEST MODE<br>
                <small>Expires in 2 minutes</small>
            </div>
        `;
        document.body.appendChild(indicator);
        
        // Remove indicator after 10 seconds
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 10000);
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
        this.warningShown = true;
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiryModal'));
        modal.show();
        
        this.startCountdown(timeUntilExpiry);
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

    showExpired() {
        this.hideWarning();
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiredModal'));
        modal.show();
        
        // Redirect to login after 3 seconds
        setTimeout(() => {
            window.location.href = '/login';
        }, 3000);
    }

    hideWarning() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('sessionExpiryModal'));
        if (modal) {
            modal.hide();
        }
        
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }

    resetWarning() {
        this.warningShown = false;
        this.hideWarning();
    }

    async extendSession() {
        try {
            console.log('SessionMonitor: Attempting to extend session via POST to:', this.apiBaseUrl + '/extend-session');
            const response = await fetch(this.apiBaseUrl + '/extend-session', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

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
            this.showExpired();
        }
    }

    logoutNow() {
        window.location.href = '/logout';
    }

    redirectToLogin() {
        window.location.href = '/login';
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

    bindEvents() {
        // Extend session button
        document.getElementById('extendSessionBtn')?.addEventListener('click', () => {
            this.extendSession();
        });

        // Logout now button
        document.getElementById('logoutNowBtn')?.addEventListener('click', () => {
            this.logoutNow();
        });

        // Redirect to login button
        document.getElementById('redirectToLoginBtn')?.addEventListener('click', () => {
            this.redirectToLogin();
        });

        // Handle modal hidden events
        document.getElementById('sessionExpiryModal')?.addEventListener('hidden.bs.modal', () => {
            this.resetWarning();
        });
    }
}

// Initialize session monitor when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('SessionMonitor: DOM loaded, checking if user is logged in...');
    console.log('SessionMonitor: user-logged-in meta:', document.querySelector('meta[name="user-logged-in"]')?.getAttribute('content'));
    console.log('SessionMonitor: body classes:', document.body.className);
    
    // Only initialize if user is logged in
    if (document.body.classList.contains('logged-in') || 
        document.querySelector('meta[name="user-logged-in"]')?.getAttribute('content') === 'true') {
        console.log('SessionMonitor: User is logged in, initializing session monitor...');
        new SessionMonitor();
    } else {
        console.log('SessionMonitor: User is not logged in, skipping session monitor initialization');
    }
});

// Export for potential external use
window.SessionMonitor = SessionMonitor;
