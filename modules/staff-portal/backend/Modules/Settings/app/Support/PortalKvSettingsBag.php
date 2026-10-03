<?php

namespace Modules\Settings\Support;

use Modules\Settings\Models\PortalKvSetting;
use Staff\Shared\ModuleSettingsBag;

final class PortalKvSettingsBag implements ModuleSettingsBag
{
    public function __construct(private readonly string $group = 'staff_api') {}

    public function get(string $key): ?string
    {
        $row = PortalKvSetting::query()->where('setting_key', $key)->first();
        if (! $row || $row->setting_value === null) {
            return null;
        }

        return (string) $row->setting_value;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            PortalKvSetting::query()->where('setting_key', $key)->delete();

            return;
        }
        PortalKvSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'group' => $this->group],
        );
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && trim($v) !== '';
    }
}
