<!-- Session Expiry Warning Modal -->
<div class="modal fade" id="sessionExpiryModal" tabindex="-1" aria-labelledby="sessionExpiryModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-warning" id="sessionExpiryModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Session Expiring Soon
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-clock text-warning" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3">Your session will expire in <span id="countdownTimer" class="text-danger fw-bold">5:00</span></h6>
                <p class="text-muted mb-4">
                    For security reasons, you will be automatically logged out. 
                    Would you like to extend your session?
                </p>
                
                <!-- Progress bar -->
                <div class="progress mb-4" style="height: 6px;">
                    <div class="progress-bar bg-warning" id="sessionProgressBar" role="progressbar" style="width: 100%"></div>
                </div>
                
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-success" id="extendSessionBtn">
                        <i class="fas fa-clock me-1"></i>
                        Keep Me Logged In
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="logoutNowBtn">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Log Out Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session Expired Modal -->
<div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-labelledby="sessionExpiredModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="sessionExpiredModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Session Expired
                </h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-lock text-danger" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3 text-danger">Your session has expired</h6>
                <p class="text-muted mb-4">
                    For security reasons, you have been automatically logged out. 
                    Please log in again to continue.
                </p>
                
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-primary" id="redirectToLoginBtn">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        Go to Login
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#sessionExpiryModal .modal-content,
#sessionExpiredModal .modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#sessionExpiryModal .modal-header,
#sessionExpiredModal .modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 0.375rem 0.375rem 0 0;
}

#sessionExpiryModal .btn-success,
#sessionExpiredModal .btn-primary {
    min-width: 140px;
    font-weight: 500;
}

#sessionExpiryModal .btn-outline-secondary {
    min-width: 120px;
    font-weight: 500;
}

#countdownTimer {
    font-size: 1.5rem;
    font-family: 'Courier New', monospace;
}

.progress {
    border-radius: 3px;
}

.progress-bar {
    transition: width 1s linear;
}
</style>
