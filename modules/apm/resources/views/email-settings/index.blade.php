@php
    $settings = $panelData['settings'] ?? [];
    $status = $panelData['status'] ?? [];
    $dispatch = old('staff_mail_dispatch', $settings['staff_mail_dispatch'] ?? 'auto');
    $transport = old('mail_transport', $settings['mail_transport'] ?? 'exchange');
@endphp

<div class="email-settings-panel">
    @if(session('msg'))
        <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show" role="alert">
            {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
            <div>
                <h5 class="alert-heading mb-2"><i class="bx bx-envelope me-1"></i> Outbound email</h5>
                <p class="mb-2 small">
                    APM sends via the Staff Portal mail hub when
                    <code>STAFF_MAIL_DISPATCH</code> is <strong>auto</strong> or <strong>portal</strong>.
                    Configure providers (HTTP / Exchange / SMTP) in Staff Portal → Email settings.
                </p>
                <p class="mb-0 small text-muted">
                    Share API: <code>{{ $status['share_base'] ?? '—' }}</code>
                    · Portal mail client: {{ !empty($status['portal_mail_client']) ? 'available' : 'missing' }}
                </p>
            </div>
            <a href="{{ $panelData['portal_email_url'] ?? '#' }}" class="btn btn-success btn-sm" target="_blank" rel="noopener">
                <i class="bx bx-link-external"></i> Staff Portal Email settings
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">HTTP (notifications API)</div>
                    <div class="fw-semibold">
                        @if(!empty($status['http_configured']))
                            <span class="text-success"><i class="bx bx-check-circle"></i> Configured</span>
                        @else
                            <span class="text-secondary"><i class="bx bx-x-circle"></i> Not set in .env</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Exchange / Graph</div>
                    <div class="fw-semibold">
                        @if(!empty($status['exchange_configured']))
                            <span class="text-success"><i class="bx bx-check-circle"></i> Configured</span>
                        @else
                            <span class="text-secondary"><i class="bx bx-x-circle"></i> Not set in .env</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">SMTP</div>
                    <div class="fw-semibold">
                        @if(!empty($status['smtp_configured']))
                            <span class="text-success"><i class="bx bx-check-circle"></i> Configured</span>
                        @else
                            <span class="text-secondary"><i class="bx bx-x-circle"></i> Not set in .env</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ $panelData['update_url'] ?? route('email-settings.update') }}" class="card border-0 shadow-sm mb-4">
        @csrf
        <div class="card-header bg-white py-3">
            <h6 class="mb-0"><i class="bx bx-slider-alt me-1 text-success"></i> Dispatch &amp; local fallback</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="staff_mail_dispatch">STAFF_MAIL_DISPATCH</label>
                    <select class="form-select" id="staff_mail_dispatch" name="staff_mail_dispatch" required>
                        <option value="auto" {{ $dispatch === 'auto' ? 'selected' : '' }}>auto — portal first, then local</option>
                        <option value="portal" {{ $dispatch === 'portal' ? 'selected' : '' }}>portal — Share /share/mail/send only</option>
                        <option value="local" {{ $dispatch === 'local' ? 'selected' : '' }}>local — MAIL_TRANSPORT only</option>
                    </select>
                    <div class="form-text">Preferred: <code>auto</code>. Provider secrets live in Staff Portal Email settings.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mail_transport">MAIL_TRANSPORT (local fallback)</label>
                    <select class="form-select" id="mail_transport" name="mail_transport" required>
                        @foreach (['http' => 'HTTP (notifications.africacdc.org)', 'exchange' => 'Exchange / Microsoft Graph', 'smtp' => 'SMTP', 'zoho' => 'Zoho SMTP', 'log' => 'Log only'] as $value => $label)
                            <option value="{{ $value }}" {{ $transport === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_from_address">From address</label>
                    <input type="email" class="form-control" id="mail_from_address" name="mail_from_address"
                        value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}"
                        placeholder="noreply@africacdc.org">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_from_name">From name</label>
                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name"
                        value="{{ old('mail_from_name', $settings['mail_from_name'] ?? 'Africa CDC APM') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mail_subject_prefix">Subject prefix</label>
                    <input type="text" class="form-control" id="mail_subject_prefix" name="mail_subject_prefix"
                        value="{{ old('mail_subject_prefix', $settings['mail_subject_prefix'] ?? 'APM') }}">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bx bx-save"></i> Save email settings
            </button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0"><i class="bx bx-send me-1 text-primary"></i> Send test email</h6>
        </div>
        <div class="card-body">
            <div id="email-settings-test-alert" class="alert d-none" role="alert"></div>
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="email_test_to">Recipient</label>
                    <input type="email" class="form-control" id="email_test_to" placeholder="you@africacdc.org">
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-outline-primary btn-sm w-100" id="email-settings-test-btn">
                        <i class="bx bx-send"></i> Send test
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">Uses the same path as notifications (<code>sendEmail</code> → portal hub when dispatch is auto/portal).</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('email-settings-test-btn');
    const input = document.getElementById('email_test_to');
    const alertEl = document.getElementById('email-settings-test-alert');
    if (!btn || !input || !alertEl) return;

    function showAlert(ok, msg) {
        alertEl.className = 'alert alert-' + (ok ? 'success' : 'danger');
        alertEl.textContent = msg;
        alertEl.classList.remove('d-none');
    }

    btn.addEventListener('click', async function () {
        const to = (input.value || '').trim();
        if (!to) {
            showAlert(false, 'Enter a recipient email.');
            return;
        }
        btn.disabled = true;
        try {
            const res = await fetch(@json($panelData['test_url'] ?? route('email-settings.test')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ to }),
            });
            const data = await res.json().catch(() => ({}));
            showAlert(!!data.success, data.message || (res.ok ? 'Sent.' : 'Send failed.'));
        } catch (e) {
            showAlert(false, e.message || 'Request failed');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endpush
