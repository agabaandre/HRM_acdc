<?php

namespace App\Support;

class ConfigValue
{
    public static function firstNonEmpty(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
