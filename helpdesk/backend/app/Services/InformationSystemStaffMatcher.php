<?php

namespace App\Services;

class InformationSystemStaffMatcher
{
    public function __construct(
        private readonly StaffDirectoryLookupService $directory,
    ) {}

    /**
     * @return array{staff_id: ?int, name_raw: ?string}
     */
    public function resolve(?string $displayName): array
    {
        $raw = trim((string) $displayName);
        if ($raw === '') {
            return ['staff_id' => null, 'name_raw' => null];
        }

        $normalized = $this->normalizeName($raw);
        $candidates = $this->directory->searchByName($normalized, 50);

        $exact = null;
        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $row) {
            $candName = $this->normalizeName((string) ($row['name'] ?? ''));
            if ($candName === '') {
                continue;
            }
            if ($candName === $normalized) {
                $exact = (int) $row['staff_id'];
                break;
            }
            similar_text($normalized, $candName, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = (int) $row['staff_id'];
            }
        }

        if ($exact !== null && $exact > 0) {
            return ['staff_id' => $exact, 'name_raw' => $raw];
        }
        if ($best !== null && $best > 0 && $bestScore >= 85.0) {
            return ['staff_id' => $best, 'name_raw' => $raw];
        }

        return ['staff_id' => null, 'name_raw' => $raw];
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\b(dr|mr|mrs|ms|prof)\.?\b/i', '', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? $name;

        return strtolower($name);
    }
}
