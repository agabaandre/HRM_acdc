<?php

namespace App\Services\WhatsApp;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class WhatsAppSecretStore
{
    public static function get(string $key, string $default = ''): string
    {
        $raw = SystemSetting::get($key, $default);
        if ($raw === null || $raw === '') {
            return $default;
        }

        try {
            return Crypt::decryptString((string) $raw);
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    public static function set(string $key, string $value, string $group = 'whatsapp'): void
    {
        if ($value === '') {
            return;
        }

        SystemSetting::set($key, Crypt::encryptString($value), $group, 'password');
    }

    public static function isConfigured(string $key): bool
    {
        return self::get($key) !== '';
    }
}
