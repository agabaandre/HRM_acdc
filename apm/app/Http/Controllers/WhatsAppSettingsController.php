<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\WhatsApp\WhatsAppBotClient;
use App\Services\WhatsApp\WhatsAppConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppBotClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to WhatsApp settings.');
        }

        $passwordConfigured = $this->config->adminPassword() !== '';

        return [
            'settings' => [
                'whatsapp_bot_enabled' => $this->config->isEnabled(),
                'whatsapp_bot_api_url' => $this->config->apiUrl(),
                'whatsapp_bot_number' => $this->config->botNumber(),
                'whatsapp_primary_group_jid' => $this->config->primaryGroupJid(),
                'whatsapp_primary_group_name' => $this->config->primaryGroupName(),
                'whatsapp_bot_admin_password_configured' => $passwordConfigured,
            ],
            'bot_repo_url' => 'https://github.com/jacktheboss220/WhatsAppBotMultiDevice',
            'groups_url' => route('whatsapp-groups.index'),
            'test_url' => route('whatsapp-settings.test'),
            'update_url' => route('whatsapp-settings.update'),
        ];
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to WhatsApp settings.');
        }

        $validated = $request->validate([
            'whatsapp_bot_enabled' => ['nullable', 'boolean'],
            'whatsapp_bot_api_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_bot_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_bot_admin_password' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('whatsapp_bot_enabled', $validated)) {
            SystemSetting::set(
                'whatsapp_bot_enabled',
                $request->boolean('whatsapp_bot_enabled') ? '1' : '0',
                'whatsapp',
                'boolean'
            );
        }

        if (array_key_exists('whatsapp_bot_api_url', $validated) && $validated['whatsapp_bot_api_url'] !== null) {
            SystemSetting::set('whatsapp_bot_api_url', rtrim($validated['whatsapp_bot_api_url'], '/'), 'whatsapp', 'text');
        }

        if (array_key_exists('whatsapp_bot_number', $validated)) {
            $digits = preg_replace('/\D+/', '', (string) ($validated['whatsapp_bot_number'] ?? ''));
            SystemSetting::set('whatsapp_bot_number', $digits, 'whatsapp', 'text');
        }

        if (! empty($validated['whatsapp_bot_admin_password'])) {
            SystemSetting::set('whatsapp_bot_admin_password', $validated['whatsapp_bot_admin_password'], 'whatsapp', 'password');
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

    public function testConnection(): JsonResponse
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to WhatsApp settings.');
        }

        $public = $this->client->publicStatus();
        $admin = null;
        $adminError = null;

        if ($this->config->isConfigured()) {
            try {
                $admin = $this->client->adminStats();
            } catch (RuntimeException $e) {
                $adminError = $e->getMessage();
            }
        } else {
            $adminError = 'Set API URL and admin password first.';
        }

        return response()->json([
            'data' => [
                'public_status' => $public,
                'admin_stats' => $admin,
                'admin_error' => $adminError,
            ],
        ]);
    }
}
