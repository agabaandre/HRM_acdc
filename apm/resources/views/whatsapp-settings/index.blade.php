@php
    $settings = $panelData['settings'] ?? [];
    $passwordConfigured = (bool) ($settings['whatsapp_bot_admin_password_configured'] ?? false);
@endphp

<div id="whatsapp-settings-panel" class="whatsapp-settings-panel">
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
            <div>
                <h5 class="alert-heading mb-2"><i class="bx bxl-whatsapp me-1"></i> WhatsApp bot integration</h5>
                <p class="mb-2 small">
                    Connect APM to a self-hosted
                    <a href="{{ $panelData['bot_repo_url'] ?? 'https://github.com/jacktheboss220/WhatsAppBotMultiDevice' }}" target="_blank" rel="noopener">WhatsAppBotMultiDevice</a>
                    instance for staff group management. Configure the bot URL and admin password here, then manage groups from
                    <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" wire:navigate>Staff → WhatsApp groups</a>.
                </p>
                <p class="mb-0 small text-muted">
                    Bot number is stored here and shown on the groups screen. Use digits only with country code (e.g. <code>251911234567</code>).
                </p>
            </div>
            <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" class="btn btn-success btn-sm" wire:navigate>
                <i class="bx bxl-whatsapp"></i> Open group manager
            </a>
        </div>
    </div>

    <form method="POST" action="{{ $panelData['update_url'] ?? route('whatsapp-settings.update') }}" class="whatsapp-settings-form">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_bot_enabled" name="whatsapp_bot_enabled" value="1"
                        {{ !empty($settings['whatsapp_bot_enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="whatsapp_bot_enabled">Enable WhatsApp integration</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="whatsapp_bot_api_url">Bot API URL</label>
                <input type="url" class="form-control" id="whatsapp_bot_api_url" name="whatsapp_bot_api_url"
                    value="{{ old('whatsapp_bot_api_url', $settings['whatsapp_bot_api_url'] ?? '') }}"
                    placeholder="http://127.0.0.1:8000">
                <div class="form-text">Base URL where WhatsAppBotMultiDevice is running (no trailing slash).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="whatsapp_bot_number">Bot / owner number</label>
                <input type="text" class="form-control" id="whatsapp_bot_number" name="whatsapp_bot_number"
                    value="{{ old('whatsapp_bot_number', $settings['whatsapp_bot_number'] ?? '') }}"
                    placeholder="251911234567">
                <div class="form-text">Digits only, with country code. Matches <code>MY_NUMBER</code> / <code>BOT_NUMBER</code> in the bot <code>.env</code>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="whatsapp_bot_admin_password">Admin password</label>
                <input type="password" class="form-control" id="whatsapp_bot_admin_password" name="whatsapp_bot_admin_password"
                    placeholder="{{ $passwordConfigured ? '•••••••• (leave blank to keep)' : 'ADMIN_PASSWORD from bot .env' }}" autocomplete="new-password">
            </div>
            <div class="col-md-6">
                <label class="form-label">Primary staff group</label>
                <input type="text" class="form-control" readonly
                    value="{{ ($settings['whatsapp_primary_group_name'] ?? '') !== '' ? $settings['whatsapp_primary_group_name'] : 'Not selected yet' }}">
                <div class="form-text">
                    @if (!empty($settings['whatsapp_primary_group_jid']))
                        <code class="small">{{ $settings['whatsapp_primary_group_jid'] }}</code>
                    @else
                        Choose a group in the <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" wire:navigate>group manager</a>.
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-success">
                <i class="bx bx-save"></i> Save WhatsApp settings
            </button>
            <button type="button" class="btn btn-outline-secondary" id="whatsapp-test-connection"
                data-url="{{ $panelData['test_url'] ?? route('whatsapp-settings.test') }}">
                <i class="bx bx-plug"></i> Test connection
            </button>
        </div>
    </form>

    <pre id="whatsapp-test-output" class="whatsapp-test-output mt-3 d-none"></pre>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('whatsapp-test-connection');
    const out = document.getElementById('whatsapp-test-output');
    if (!btn || !out) return;
    btn.addEventListener('click', async function () {
        btn.disabled = true;
        out.classList.remove('d-none');
        out.textContent = 'Testing connection…';
        try {
            const res = await fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            out.textContent = JSON.stringify(json.data ?? json, null, 2);
        } catch (e) {
            out.textContent = 'Request failed: ' + e.message;
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endpush
