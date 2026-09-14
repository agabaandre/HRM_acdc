<?php

namespace Modules\Settings\Support;

class PortalAiProvidersConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $cfg = config('settings.ai_providers');
        if (! is_array($cfg) || $cfg === []) {
            $path = module_path('Settings', 'config/ai_providers.php');
            $cfg = is_file($path) ? require $path : [];
        }

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function drivers(): array
    {
        $drivers = self::all()['drivers'] ?? [];

        return is_array($drivers) ? array_values($drivers) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function driver(string $key): ?array
    {
        foreach (self::drivers() as $driver) {
            if (($driver['key'] ?? '') === $key) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function driverKeys(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $driver): string => (string) ($driver['key'] ?? ''),
            self::drivers(),
        )));
    }
}
