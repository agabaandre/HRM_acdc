<?php

namespace App\Services\WhatsApp;

use App\Models\SystemSetting;

class WhatsAppConfig
{
    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('whatsapp_bot_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function apiUrl(): string
    {
        $url = trim((string) SystemSetting::get('whatsapp_bot_api_url', 'http://127.0.0.1:8000'));

        return rtrim($url !== '' ? $url : 'http://127.0.0.1:8000', '/');
    }

    /** Bot / owner number — digits only, with country code. */
    public function botNumber(): string
    {
        return $this->normalizePhone((string) SystemSetting::get('whatsapp_bot_number', ''));
    }

    public function adminPassword(): string
    {
        return (string) (SystemSetting::get('whatsapp_bot_admin_password', '') ?? '');
    }

    public function primaryGroupJid(): string
    {
        return trim((string) SystemSetting::get('whatsapp_primary_group_jid', ''));
    }

    public function primaryGroupName(): string
    {
        return trim((string) SystemSetting::get('whatsapp_primary_group_name', ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl() !== ''
            && $this->adminPassword() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSummary(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'api_url' => $this->apiUrl(),
            'bot_number' => $this->botNumber(),
            'admin_password_configured' => $this->adminPassword() !== '',
            'primary_group_jid' => $this->primaryGroupJid(),
            'primary_group_name' => $this->primaryGroupName(),
            'bot_repo_url' => 'https://github.com/jacktheboss220/WhatsAppBotMultiDevice',
        ];
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
