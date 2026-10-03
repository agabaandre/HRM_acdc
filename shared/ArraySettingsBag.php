<?php

namespace Staff\Shared;

/**
 * In-memory bag (tests / one-shot probes).
 */
final class ArraySettingsBag implements ModuleSettingsBag
{
    /** @param  array<string, string|null>  $data */
    public function __construct(private array $data = []) {}

    public function get(string $key): ?string
    {
        if (! array_key_exists($key, $this->data)) {
            return null;
        }
        $v = $this->data[$key];

        return $v === null ? null : (string) $v;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            unset($this->data[$key]);

            return;
        }
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && trim($v) !== '';
    }
}
