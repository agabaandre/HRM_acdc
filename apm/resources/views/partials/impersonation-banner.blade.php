@if(session('original_user') && data_get(session('user'), 'is_impersonated'))
    @php
        $originalUser = session('original_user', []);
        $impersonationStart = (int) session('impersonation_start', time());
        $remainingSeconds = max(0, 300 - (time() - $impersonationStart));
        $minutesRemaining = (int) floor($remainingSeconds / 60);
        $secondsRemaining = $remainingSeconds % 60;
    @endphp
    <div class="apm-impersonation-banner" id="apmImpersonationBanner">
        <div class="container-fluid">
            <div class="row align-items-center g-2">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center">
                        <div class="me-3 fs-3 text-warning">
                            <i class="bx bx-user-voice"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-warning mb-1">
                                <i class="bx bx-error-circle me-1"></i>Impersonation mode active
                            </div>
                            <div class="small text-white">
                                Viewing APM as <strong>{{ session('user.name') }}</strong>
                                (admin: <strong>{{ $originalUser['name'] ?? 'Admin' }}</strong>)
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <span class="badge bg-warning text-dark" id="apmSessionTimer" title="Suggested review window (5 minutes)">
                            {{ sprintf('%02d:%02d', $minutesRemaining, $secondsRemaining) }}
                        </span>
                        <a href="{{ route('apm-api-users.revert') }}" class="btn btn-sm btn-light fw-semibold">
                            <i class="bx bx-undo me-1"></i>Revert to admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .apm-impersonation-banner {
            background: linear-gradient(135deg, #b42318 0%, #912018 100%);
            color: #fff;
            padding: 0.75rem 0;
            border-bottom: 3px solid #f79009;
            position: relative;
            z-index: 1040;
        }
    </style>
    <script>
        (function () {
            var remaining = {{ $remainingSeconds }};
            var el = document.getElementById('apmSessionTimer');
            if (!el || remaining <= 0) {
                return;
            }
            var timer = setInterval(function () {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(timer);
                    el.textContent = '00:00';
                    return;
                }
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }, 1000);
        })();
    </script>
@endif
