<?php

namespace Modules\Settings\Support;

class PortalLocalesConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $cfg = config('settings.supported_locales');
        if (! is_array($cfg) || $cfg === []) {
            $path = module_path('Settings', 'config/supported_locales.php');
            $cfg = is_file($path) ? require $path : [];
        }
        if (! is_array($cfg)) {
            $cfg = [];
        }

        $extraPath = module_path('Settings', 'config/supported_locales_menus.php');
        if (is_file($extraPath)) {
            $extra = require $extraPath;
            if (is_array($extra)) {
                $cfg = array_replace_recursive($cfg, $extra);
            }
        }

        return $cfg;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if ($key === '') {
            return $all;
        }

        $cursor = $all;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
