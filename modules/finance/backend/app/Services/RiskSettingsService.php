<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class RiskSettingsService
{
    public function get(string $key, ?string $default = null): ?string
    {
        if (! DB::getSchemaBuilder()->hasTable('rr_settings')) {
            return $default;
        }
        $value = DB::table('rr_settings')->where('key', $key)->value('value');

        return $value === null ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $existing = DB::table('rr_settings')->where('key', $key)->first();
        if ($existing) {
            DB::table('rr_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('rr_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function importEnabled(): bool
    {
        return $this->get('import_enabled', '1') === '1';
    }

    public function setImportEnabled(bool $enabled): void
    {
        $this->set('import_enabled', $enabled ? '1' : '0');
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('rr_settings')) {
            return ['import_enabled' => '1'];
        }

        return DB::table('rr_settings')->pluck('value', 'key')->map(fn ($v) => $v === null ? null : (string) $v)->all();
    }
}
