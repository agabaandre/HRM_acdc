<?php
// Check if user is being impersonated
$is_impersonated = $this->session->userdata('is_impersonated') || 
                   (isset($this->session->userdata('user')->is_impersonated) && $this->session->userdata('user')->is_impersonated);

if ($is_impersonated):
    $original_user = $this->session->userdata('original_user');
    $impersonation_start = $this->session->userdata('impersonation_start');
    $current_time = time();
    $elapsed_time = $current_time - $impersonation_start;
    $remaining_time = 300 - $elapsed_time; // 5 minutes total
    $minutes_remaining = floor($remaining_time / 60);
    $seconds_remaining = $remaining_time % 60;
?>

<!-- Impersonation Warning Banner -->
<div class="impersonation-banner" id="impersonationBanner">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <div class="impersonation-icon me-3">
                        <i class="fa fa-user-secret fa-2x text-warning"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 text-warning fw-bold">
                            <i class="fa fa-exclamation-triangle me-2"></i>IMPERSONATION MODE ACTIVE
                        </h5>
                        <p class="mb-0 text-white">
                            You are currently impersonating <strong><?= $this->session->userdata('user')->name ?></strong> 
                            as <strong><?= $original_user->name ?></strong> (Admin)
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <!-- Timer Display -->
                    <div class="impersonation-timer me-3">
                        <small class="text-white-50 d-block">Session expires in:</small>
                        <span class="badge bg-warning text-dark fs-6" id="sessionTimer">
                            <?= sprintf('%02d:%02d', $minutes_remaining, $seconds_remaining) ?>
                        </span>
                    </div>
                    
                    <!-- Revert Button -->
                    <a href="<?= base_url('auth/revert') ?>" class="btn btn-danger btn-sm">
                        <i class="fa fa-undo me-1"></i>Revert to Admin
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.impersonation-banner {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 1rem 0;
    border-bottom: 3px solid #ffc107;
    position: sticky;
    top: 0;
    z-index: 1050;
    box-shadow: 0 2px 10px rgba(220, 53, 69, 0.3);
}

.impersonation-icon {
    animation: pulse 2s infinite;
}

.impersonation-timer {
    text-align: center;
}

.impersonation-timer .badge {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .impersonation-banner .col-md-8,
    .impersonation-banner .col-md-4 {
        text-align: center;
        margin-bottom: 1rem;
    }
    
    .impersonation-banner .d-flex {
        justify-content: center;
    }
    
    .impersonation-banner .text-end {
        text-align: center !important;
    }
}
</style>

<script>
$(document).ready(function() {
    // Session countdown timer
    let remainingTime = <?= $remaining_time ?>;
    
    function updateTimer() {
        if (remainingTime <= 0) {
            // Session expired, redirect to revert
            window.location.href = '<?= base_url("auth/revert") ?>';
            return;
        }
        
        const minutes = Math.floor(remainingTime / 60);
        const seconds = remainingTime % 60;
        
        $('#sessionTimer').text(
            String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0')
        );
        
        // Change color when less than 1 minute remaining
        if (remainingTime < 60) {
            $('#sessionTimer').removeClass('bg-warning').addClass('bg-danger');
        }
        
        remainingTime--;
    }
    
    // Update timer every second
    setInterval(updateTimer, 1000);
    
    // Auto-hide banner after 10 seconds (optional)
    setTimeout(function() {
        $('#impersonationBanner').fadeOut(1000);
    }, 10000);
    
    // Show banner on hover over any element
    $('body').hover(function() {
        $('#impersonationBanner').fadeIn(500);
    });
});
</script>

<?php endif; ?>
