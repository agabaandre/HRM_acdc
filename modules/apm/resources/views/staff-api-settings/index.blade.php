@php
    $settings = $panelData['settings'] ?? [];
    $resolved = $panelData['resolved'] ?? [];
    $sources = $panelData['sources'] ?? [];
@endphp

<div class="staff-api-settings-panel">
    @if(session('msg'))
        <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show" role="alert">
            {{ session('msg') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <h5 class="alert-heading mb-2"><i class="bx bx-key me-1"></i> Staff Share API</h5>
        <p class="mb-1 small">DB values override env. Empty DB fields fall back to env, then the portal default static token.</p>
        <p class="mb-0 small text-muted">
            Effective base: <code>{{ $resolved['base_url'] ?? '—' }}</code>
            · docs: <a href="{{ $panelData['docs_url'] ?? '#' }}" target="_blank" rel="noopener">Share docs</a>
            · portal: <a href="{{ $panelData['portal_staff_api_url'] ?? '#' }}" target="_blank" rel="noopener">Staff API settings</a>
        </p>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h6 class="mb-0">Resolved (effective)</h6></div>
        <div class="card-body small">
            <div><strong>Base:</strong> <code>{{ $resolved['base_url'] ?? '' }}</code> ({{ $sources['base_url'] ?? '—' }})</div>
            <div><strong>User:</strong> {{ $resolved['username'] ?: '—' }} ({{ $sources['username'] ?? '—' }})</div>
            <div><strong>Password:</strong> {{ !empty($resolved['password_configured']) ? 'set' : 'empty' }} ({{ $sources['password'] ?? '—' }})</div>
            <div><strong>Token:</strong> {{ $resolved['token_preview'] ?? '—' }} ({{ $sources['token'] ?? '—' }})</div>
        </div>
    </div>

    <form method="POST" action="{{ $panelData['update_url'] ?? route('staff-api-settings.update') }}" class="card border-0 shadow-sm mb-4">
        @csrf
        <div class="card-header bg-white py-3"><h6 class="mb-0">DB overrides</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label" for="staff_api_base_url">Base URL</label>
                    <input type="text" class="form-control" id="staff_api_base_url" name="staff_api_base_url"
                        value="{{ old('staff_api_base_url', $settings['staff_api_base_url'] ?? '') }}"
                        placeholder="Leave empty to use env / default">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="staff_api_username">Username</label>
                    <input type="text" class="form-control" id="staff_api_username" name="staff_api_username"
                        value="{{ old('staff_api_username', $settings['staff_api_username'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="staff_api_password">Password</label>
                    <input type="password" class="form-control" id="staff_api_password" name="staff_api_password"
                        placeholder="Leave blank to keep" autocomplete="new-password">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="clear_password" value="1" id="clear_password">
                        <label class="form-check-label" for="clear_password">Clear DB password</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="staff_api_token">Static token</label>
                    <input type="password" class="form-control" id="staff_api_token" name="staff_api_token"
                        placeholder="Leave blank to keep" autocomplete="new-password">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="clear_token" value="1" id="clear_token">
                        <label class="form-check-label" for="clear_token">Clear DB token (fall back to env / default)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="submit" class="btn btn-success btn-sm"><i class="bx bx-save"></i> Save</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h6 class="mb-0">Connection test</h6></div>
        <div class="card-body">
            <div id="staff-api-test-alert" class="alert d-none" role="alert"></div>
            <pre id="staff-api-test-details" class="small bg-light p-3 rounded d-none"></pre>
            <button type="button" class="btn btn-outline-primary btn-sm" id="staff-api-test-btn">
                <i class="bx bx-wifi"></i> Test connection
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('staff-api-test-btn');
    const alertEl = document.getElementById('staff-api-test-alert');
    const details = document.getElementById('staff-api-test-details');
    if (!btn) return;
    btn.addEventListener('click', async function () {
        btn.disabled = true;
        try {
            const body = {
                staff_api_base_url: document.getElementById('staff_api_base_url')?.value || '',
                staff_api_username: document.getElementById('staff_api_username')?.value || '',
                staff_api_password: document.getElementById('staff_api_password')?.value || '',
                staff_api_token: document.getElementById('staff_api_token')?.value || '',
            };
            const res = await fetch(@json($panelData['test_url'] ?? route('staff-api-settings.test')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            alertEl.className = 'alert alert-' + (data.success ? 'success' : 'danger');
            alertEl.textContent = data.message || (data.success ? 'CONNECTED' : 'FAILED');
            alertEl.classList.remove('d-none');
            if (Array.isArray(data.details)) {
                details.textContent = data.details.join('\n');
                details.classList.remove('d-none');
            }
        } catch (e) {
            alertEl.className = 'alert alert-danger';
            alertEl.textContent = e.message || 'Request failed';
            alertEl.classList.remove('d-none');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endpush
