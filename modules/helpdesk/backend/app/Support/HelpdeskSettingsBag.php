<?php

namespace App\Support;

use App\Models\HelpdeskSetting;
use Staff\Shared\ModuleSettingsBag;

final class HelpdeskSettingsBag implements ModuleSettingsBag
{
    public function get(string $key): ?string
    {
        return HelpdeskSetting::getValue($key, null);
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            HelpdeskSetting::query()->where('key', $key)->delete();

            return;
        }
        HelpdeskSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && trim($v) !== '';
    }
}
