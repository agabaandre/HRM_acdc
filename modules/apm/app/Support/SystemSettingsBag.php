<?php

namespace App\Support;

use App\Models\SystemSetting;
use Staff\Shared\ModuleSettingsBag;

final class SystemSettingsBag implements ModuleSettingsBag
{
    public function __construct(private readonly string $group = 'staff_api') {}

    public function get(string $key): ?string
    {
        $v = SystemSetting::get($key, null);

        return $v === null ? null : (string) $v;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            SystemSetting::query()->where('key', $key)->delete();
            \Illuminate\Support\Facades\Cache::forget('system_setting:'.$key);

            return;
        }
        $type = str_contains($key, 'password') || str_contains($key, 'token') || str_contains($key, 'secret')
            ? 'password'
            : 'text';
        SystemSetting::set($key, $value, $this->group, $type);
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && trim($v) !== '';
    }
}
