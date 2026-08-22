<?php

namespace Modules\Workplan\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Modules\Workplan\Models\WorkplanPraSetting;

class PraWorkplanSettingsService
{
    /** @var array<string, mixed>|null */
    private ?array $resolvedCache = null;

    /**
     * Merged PRA config: Settings DB overrides `.env` / config('workplan.pra').
     *
     * @return array{
     *     base_url: string,
     *     api_key: string,
     *     tiers: string,
     *     fiscal_year: ?int,
     *     divisions: list<string>,
     *     division_aliases: array<string, string>,
     *     timeout: int
     * }
     */
    public function resolved(): array
    {
        if ($this->resolvedCache !== null) {
            return $this->resolvedCache;
        }

        $env = $this->envDefaults();
        $stored = $this->stored();

        $baseUrl = rtrim((string) ($stored['base_url'] ?? $env['base_url']), '/');
        $apiKey = (string) ($stored['api_key'] ?? $env['api_key']);
        $tiers = trim((string) ($stored['tiers'] ?? $env['tiers']));
        if ($tiers === '') {
            $tiers = '3,4';
        }

        $fiscal = $stored['fiscal_year'] ?? null;
        $fiscalYear = ($fiscal === null || $fiscal === '') ? null : (int) $fiscal;

        $divisionsRaw = $stored['divisions'] ?? $env['divisions_raw'];
        $aliasesRaw = $stored['division_aliases'] ?? $env['division_aliases_raw'];
        $timeout = (int) ($stored['timeout'] ?? $env['timeout']);

        return $this->resolvedCache = [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'tiers' => $tiers,
            'fiscal_year' => $fiscalYear,
            'divisions' => $this->parseDivisions((string) $divisionsRaw),
            'division_aliases' => $this->parseAliases((string) $aliasesRaw),
            'timeout' => max(10, min(300, $timeout > 0 ? $timeout : 60)),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->resolved()['api_key'] !== '' && $this->resolved()['base_url'] !== '';
    }

    /**
     * Safe payload for the Settings form (never includes the raw API key).
     *
     * @return array<string, mixed>
     */
    public function formPayload(): array
    {
        $env = $this->envDefaults();
        $stored = $this->stored();
        $resolved = $this->resolved();

        $divisions = (string) ($stored['divisions'] ?? $env['divisions_raw']);
        $aliases = (string) ($stored['division_aliases'] ?? $env['division_aliases_raw']);

        return [
            'base_url' => $resolved['base_url'],
            'api_key_set' => $resolved['api_key'] !== '',
            'tiers' => $resolved['tiers'],
            'fiscal_year' => $resolved['fiscal_year'],
            'divisions' => $divisions,
            'division_aliases' => $aliases,
            'timeout' => $resolved['timeout'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        if (! Schema::hasTable('workplan_pra_settings')) {
            throw new \RuntimeException('workplan_pra_settings table is missing. Run migrations.');
        }

        if (array_key_exists('base_url', $values)) {
            $this->put('base_url', rtrim(trim((string) $values['base_url']), '/'));
        }
        if (array_key_exists('tiers', $values)) {
            $tiers = trim((string) $values['tiers']);
            $this->put('tiers', $tiers !== '' ? $tiers : '3,4');
        }
        if (array_key_exists('fiscal_year', $values)) {
            $fiscal = $values['fiscal_year'];
            $this->put('fiscal_year', $fiscal === null || $fiscal === '' ? null : (int) $fiscal);
        }
        if (array_key_exists('divisions', $values)) {
            $this->put('divisions', trim((string) $values['divisions']));
        }
        if (array_key_exists('division_aliases', $values)) {
            $this->put('division_aliases', trim((string) $values['division_aliases']));
        }
        if (array_key_exists('timeout', $values)) {
            $timeout = (int) $values['timeout'];
            $this->put('timeout', max(10, min(300, $timeout > 0 ? $timeout : 60)));
        }
        if (array_key_exists('api_key', $values)) {
            $key = trim((string) $values['api_key']);
            if ($key !== '') {
                $this->put('api_key', Crypt::encryptString($key), encrypted: true);
            }
        }

        $this->resolvedCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function stored(): array
    {
        if (! Schema::hasTable('workplan_pra_settings')) {
            return [];
        }

        $out = [];
        foreach (WorkplanPraSetting::query()->get() as $row) {
            $payload = is_array($row->setting_value) ? $row->setting_value : [];
            $key = (string) $row->setting_key;
            if ($key === 'api_key') {
                $out['api_key'] = $this->decodeApiKey($payload);

                continue;
            }
            if (array_key_exists('value', $payload)) {
                $out[$key] = $payload['value'];
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     base_url: string,
     *     api_key: string,
     *     tiers: string,
     *     fiscal_year: mixed,
     *     divisions_raw: string,
     *     division_aliases_raw: string,
     *     timeout: int
     * }
     */
    protected function envDefaults(): array
    {
        $pra = (array) config('workplan.pra', []);
        $aliases = is_array($pra['division_aliases'] ?? null) ? $pra['division_aliases'] : [];
        $divisions = is_array($pra['divisions'] ?? null) ? $pra['divisions'] : [];

        return [
            'base_url' => rtrim((string) ($pra['base_url'] ?? ''), '/'),
            'api_key' => (string) ($pra['api_key'] ?? ''),
            'tiers' => (string) ($pra['tiers'] ?? '3,4'),
            'fiscal_year' => null,
            'divisions_raw' => implode(',', array_map('strval', $divisions)),
            'division_aliases_raw' => $this->stringifyAliases($aliases),
            'timeout' => (int) ($pra['timeout'] ?? 60),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function decodeApiKey(array $payload): string
    {
        $encrypted = (string) ($payload['encrypted'] ?? '');
        if ($encrypted !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                return '';
            }
        }

        return (string) ($payload['value'] ?? '');
    }

    protected function put(string $key, mixed $value, bool $encrypted = false): void
    {
        WorkplanPraSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $encrypted ? ['encrypted' => $value] : ['value' => $value]],
        );
    }

    /**
     * @return list<string>
     */
    public function parseDivisions(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || in_array(strtolower($raw), ['*', 'all'], true)) {
            return [];
        }

        $codes = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return array<string, string>
     */
    public function parseAliases(string $raw): array
    {
        $aliases = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $pair) {
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }
            [$from, $to] = array_map('trim', explode(':', $pair, 2));
            if ($from !== '' && $to !== '') {
                $aliases[strtoupper($from)] = strtoupper($to);
            }
        }

        return $aliases;
    }

    /**
     * @param  array<string, string>  $aliases
     */
    public function stringifyAliases(array $aliases): string
    {
        $parts = [];
        foreach ($aliases as $from => $to) {
            $from = strtoupper(trim((string) $from));
            $to = strtoupper(trim((string) $to));
            if ($from !== '' && $to !== '') {
                $parts[] = $from.':'.$to;
            }
        }

        return implode(',', $parts);
    }
}
