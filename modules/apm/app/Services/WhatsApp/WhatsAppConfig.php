<?php

namespace App\Services\WhatsApp;

use App\Models\SystemSetting;

class WhatsAppConfig
{
    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('whatsapp_bot_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function driver(): string
    {
        $driver = trim((string) SystemSetting::get('whatsapp_driver', 'native'));

        return in_array($driver, ['native', 'external'], true) ? $driver : 'native';
    }

    public function usesNativeDriver(): bool
    {
        return $this->driver() === 'native';
    }

    public function workerUrl(): string
    {
        $url = trim((string) SystemSetting::get('whatsapp_worker_url', 'http://127.0.0.1:8765'));
        $url = rtrim($url !== '' ? $url : 'http://127.0.0.1:8765', '/');

        try {
            return WhatsAppUrlGuard::assertSafe($url, true);
        } catch (\Throwable) {
            return 'http://127.0.0.1:8765';
        }
    }

    public function workerToken(): string
    {
        return WhatsAppSecretStore::get('whatsapp_worker_token');
    }

    public function apiUrl(): string
    {
        if ($this->usesNativeDriver()) {
            return $this->workerUrl();
        }

        $url = trim((string) SystemSetting::get('whatsapp_bot_api_url', 'http://127.0.0.1:8000'));
        $url = rtrim($url !== '' ? $url : 'http://127.0.0.1:8000', '/');

        try {
            return WhatsAppUrlGuard::assertSafe($url, false);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Bot / owner number — digits only, with country code. */
    public function botNumber(): string
    {
        return $this->normalizePhone((string) SystemSetting::get('whatsapp_bot_number', ''));
    }

    public function adminPassword(): string
    {
        return WhatsAppSecretStore::get('whatsapp_bot_admin_password');
    }

    public function primaryGroupJid(): string
    {
        return trim((string) SystemSetting::get('whatsapp_primary_group_jid', ''));
    }

    public function primaryGroupName(): string
    {
        return trim((string) SystemSetting::get('whatsapp_primary_group_name', ''));
    }

    public function groupSyncKeyword(): string
    {
        return trim((string) SystemSetting::get('whatsapp_group_sync_keyword', 'Africa CDC'));
    }

    public function moduleGrantAll(): bool
    {
        return filter_var(
            SystemSetting::get('whatsapp_module_grant_all', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @return list<int>
     */
    public function modulePermissionIds(): array
    {
        return self::parsePermissionIds(
            (string) SystemSetting::get('whatsapp_module_permission_ids', '')
        );
    }

    public function configAdminOnly(): bool
    {
        return filter_var(
            SystemSetting::get('whatsapp_config_admin_only', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @return list<int>
     */
    public function configPermissionIds(): array
    {
        return self::parsePermissionIds(
            (string) SystemSetting::get('whatsapp_config_permission_ids', '')
        );
    }

    /**
     * @return list<int>
     */
    public static function parsePermissionIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '[]') {
            return [];
        }

        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter(array_map('intval', $decoded))));
            }
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $parts))));
    }

    public function isConfigured(): bool
    {
        if ($this->usesNativeDriver()) {
            return $this->workerUrl() !== '' && $this->workerToken() !== '';
        }

        return $this->apiUrl() !== ''
            && $this->adminPassword() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSummary(bool $includeInternalUrls = false): array
    {
        $summary = [
            'enabled' => $this->isEnabled(),
            'driver' => $this->driver(),
            'bot_number' => $this->botNumber(),
            'admin_password_configured' => $this->adminPassword() !== '',
            'worker_token_configured' => $this->workerToken() !== '',
            'primary_group_jid' => $this->primaryGroupJid(),
            'primary_group_name' => $this->primaryGroupName(),
            'group_sync_keyword' => $this->groupSyncKeyword(),
            'uses_native' => $this->usesNativeDriver(),
        ];

        if ($includeInternalUrls) {
            $summary['api_url'] = $this->apiUrl();
            $summary['worker_url'] = $this->workerUrl();
        }

        return $summary;
    }

    public function normalizePhone(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    public function phoneTail(string $digits): string
    {
        $digits = $this->normalizePhone($digits);
        if ($digits === '') {
            return '';
        }

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }
}
