<div class="login-form-container mx-auto" style="max-width: 400px; width: 100%;">
    <div class="text-center d-md-none mb-4">
        <img src="{{ \App\Support\CbpAsset::url('images/AU_CDC_Logo-800.png') }}" alt="Africa CDC" width="140" class="img-fluid mb-2">
    </div>

    <div class="form-title text-center mb-4">
        <h2 class="fw-semibold mb-1" style="color: var(--text-dark, #2c3e50); font-size: 2rem;">Sign In</h2>
        <p class="text-muted mb-0">Choose your preferred sign-in method</p>
    </div>

    @if ($flashError)
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $flashError }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($flashSuccess)
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ $flashSuccess }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($microsoftEnabled)
        <a href="{{ route('auth.microsoft.redirect') }}"
           class="btn btn-ms w-100 d-flex align-items-center justify-content-center gap-2 mb-3 text-decoration-none">
            <i class="fab fa-microsoft"></i>
            Sign in with Staff Email
        </a>
    @else
        <div class="alert alert-warning small py-2 mb-3">
            Microsoft sign-in is not configured. Set <code>TENANT_ID</code>, <code>CLIENT_ID</code>, and <code>CLIENT_SEC_VALUE</code> in <code>.env</code>.
        </div>
    @endif

    @if ($allowAlternativeLogin)
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="toggleAlternative" wire:model.live="showAlternative">
            <label class="form-check-label text-muted" for="toggleAlternative">
                Use alternative sign-in method
            </label>
        </div>

        <div @class(['form-toggle', 'active' => $showAlternative]) id="alternativeSignIn" style="{{ $showAlternative ? '' : 'display: none;' }}">
            @if ($showAlternative)
                <form wire:submit="login" class="mt-2">
                    <div class="mb-3">
                        <label for="inputEmail" class="form-label fw-medium">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                            <input wire:model="email" type="email" id="inputEmail"
                                   class="form-control border-start-0 @error('email') is-invalid @enderror"
                                   placeholder="Enter your email address" autocomplete="username" required>
                        </div>
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="inputPassword" class="form-label fw-medium">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                            <input wire:model="password" type="password" id="inputPassword"
                                   class="form-control border-start-0 @error('password') is-invalid @enderror"
                                   placeholder="Enter your password" autocomplete="current-password" required>
                        </div>
                        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check mb-3">
                        <input wire:model="remember" type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" wire:loading.attr="disabled"
                            style="background: var(--primary-color, #119a48); border-color: var(--primary-color, #119a48);">
                        <span wire:loading.remove><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                        <span wire:loading><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
                    </button>
                </form>
            @endif
        </div>
    @endif

    <div class="footer text-center mt-4 pt-3 border-top">
        @if (!empty($apmBaseUrl))
            <p class="small mb-2">
                <a href="{{ $apmBaseUrl }}/faq" target="_blank" rel="noopener" class="text-decoration-none" style="color: var(--primary-color, #119a48);">FAQ</a>
                <span class="mx-2 text-muted">|</span>
                <a href="{{ $apmBaseUrl }}/help" target="_blank" rel="noopener" class="text-decoration-none" style="color: var(--primary-color, #119a48);">Help</a>
            </p>
        @endif
        <p class="text-muted small mb-0">&copy; {{ date('Y') }} Africa CDC. All rights reserved.</p>
    </div>
</div>

@push('styles')
<style>
    .btn-ms {
        background-color: #0078d4;
        border: 2px solid #0078d4;
        color: #fff;
        padding: 14px 20px;
        font-weight: 600;
        border-radius: 6px;
        transition: all 0.25s ease;
    }
    .btn-ms:hover {
        background-color: #106ebe;
        border-color: #106ebe;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(0, 120, 212, 0.35);
    }
    .form-toggle.active { animation: slideDown 0.3s ease; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .form-control:focus {
        border-color: var(--primary-color, #119a48);
        box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.2);
    }
    .form-check-input:checked {
        background-color: var(--primary-color, #119a48);
        border-color: var(--primary-color, #119a48);
    }
</style>
@endpush
