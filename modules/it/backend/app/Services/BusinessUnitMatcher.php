<?php

namespace App\Services;

/**
 * Map Excel "Business Unit" values (e.g. PHC/CHSHP, EPR) to Staff Portal org ids.
 */
final class BusinessUnitMatcher
{
    /**
     * Excel business-unit labels → division_short_name (ci).
     *
     * @var array<string, string>
     */
    private const DIVISION_ALIASES = [
        'western - rcc' => 'WRCC',
        'western rcc' => 'WRCC',
        'center - rcc' => 'CRCC',
        'central - rcc' => 'CRCC',
        'central rcc' => 'CRCC',
        'eastern- rcc' => 'ERCC',
        'eastern - rcc' => 'ERCC',
        'eastern rcc' => 'ERCC',
        'northern - rcc' => 'NRCC',
        'northern rcc' => 'NRCC',
        'southern - rcc' => 'SRCC',
        'southern rcc' => 'SRCC',
        'supply chain/ procurement' => 'SCM',
        'supply chain' => 'SCM',
        'procurement' => 'SCM',
        'hr' => 'HRM',
        'human resources' => 'HRM',
        'digital health' => 'DHIS',
        'lab' => 'LAB',
        'laboratory' => 'LAB',
        'finance' => 'DFIN',
        'science' => 'DSI',
        'legal' => 'LADS',
    ];

    /** @var list<array<string, mixed>> */
    private array $divisions;

    /** @var list<array<string, mixed>> */
    private array $directorates;

    /**
     * @param  array{divisions?: list<array<string, mixed>>, directorates?: list<array<string, mixed>>}  $org
     */
    public function __construct(array $org)
    {
        $this->divisions = array_values($org['divisions'] ?? []);
        $this->directorates = array_values($org['directorates'] ?? []);
    }

    /**
     * @return array{division_id:?int,directorate_id:?int,unmapped:?string,division_head:?int}
     */
    public function match(string $businessUnit): array
    {
        $raw = trim($businessUnit);
        if ($raw === '') {
            return [
                'division_id' => null,
                'directorate_id' => null,
                'unmapped' => null,
                'division_head' => null,
            ];
        }

        $parts = preg_split('#[/\\\\|]#', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $p) => $p !== ''));

        $directorateToken = null;
        $divisionToken = $raw;
        if (count($parts) >= 2) {
            $directorateToken = $parts[0];
            $divisionToken = $parts[count($parts) - 1];
        } elseif (count($parts) === 1) {
            $divisionToken = $parts[0];
        }

        $division = $this->findDivision($divisionToken);
        $directorateId = null;
        if ($directorateToken !== null) {
            $directorateId = $this->findDirectorateId($directorateToken);
        }
        if ($directorateId === null && $division !== null) {
            $directorateId = isset($division['directorate_id']) && $division['directorate_id'] !== null
                ? (int) $division['directorate_id']
                : null;
        }

        if ($division === null) {
            return [
                'division_id' => null,
                'directorate_id' => $directorateId,
                'unmapped' => $raw,
                'division_head' => null,
            ];
        }

        $head = $division['division_head'] ?? null;

        return [
            'division_id' => (int) $division['division_id'],
            'directorate_id' => $directorateId,
            'unmapped' => null,
            'division_head' => $head !== null && $head !== '' ? (int) $head : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDivision(string $token): ?array
    {
        $needle = $this->normalizeToken($token);
        if ($needle === '') {
            return null;
        }

        if (isset(self::DIVISION_ALIASES[$needle])) {
            $aliasShort = mb_strtolower(self::DIVISION_ALIASES[$needle]);
            foreach ($this->divisions as $div) {
                $short = mb_strtolower(trim((string) ($div['division_short_name'] ?? '')));
                if ($short !== '' && $short === $aliasShort) {
                    return $div;
                }
            }
        }

        foreach ($this->divisions as $div) {
            $short = mb_strtolower(trim((string) ($div['division_short_name'] ?? '')));
            if ($short !== '' && $short === $needle) {
                return $div;
            }
        }

        foreach ($this->divisions as $div) {
            $name = $this->normalizeToken((string) ($div['division_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
                return $div;
            }
        }

        return null;
    }

    private function normalizeToken(string $token): string
    {
        $token = mb_strtolower(trim($token));
        $token = str_replace(['–', '—'], '-', $token);
        $token = preg_replace('/\s+/', ' ', $token) ?? $token;

        return $token;
    }

    private function findDirectorateId(string $token): ?int
    {
        $needle = mb_strtolower(trim($token));
        if ($needle === '') {
            return null;
        }

        foreach ($this->directorates as $dir) {
            $aliases = $dir['aliases'] ?? [];
            if (! is_array($aliases)) {
                $aliases = [];
            }
            foreach ($aliases as $alias) {
                if (mb_strtolower(trim((string) $alias)) === $needle) {
                    return (int) ($dir['id'] ?? $dir['directorate_id'] ?? 0) ?: null;
                }
            }
            $name = mb_strtolower(trim((string) ($dir['name'] ?? $dir['directorate_name'] ?? '')));
            if ($name !== '' && ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name))) {
                return (int) ($dir['id'] ?? $dir['directorate_id'] ?? 0) ?: null;
            }
            $short = mb_strtolower(trim((string) ($dir['short_name'] ?? $dir['directorate_short_name'] ?? '')));
            if ($short !== '' && $short === $needle) {
                return (int) ($dir['id'] ?? $dir['directorate_id'] ?? 0) ?: null;
            }
        }

        return null;
    }
}
