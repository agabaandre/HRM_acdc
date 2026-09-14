<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\WhatsAppGroup;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppBootstrapService;
use App\Services\WhatsApp\WhatsAppConfig;
use App\Services\WhatsApp\WhatsAppSecretStore;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\WhatsAppUrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppBootstrapService $bootstrap,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        $passwordConfigured = $this->config->usesNativeDriver()
            ? $this->config->workerToken() !== ''
            : $this->config->adminPassword() !== '';

        return [
            'settings' => [
                'whatsapp_bot_enabled' => $this->config->isEnabled(),
                'whatsapp_driver' => $this->config->driver(),
                'whatsapp_bot_api_url' => SystemSetting::get('whatsapp_bot_api_url', ''),
                'whatsapp_worker_url' => SystemSetting::get('whatsapp_worker_url', 'http://127.0.0.1:8765'),
                'whatsapp_bot_number' => $this->config->botNumber(),
                'whatsapp_primary_group_jid' => $this->config->primaryGroupJid(),
                'whatsapp_primary_group_name' => $this->config->primaryGroupName(),
                'whatsapp_bot_admin_password_configured' => $passwordConfigured,
                'whatsapp_worker_token_configured' => $this->config->workerToken() !== '',
                'whatsapp_module_grant_all' => $this->config->moduleGrantAll(),
                'whatsapp_module_permission_ids' => implode(', ', $this->config->modulePermissionIds()),
                'whatsapp_config_admin_only' => $this->config->configAdminOnly(),
                'whatsapp_config_permission_ids' => implode(', ', $this->config->configPermissionIds()),
                'whatsapp_group_sync_keyword' => $this->config->groupSyncKeyword(),
            ],
            'groups_url' => route('whatsapp-groups.index'),
            'test_url' => route('whatsapp-settings.test'),
            'pair_url' => route('whatsapp-settings.pair'),
            'qr_url' => route('whatsapp-settings.qr'),
            'qr_start_url' => route('whatsapp-settings.qr-start'),
            'sync_url' => route('whatsapp-settings.sync'),
            'sync_primary_url' => route('whatsapp-settings.sync-primary'),
            'bootstrap_url' => route('whatsapp-settings.bootstrap'),
            'update_url' => route('whatsapp-settings.update'),
            'worker_env_exists' => is_file($this->bootstrap->workerEnvPath()),
        ];
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_bot_enabled' => ['nullable', 'boolean'],
            'whatsapp_driver' => ['nullable', 'in:native,external'],
            'whatsapp_bot_api_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_worker_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_bot_number' => ['nullable', 'string', 'regex:/^\d{0,15}$/'],
            'whatsapp_bot_admin_password' => ['nullable', 'string', 'max:255'],
            'whatsapp_worker_token' => ['nullable', 'string', 'min:32', 'max:255'],
            'whatsapp_module_grant_all' => ['nullable', 'boolean'],
            'whatsapp_module_permission_ids' => ['nullable', 'string', 'max:500'],
            'whatsapp_config_admin_only' => ['nullable', 'boolean'],
            'whatsapp_config_permission_ids' => ['nullable', 'string', 'max:500'],
            'whatsapp_group_sync_keyword' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $driver = $validated['whatsapp_driver']
                ?? $this->config->driver();

            if (array_key_exists('whatsapp_bot_enabled', $validated)) {
                SystemSetting::set(
                    'whatsapp_bot_enabled',
                    $request->boolean('whatsapp_bot_enabled') ? '1' : '0',
                    'whatsapp',
                    'boolean'
                );
            }

            if (array_key_exists('whatsapp_driver', $validated) && $validated['whatsapp_driver'] !== null) {
                SystemSetting::set('whatsapp_driver', $validated['whatsapp_driver'], 'whatsapp', 'text');
            }

            // External API URL is only validated/saved when using the external driver.
            // Native mode forms still post the hidden field (often 127.0.0.1), which must not fail save.
            if (
                $driver === 'external'
                && array_key_exists('whatsapp_bot_api_url', $validated)
                && $validated['whatsapp_bot_api_url'] !== null
                && trim((string) $validated['whatsapp_bot_api_url']) !== ''
            ) {
                $safeUrl = WhatsAppUrlGuard::assertSafe($validated['whatsapp_bot_api_url'], false);
                SystemSetting::set('whatsapp_bot_api_url', $safeUrl, 'whatsapp', 'text');
            }

            if (
                $driver === 'native'
                && array_key_exists('whatsapp_worker_url', $validated)
                && $validated['whatsapp_worker_url'] !== null
                && trim((string) $validated['whatsapp_worker_url']) !== ''
            ) {
                $safeUrl = WhatsAppUrlGuard::assertSafe($validated['whatsapp_worker_url'], true);
                SystemSetting::set('whatsapp_worker_url', $safeUrl, 'whatsapp', 'text');
                // Keep legacy api_url aligned with the worker for native mode.
                SystemSetting::set('whatsapp_bot_api_url', $safeUrl, 'whatsapp', 'text');
            }

            if (array_key_exists('whatsapp_bot_number', $validated)) {
                SystemSetting::set('whatsapp_bot_number', $validated['whatsapp_bot_number'] ?? '', 'whatsapp', 'text');
            }

            if (! empty($validated['whatsapp_bot_admin_password'])) {
                WhatsAppSecretStore::set('whatsapp_bot_admin_password', $validated['whatsapp_bot_admin_password']);
            }

            if (! empty($validated['whatsapp_worker_token'])) {
                WhatsAppSecretStore::set('whatsapp_worker_token', $validated['whatsapp_worker_token']);
            }

            if (array_key_exists('whatsapp_module_grant_all', $validated)) {
                SystemSetting::set(
                    'whatsapp_module_grant_all',
                    $request->boolean('whatsapp_module_grant_all') ? '1' : '0',
                    'whatsapp',
                    'boolean'
                );
            }

            if (array_key_exists('whatsapp_module_permission_ids', $validated)) {
                $ids = WhatsAppConfig::parsePermissionIds($validated['whatsapp_module_permission_ids'] ?? '');
                SystemSetting::set(
                    'whatsapp_module_permission_ids',
                    $ids === [] ? '' : implode(',', $ids),
                    'whatsapp',
                    'text'
                );
            }

            if (array_key_exists('whatsapp_config_admin_only', $validated)) {
                SystemSetting::set(
                    'whatsapp_config_admin_only',
                    $request->boolean('whatsapp_config_admin_only') ? '1' : '0',
                    'whatsapp',
                    'boolean'
                );
            }

            if (array_key_exists('whatsapp_config_permission_ids', $validated)) {
                $ids = WhatsAppConfig::parsePermissionIds($validated['whatsapp_config_permission_ids'] ?? '');
                SystemSetting::set(
                    'whatsapp_config_permission_ids',
                    $ids === [] ? '' : implode(',', $ids),
                    'whatsapp',
                    'text'
                );
            }

            if (array_key_exists('whatsapp_group_sync_keyword', $validated)) {
                SystemSetting::set(
                    'whatsapp_group_sync_keyword',
                    trim((string) ($validated['whatsapp_group_sync_keyword'] ?? '')),
                    'whatsapp',
                    'text'
                );
            }

            if ($this->config->usesNativeDriver()) {
                $this->bootstrap->writeWorkerEnv();
            }

            WhatsAppAuditLogger::log('settings.updated', [
                'driver' => $this->config->driver(),
                'enabled' => $this->config->isEnabled(),
            ]);
        } catch (Throwable $e) {
            Log::warning('WhatsApp settings update failed', ['error' => $e->getMessage()]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $this->safeError($e, 'Could not save WhatsApp settings.')], 422);
            }

            return redirect()->route('system-configs.index', ['tab' => 'whatsapp'])
                ->with('msg', $this->safeError($e, 'Could not save WhatsApp settings.'))
                ->with('type', 'error');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp settings saved.',
                'settings' => $this->getIndexData()['settings'],
            ]);
        }

        return redirect()->route('system-configs.index', ['tab' => 'whatsapp'])
            ->with('msg', 'WhatsApp settings saved.')
            ->with('type', 'success');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $usesNative = $this->config->usesNativeDriver();
        $passwordConfigured = $usesNative
            ? $this->config->workerToken() !== ''
            : $this->config->adminPassword() !== '';

        $public = $this->whatsapp->publicStatus();
        $admin = null;
        $adminError = null;

        if (! $passwordConfigured && ! $usesNative) {
            $adminError = 'External admin password is not configured.';
        } elseif (! ($public['reachable'] ?? false)) {
            $adminError = $usesNative
                ? 'WhatsApp worker is not reachable.'
                : 'External bot API is not reachable.';
        } else {
            try {
                $admin = $usesNative
                    ? $this->whatsapp->adminStats()
                    : $this->whatsapp->adminStats(null, $this->config->adminPassword());
            } catch (RuntimeException $e) {
                Log::info('WhatsApp test connection admin check failed', ['error' => $e->getMessage()]);
                $adminError = 'Admin authentication check failed.';
            }
        }

        $ok = ($public['reachable'] ?? false)
            && ($public['connected'] ?? false)
            && ($public['registered'] ?? false)
            && $admin !== null;

        $hints = [];
        if (! ($public['reachable'] ?? false)) {
            $hints[] = $usesNative
                ? 'Start the native worker: cd apm/whatsapp-service && npm start'
                : 'Verify the external bot URL is reachable from this server.';
        } elseif (! ($public['connected'] ?? false) || ! ($public['registered'] ?? false)) {
            $hints[] = 'Complete WhatsApp pairing (QR or code), then sync groups.';
        }

        WhatsAppAuditLogger::log('connection.tested', ['ok' => $ok, 'driver' => $this->config->driver()]);

        return response()->json([
            'data' => [
                'ok' => $ok,
                'driver' => $this->config->driver(),
                'admin_password_configured' => $passwordConfigured,
                'summary' => $ok
                    ? 'WhatsApp platform is reachable, connected, and ready.'
                    : ($hints[0] ?? 'Connection test did not pass.'),
                'hints' => $hints,
                'public_status' => $public,
                'admin_stats' => $admin,
                'admin_error' => $adminError,
            ],
        ]);
    }

    public function requestPairing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\d{7,15}$/'],
        ]);

        try {
            $result = $this->whatsapp->requestPairingCode($validated['phone']);
            WhatsAppAuditLogger::log('pairing.code_requested');

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp pairing code request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $this->safeError($e, 'Pairing request failed.')], 502);
        }
    }

    public function startQrPairing(): JsonResponse
    {
        try {
            $result = $this->whatsapp->startQrPairing();
            WhatsAppAuditLogger::log('pairing.qr_started');

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp QR pairing start failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $this->safeError($e, 'Could not start QR pairing.')], 502);
        }
    }

    public function qrCode(): JsonResponse
    {
        try {
            $result = $this->whatsapp->qrCode();

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp QR poll failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $this->safeError($e, 'Could not load QR code.')], 502);
        }
    }

    public function bootstrapPlatform(): JsonResponse
    {
        try {
            $result = $this->bootstrap->bootstrap(false);
            WhatsAppAuditLogger::log('platform.bootstrapped');

            return response()->json([
                'data' => array_merge($result, [
                    'message' => 'Worker credentials are ready. Start the worker with: cd apm/whatsapp-service && npm start',
                ]),
            ]);
        } catch (Throwable $e) {
            Log::error('WhatsApp bootstrap failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $this->safeError($e, 'Bootstrap failed.')], 500);
        }
    }

    public function syncGroups(): JsonResponse
    {
        try {
            $result = $this->whatsapp->syncGroups();
            WhatsAppAuditLogger::log('groups.synced', [
                'scope' => 'all',
                'count' => $result['synced'] ?? null,
                'pruned' => $result['pruned'] ?? null,
            ]);

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp group sync failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $this->safeError($e, 'Group sync failed.')], 502);
        }
    }

    public function syncPrimaryGroup(): JsonResponse
    {
        try {
            $result = $this->whatsapp->syncPrimaryGroup();
            WhatsAppAuditLogger::log('groups.synced', [
                'scope' => 'primary',
                'jid' => $result['primary_jid'] ?? null,
            ]);

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp primary group sync failed', ['error' => $e->getMessage()]);

            $status = str_contains($e->getMessage(), 'not configured') ? 422 : 502;

            return response()->json(['message' => $this->safeError($e, 'Primary group sync failed.')], $status);
        }
    }

    private function safeError(Throwable $e, string $fallback): string
    {
        if ($e instanceof RuntimeException) {
            $message = $e->getMessage();
            if (str_contains($message, 'URL') || str_contains($message, 'localhost') || str_contains($message, 'password')) {
                return $message;
            }
        }

        return $fallback;
    }
}
