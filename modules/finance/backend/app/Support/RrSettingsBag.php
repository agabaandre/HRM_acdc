<?php

namespace App\Support;

use App\Services\RiskSettingsService;
use Staff\Shared\ModuleSettingsBag;

final class RrSettingsBag implements ModuleSettingsBag
{
    public function __construct(private readonly RiskSettingsService $settings = new RiskSettingsService) {}

    public function get(string $key): ?string
    {
        $v = $this->settings->get($key);

        return $v === null || $v === '' ? null : (string) $v;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            if (\Illuminate\Support\Facades\Schema::hasTable('rr_settings')) {
                \Illuminate\Support\Facades\DB::table('rr_settings')->where('key', $key)->delete();
            }

            return;
        }
        $this->settings->set($key, $value);
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && trim($v) !== '';
    }
}
