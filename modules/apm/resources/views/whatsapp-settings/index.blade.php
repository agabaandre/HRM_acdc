@php
    $settings = $panelData['settings'] ?? [];
    $driver = old('whatsapp_driver', $settings['whatsapp_driver'] ?? 'native');
    $isNative = $driver === 'native';
    $secretConfigured = (bool) ($settings['whatsapp_bot_admin_password_configured'] ?? false);
@endphp

<div id="whatsapp-settings-panel" class="whatsapp-settings-panel">
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
            <div>
                <h5 class="alert-heading mb-2"><i class="bx bxl-whatsapp me-1"></i> WhatsApp platform</h5>
                <p class="mb-2 small">
                    Manage staff WhatsApp groups from
                    <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" wire:navigate>Staff → WhatsApp groups</a>.
                    <strong>Native</strong> mode runs the APM WhatsApp worker (Node + MySQL). <strong>External</strong> mode proxies to a third-party
                    <a href="https://github.com/jacktheboss220/WhatsAppBotMultiDevice" target="_blank" rel="noopener">WhatsAppBotMultiDevice</a> instance.
                </p>
                <p class="mb-0 small text-muted">
                    Bot number: digits only with country code (e.g. <code>256702787688</code>).
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
                <label class="form-label" for="whatsapp_driver">Platform driver</label>
                <select class="form-select" id="whatsapp_driver" name="whatsapp_driver">
                    <option value="native" {{ $isNative ? 'selected' : '' }}>Native (APM worker + MySQL)</option>
                    <option value="external" {{ ! $isNative ? 'selected' : '' }}>External (WhatsAppBotMultiDevice)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="whatsapp_bot_number">Bot / owner number</label>
                <input type="text" class="form-control" id="whatsapp_bot_number" name="whatsapp_bot_number"
                    value="{{ old('whatsapp_bot_number', $settings['whatsapp_bot_number'] ?? '') }}"
                    placeholder="256702787688">
            </div>
            <div class="col-md-6 whatsapp-native-field" @if(! $isNative) style="display:none" @endif>
                <label class="form-label" for="whatsapp_group_sync_keyword">Group sync keyword</label>
                <input type="text" class="form-control" id="whatsapp_group_sync_keyword" name="whatsapp_group_sync_keyword"
                    value="{{ old('whatsapp_group_sync_keyword', $settings['whatsapp_group_sync_keyword'] ?? 'Africa CDC') }}"
                    placeholder="Africa CDC">
                <div class="form-text">Only WhatsApp groups whose name contains this text are kept during sync. Groups without the keyword are removed from APM.</div>
            </div>

            <div class="col-md-6 whatsapp-native-field" @if(! $isNative) style="display:none" @endif>
                <label class="form-label" for="whatsapp_worker_url">Worker URL</label>
                <input type="url" class="form-control" id="whatsapp_worker_url" name="whatsapp_worker_url"
                    value="{{ old('whatsapp_worker_url', $settings['whatsapp_worker_url'] ?? 'http://127.0.0.1:8765') }}"
                    placeholder="http://127.0.0.1:8765">
                <div class="form-text">Node worker in <code>apm/whatsapp-service</code> (default port 8765).</div>
            </div>
            <div class="col-md-6 whatsapp-native-field" @if(! $isNative) style="display:none" @endif>
                <label class="form-label" for="whatsapp_worker_token">Worker token</label>
                <input type="password" class="form-control" id="whatsapp_worker_token" name="whatsapp_worker_token"
                    placeholder="{{ !empty($settings['whatsapp_worker_token_configured']) ? '•••••••• (leave blank to keep)' : 'Set token (also in worker .env)' }}" autocomplete="new-password">
            </div>

            <div class="col-md-6 whatsapp-external-field" @if($isNative) style="display:none" @endif>
                <label class="form-label" for="whatsapp_bot_api_url">External bot API URL</label>
                <input type="url" class="form-control" id="whatsapp_bot_api_url" name="whatsapp_bot_api_url"
                    value="{{ old('whatsapp_bot_api_url', $settings['whatsapp_bot_api_url'] ?? '') }}"
                    placeholder="http://127.0.0.1:8000">
            </div>
            <div class="col-md-6 whatsapp-external-field" @if($isNative) style="display:none" @endif>
                <label class="form-label" for="whatsapp_bot_admin_password">External admin password</label>
                <input type="password" class="form-control" id="whatsapp_bot_admin_password" name="whatsapp_bot_admin_password"
                    placeholder="{{ $secretConfigured ? '•••••••• (leave blank to keep)' : 'ADMIN_PASSWORD from bot .env' }}" autocomplete="new-password">
            </div>

            <div class="col-md-6">
                <label class="form-label">Primary staff group</label>
                <input type="text" class="form-control" readonly
                    value="{{ ($settings['whatsapp_primary_group_name'] ?? '') !== '' ? $settings['whatsapp_primary_group_name'] : 'Not selected yet' }}">
                <div class="form-text">
                    @if (!empty($settings['whatsapp_primary_group_jid']))
                        <code class="small">{{ $settings['whatsapp_primary_group_jid'] }}</code>
                    @else
                        Choose in the <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" wire:navigate>group manager</a>.
                    @endif
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3"><i class="bx bx-lock-alt"></i> Access control</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="whatsapp_module_grant_all" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_module_grant_all"
                                        name="whatsapp_module_grant_all" value="1"
                                        {{ !empty($settings['whatsapp_module_grant_all']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="whatsapp_module_grant_all">Allow all staff to use WhatsApp groups</label>
                                </div>
                                <label class="form-label" for="whatsapp_module_permission_ids">Module permission IDs</label>
                                <input type="text" class="form-control" id="whatsapp_module_permission_ids" name="whatsapp_module_permission_ids"
                                    value="{{ old('whatsapp_module_permission_ids', $settings['whatsapp_module_permission_ids'] ?? '') }}"
                                    placeholder="e.g. 89, 92">
                                <div class="form-text">Used when “allow all staff” is off. Comma-separated staff portal permission codes. Role 10 always has access.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="whatsapp_config_admin_only" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_config_admin_only"
                                        name="whatsapp_config_admin_only" value="1"
                                        {{ !empty($settings['whatsapp_config_admin_only']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="whatsapp_config_admin_only">Restrict configuration to admin role (10)</label>
                                </div>
                                <label class="form-label" for="whatsapp_config_permission_ids">Extra config permission IDs</label>
                                <input type="text" class="form-control" id="whatsapp_config_permission_ids" name="whatsapp_config_permission_ids"
                                    value="{{ old('whatsapp_config_permission_ids', $settings['whatsapp_config_permission_ids'] ?? '') }}"
                                    placeholder="e.g. 89">
                                <div class="form-text">Role 10 always has config access. Add permission IDs to also allow non-admin users when admin-only is on, or to define access when admin-only is off.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-success">
                <i class="bx bx-save"></i> Save settings
            </button>
            <button type="button" class="btn btn-outline-secondary" id="whatsapp-test-connection"
                data-url="{{ $panelData['test_url'] ?? route('whatsapp-settings.test') }}">
                <i class="bx bx-plug"></i> Test connection
            </button>
            <button type="button" class="btn btn-outline-primary whatsapp-native-field" id="whatsapp-bootstrap"
                data-url="{{ $panelData['bootstrap_url'] ?? route('whatsapp-settings.bootstrap') }}" @if(! $isNative) style="display:none" @endif>
                <i class="bx bx-cog"></i> Setup worker (.env)
            </button>
            <button type="button" class="btn btn-outline-primary whatsapp-native-field" id="whatsapp-sync-groups"
                data-url="{{ $panelData['sync_url'] ?? route('whatsapp-settings.sync') }}" @if(! $isNative) style="display:none" @endif>
                <i class="bx bx-refresh"></i> Sync all matching groups
            </button>
            <button type="button" class="btn btn-outline-success whatsapp-native-field" id="whatsapp-sync-primary"
                data-url="{{ $panelData['sync_primary_url'] ?? route('whatsapp-settings.sync-primary') }}"
                data-primary-name="{{ $settings['whatsapp_primary_group_name'] ?? '' }}"
                @if(! $isNative || empty($settings['whatsapp_primary_group_jid'])) disabled @endif
                @if(! $isNative) style="display:none" @endif>
                <i class="bx bx-star"></i> Sync primary group only
            </button>
        </div>
        <p class="small text-muted mt-2 mb-0 whatsapp-native-field" @if(! $isNative) style="display:none" @endif>
            <strong>Sync all matching groups</strong> imports every group whose name contains the keyword and removes the rest from APM.
            <strong>Sync primary group only</strong> refreshes just the primary staff group
            @if (!empty($settings['whatsapp_primary_group_name']))
                (<em>{{ $settings['whatsapp_primary_group_name'] }}</em>).
            @else
                — set a primary group in <a href="{{ $panelData['groups_url'] ?? route('whatsapp-groups.index') }}" wire:navigate>WhatsApp groups</a> first.
            @endif
        </p>
    </form>

    <div class="card border-0 shadow-sm mt-4 whatsapp-native-field" id="whatsapp-pair-card" @if(! $isNative) style="display:none" @endif>
        <div class="card-body">
            <h6 class="card-title mb-3"><i class="bx bx-link"></i> Link WhatsApp</h6>

            <ul class="nav nav-pills mb-3" id="whatsapp-pair-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="whatsapp-tab-qr" data-bs-toggle="pill" data-bs-target="#whatsapp-pane-qr" type="button" role="tab">QR code</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="whatsapp-tab-code" data-bs-toggle="pill" data-bs-target="#whatsapp-pane-code" type="button" role="tab">Pairing code</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="whatsapp-pane-qr" role="tabpanel">
                    <p class="small text-muted mb-3">On your phone: WhatsApp → <strong>Settings</strong> → <strong>Linked devices</strong> → <strong>Link a device</strong> → scan this QR code.</p>
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        <div class="whatsapp-qr-box" id="whatsapp-qr-box">
                            <div class="whatsapp-qr-placeholder text-muted small" id="whatsapp-qr-placeholder">QR will appear here…</div>
                            <img id="whatsapp-qr-image" class="whatsapp-qr-image d-none" alt="WhatsApp pairing QR code" width="280" height="280">
                        </div>
                        <div class="flex-grow-1" style="min-width: 14rem;">
                            <div id="whatsapp-qr-status" class="small text-muted mb-2">Start the worker, then open this tab to load the QR.</div>
                            <button type="button" class="btn btn-success btn-sm" id="whatsapp-refresh-qr"
                                data-url="{{ $panelData['qr_url'] ?? route('whatsapp-settings.qr') }}"
                                data-start-url="{{ $panelData['qr_start_url'] ?? route('whatsapp-settings.qr-start') }}">
                                <i class="bx bx-refresh"></i> Show / refresh QR
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="whatsapp-stop-qr-poll">Stop auto-refresh</button>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="whatsapp-pane-code" role="tabpanel">
                    <p class="small text-muted mb-3">On your phone: WhatsApp → Linked devices → <strong>Link with phone number instead</strong>.</p>
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <div class="flex-grow-1" style="min-width: 14rem;">
                            <label class="form-label small mb-1" for="whatsapp_pair_phone">Phone (country code, digits only)</label>
                            <input type="text" class="form-control" id="whatsapp_pair_phone"
                                value="{{ old('whatsapp_bot_number', $settings['whatsapp_bot_number'] ?? '') }}" placeholder="256702787688">
                        </div>
                        <button type="button" class="btn btn-success" id="whatsapp-get-pairing"
                            data-url="{{ $panelData['pair_url'] ?? route('whatsapp-settings.pair') }}">
                            Get pairing code
                        </button>
                    </div>
                    <div id="whatsapp-pairing-output" class="whatsapp-test-output mt-3 d-none"></div>
                </div>
            </div>
        </div>
    </div>

    @if($isNative)
        <p class="small text-muted mt-2 mb-0">
            Worker env: {{ !empty($panelData['worker_env_exists']) ? 'ready' : 'not generated yet — click Setup worker' }} ·
            Run: <code>cd apm/whatsapp-service && npm start</code>
        </p>
    @endif

    <div id="whatsapp-test-output" class="whatsapp-test-output mt-3 d-none"></div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.querySelector('.whatsapp-settings-form');
    const driverSelect = document.getElementById('whatsapp_driver');
    const btn = document.getElementById('whatsapp-test-connection');
    const syncBtn = document.getElementById('whatsapp-sync-groups');
    const syncPrimaryBtn = document.getElementById('whatsapp-sync-primary');
    const bootstrapBtn = document.getElementById('whatsapp-bootstrap');
    const pairBtn = document.getElementById('whatsapp-get-pairing');
    const qrRefreshBtn = document.getElementById('whatsapp-refresh-qr');
    const qrStopBtn = document.getElementById('whatsapp-stop-qr-poll');
    const qrImage = document.getElementById('whatsapp-qr-image');
    const qrPlaceholder = document.getElementById('whatsapp-qr-placeholder');
    const qrStatus = document.getElementById('whatsapp-qr-status');
    const qrTab = document.getElementById('whatsapp-tab-qr');
    const out = document.getElementById('whatsapp-test-output');
    const pairOut = document.getElementById('whatsapp-pairing-output');
    if (!form) return;

    let qrPollTimer = null;

    function csrfToken() {
        const input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function toggleDriverFields() {
        const native = driverSelect && driverSelect.value === 'native';
        document.querySelectorAll('.whatsapp-native-field').forEach(function (el) {
            el.style.display = native ? '' : 'none';
        });
        document.querySelectorAll('.whatsapp-external-field').forEach(function (el) {
            el.style.display = native ? 'none' : '';
        });
    }

    if (driverSelect) {
        driverSelect.addEventListener('change', toggleDriverFields);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderResult(target, data) {
        const ok = !!data.ok;
        const alertClass = ok ? 'alert-success' : 'alert-warning';
        let html = '<div class="alert ' + alertClass + ' border-0 shadow-sm mb-2"><strong>' + (ok ? 'Ready' : 'Not ready') + '</strong><br>' + escapeHtml(data.summary || data.message || '') + '</div>';
        if (data.code) {
            html += '<div class="display-6 fw-bold text-success mb-2">' + escapeHtml(data.code) + '</div>';
        }
        if (data.qr_image && target.querySelector) {
            const img = target.querySelector('#whatsapp-qr-image') || document.getElementById('whatsapp-qr-image');
            const ph = target.querySelector('#whatsapp-qr-placeholder') || document.getElementById('whatsapp-qr-placeholder');
            if (img) { img.src = data.qr_image; img.classList.remove('d-none'); }
            if (ph) ph.classList.add('d-none');
        }
        if (Array.isArray(data.hints) && data.hints.length) {
            html += '<ul class="small mb-2">';
            data.hints.forEach(function (hint) { html += '<li>' + escapeHtml(hint) + '</li>'; });
            html += '</ul>';
        }
        if (data.public_status) {
            const ps = data.public_status;
            html += '<div class="small text-muted mb-2">reachable: ' + escapeHtml(ps.reachable) + ' · connected: ' + escapeHtml(ps.connected) + ' · registered: ' + escapeHtml(ps.registered) + '</div>';
            if (ps.error) html += '<div class="small text-danger mb-2">' + escapeHtml(ps.error) + '</div>';
        }
        if (data.admin_error) html += '<div class="small text-danger mb-2">' + escapeHtml(data.admin_error) + '</div>';
        if (data.admin_stats) html += '<pre class="small bg-light p-2 rounded mb-0">' + escapeHtml(JSON.stringify(data.admin_stats, null, 2)) + '</pre>';
        if (data.synced !== undefined) html += '<div class="small text-muted">Synced groups: ' + escapeHtml(data.synced) + '</div>';
        if (data.pruned !== undefined && Number(data.pruned) > 0) {
            html += '<div class="small text-muted">Removed from APM (no keyword match): ' + escapeHtml(data.pruned) + '</div>';
        }
        if (data.keyword) html += '<div class="small text-muted">Keyword filter: ' + escapeHtml(data.keyword) + '</div>';
        if (data.scope === 'primary' && data.name) {
            html += '<div class="small text-muted">Primary group: ' + escapeHtml(data.name) + '</div>';
        }
        if (data.roster) {
            const roster = data.roster;
            if (roster.added || roster.removed) {
                html += '<div class="small text-muted">Roster: added ' + escapeHtml(roster.added || 0) + ', removed ' + escapeHtml(roster.removed || 0) + '</div>';
            }
        }
        target.innerHTML = html;
        target.classList.remove('d-none');
    }

    async function postForm(url, extra) {
        const body = new URLSearchParams();
        body.set('_token', csrfToken());
        Object.entries(extra || {}).forEach(function ([k, v]) { body.set(k, v); });
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Request failed');
        return json.data ?? json;
    }

    if (btn && out) {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            out.classList.remove('d-none');
            out.innerHTML = '<div class="text-muted small">Testing…</div>';
            try {
                const data = await postForm(btn.dataset.url, {});
                renderResult(out, data);
            } catch (e) {
                out.innerHTML = '<div class="alert alert-danger border-0 shadow-sm mb-0">' + escapeHtml(e.message) + '</div>';
            } finally {
                btn.disabled = false;
            }
        });
    }

    if (bootstrapBtn && out) {
        bootstrapBtn.addEventListener('click', async function () {
            bootstrapBtn.disabled = true;
            try {
                const data = await postForm(bootstrapBtn.dataset.url, {});
                renderResult(out, { ok: true, summary: data.message || 'Worker .env generated.', ...data });
            } catch (e) {
                renderResult(out, { ok: false, summary: e.message });
            } finally {
                bootstrapBtn.disabled = false;
            }
        });
    }

    if (syncBtn && out) {
        syncBtn.addEventListener('click', async function () {
            syncBtn.disabled = true;
            if (syncPrimaryBtn) syncPrimaryBtn.disabled = true;
            try {
                const data = await postForm(syncBtn.dataset.url, {});
                renderResult(out, { ok: true, summary: 'All matching groups synced. Non-matching groups were removed from APM.', ...data });
            } catch (e) {
                renderResult(out, { ok: false, summary: e.message });
            } finally {
                syncBtn.disabled = false;
                if (syncPrimaryBtn && syncPrimaryBtn.dataset.primaryName) {
                    syncPrimaryBtn.disabled = false;
                }
            }
        });
    }

    if (syncPrimaryBtn && out) {
        syncPrimaryBtn.addEventListener('click', async function () {
            syncPrimaryBtn.disabled = true;
            if (syncBtn) syncBtn.disabled = true;
            try {
                const data = await postForm(syncPrimaryBtn.dataset.url, {});
                const label = syncPrimaryBtn.dataset.primaryName || data.name || 'primary group';
                renderResult(out, { ok: true, summary: 'Primary group synced: ' + label, ...data });
            } catch (e) {
                renderResult(out, { ok: false, summary: e.message });
            } finally {
                if (syncBtn) syncBtn.disabled = false;
                syncPrimaryBtn.disabled = false;
            }
        });
    }

    async function fetchQr() {
        if (!qrRefreshBtn) return;
        return postForm(qrRefreshBtn.dataset.url, {});
    }

    function setQrStatus(text, isError) {
        if (!qrStatus) return;
        qrStatus.textContent = text;
        qrStatus.className = 'small mb-2 ' + (isError ? 'text-danger' : 'text-muted');
    }

    async function refreshQr(showErrors) {
        try {
            setQrStatus('Loading QR…', false);
            const data = await fetchQr();
            if (data.connected) {
                setQrStatus('WhatsApp is connected. You can sync groups now.', false);
                stopQrPoll();
                if (qrImage) qrImage.classList.add('d-none');
                if (qrPlaceholder) { qrPlaceholder.classList.remove('d-none'); qrPlaceholder.textContent = 'Connected'; }
                return;
            }
            if (data.qr_image && qrImage) {
                qrImage.src = data.qr_image;
                qrImage.classList.remove('d-none');
                if (qrPlaceholder) qrPlaceholder.classList.add('d-none');
                setQrStatus('Scan with WhatsApp linked devices. QR auto-refreshes every 8 seconds.', false);
            } else {
                setQrStatus(data.message || 'Waiting for QR from worker…', false);
            }
        } catch (e) {
            if (showErrors) setQrStatus(e.message, true);
        }
    }

    function startQrPoll() {
        stopQrPoll();
        refreshQr(true);
        qrPollTimer = setInterval(function () { refreshQr(false); }, 8000);
    }

    async function startQrSession() {
        if (!qrRefreshBtn) return;
        const startUrl = qrRefreshBtn.dataset.startUrl;
        if (startUrl) {
            setQrStatus('Preparing new QR session…', false);
            const body = new URLSearchParams();
            body.set('_token', csrfToken());
            const res = await fetch(startUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.message || 'Could not start QR pairing');
            const data = json.data ?? json;
            if (data.qr_image && qrImage) {
                qrImage.src = data.qr_image;
                qrImage.classList.remove('d-none');
                if (qrPlaceholder) qrPlaceholder.classList.add('d-none');
                setQrStatus('Scan with WhatsApp linked devices.', false);
            }
        }
        startQrPoll();
    }

    function stopQrPoll() {
        if (qrPollTimer) {
            clearInterval(qrPollTimer);
            qrPollTimer = null;
        }
    }

    if (qrRefreshBtn) {
        qrRefreshBtn.addEventListener('click', function () { startQrSession().catch(function (e) { setQrStatus(e.message, true); }); });
    }
    if (qrStopBtn) {
        qrStopBtn.addEventListener('click', function () { stopQrPoll(); setQrStatus('Auto-refresh stopped.', false); });
    }
    if (qrTab) {
        qrTab.addEventListener('shown.bs.tab', function () {
            if (!qrImage || qrImage.classList.contains('d-none')) {
                startQrSession().catch(function (e) { setQrStatus(e.message, true); });
            } else {
                startQrPoll();
            }
        });
    }

    if (pairBtn && pairOut) {
        pairBtn.addEventListener('click', async function () {
            pairBtn.disabled = true;
            pairOut.classList.remove('d-none');
            pairOut.innerHTML = '<div class="text-muted small">Requesting pairing code…</div>';
            try {
                const phone = document.getElementById('whatsapp_pair_phone')?.value || '';
                const data = await postForm(pairBtn.dataset.url, { phone: phone });
                renderResult(pairOut, { ok: true, summary: 'Enter this code in WhatsApp linked devices.', ...data });
            } catch (e) {
                renderResult(pairOut, { ok: false, summary: e.message });
            } finally {
                pairBtn.disabled = false;
            }
        });
    }
})();
</script>
@endpush
