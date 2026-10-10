<?php

namespace App\Services\Pra;

use App\Models\SystemSetting;
use App\Services\WhatsApp\WhatsAppSecretStore;

class PraSettingsService
{
    public const GROUP = 'pra';

    public const DEFAULT_ALIASES = 'MIS:DHIS,CT:RD&CT,DIGITAL:DHIS,CARCC:CRCC,EARCC:ERCC,NARCC:NRCC,SARCC:SRCC,WARCC:WRCC,DIO:OIO,NPHI:PHIR,HEP:HEF,PHC:CPH,CHSISD:CHSHP,CLDS:LAB,HR:HRM,LEGAL:LADS,YOUTH:YD,ADMIN:DA,CPI:DCPI,FMG:DFIN';

    /**
     * @return array{
     *     base_url: string,
     *     api_key: string,
     *     tiers: string,
     *     fiscal_year: ?int,
     *     division_aliases: array<string, string>,
     *     timeout: int
     * }
     */
    public function resolved(): array
    {
        $cfg = (array) config('services.pra', []);
        $baseUrl = rtrim((string) (SystemSetting::get('pra_api_url') ?: ($cfg['base_url'] ?? '')), '/');
        $apiKey = WhatsAppSecretStore::get('pra_api_key', (string) ($cfg['api_key'] ?? ''));
        $tiers = trim((string) (SystemSetting::get('pra_tiers') ?: ($cfg['tiers'] ?? '3,4')));
        if ($tiers === '') {
            $tiers = '3,4';
        }

        $fiscalRaw = SystemSetting::get('pra_fiscal_year', $cfg['fiscal_year'] ?? null);
        $fiscalYear = ($fiscalRaw === null || $fiscalRaw === '') ? null : (int) $fiscalRaw;

        $aliasesRaw = (string) (SystemSetting::get('pra_division_aliases')
            ?: ($cfg['division_aliases'] ?? self::DEFAULT_ALIASES));
        $timeout = (int) (SystemSetting::get('pra_timeout') ?: ($cfg['timeout'] ?? 60));

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'tiers' => $tiers,
            'fiscal_year' => $fiscalYear,
            'division_aliases' => $this->parseAliases($aliasesRaw),
            'timeout' => max(10, min(300, $timeout > 0 ? $timeout : 60)),
        ];
    }

    public function isConfigured(): bool
    {
        $r = $this->resolved();

        return $r['base_url'] !== '' && $r['api_key'] !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function formPayload(): array
    {
        $cfg = (array) config('services.pra', []);
        $resolved = $this->resolved();
        $aliasesRaw = (string) (SystemSetting::get('pra_division_aliases')
            ?: ($cfg['division_aliases'] ?? self::DEFAULT_ALIASES));

        return [
            'base_url' => $resolved['base_url'],
            'api_key_set' => $resolved['api_key'] !== '',
            'tiers' => $resolved['tiers'],
            'fiscal_year' => $resolved['fiscal_year'],
            'division_aliases' => $aliasesRaw,
            'timeout' => $resolved['timeout'],
            'configured' => $this->isConfigured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): array
    {
        if (array_key_exists('base_url', $values)) {
            SystemSetting::set(
                'pra_api_url',
                rtrim(trim((string) $values['base_url']), '/'),
                self::GROUP,
                'text'
            );
        }

        if (array_key_exists('tiers', $values)) {
            $tiers = trim((string) $values['tiers']);
            SystemSetting::set('pra_tiers', $tiers !== '' ? $tiers : '3,4', self::GROUP, 'text');
        }

        if (array_key_exists('fiscal_year', $values)) {
            $fiscal = $values['fiscal_year'];
            SystemSetting::set(
                'pra_fiscal_year',
                ($fiscal === null || $fiscal === '') ? null : (string) (int) $fiscal,
                self::GROUP,
                'number'
            );
        }

        if (array_key_exists('division_aliases', $values)) {
            SystemSetting::set(
                'pra_division_aliases',
                trim((string) $values['division_aliases']),
                self::GROUP,
                'text'
            );
        }

        if (array_key_exists('timeout', $values)) {
            $timeout = max(10, min(300, (int) $values['timeout'] ?: 60));
            SystemSetting::set('pra_timeout', (string) $timeout, self::GROUP, 'number');
        }

        if (array_key_exists('api_key', $values)) {
            $key = trim((string) $values['api_key']);
            if ($key !== '') {
                WhatsAppSecretStore::set('pra_api_key', $key, self::GROUP);
            }
        }

        return $this->formPayload();
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
}
