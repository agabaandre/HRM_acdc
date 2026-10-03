<?php

namespace Staff\Shared;

/**
 * Key/value settings store used for DB overrides (DB beats env).
 */
interface ModuleSettingsBag
{
    public function get(string $key): ?string;

    public function set(string $key, ?string $value): void;

    /** True when a non-empty value is stored for the key. */
    public function has(string $key): bool;
}
