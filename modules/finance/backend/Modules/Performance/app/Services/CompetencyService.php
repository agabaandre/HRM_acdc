<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;

class CompetencyService
{
    /**
     * @return array<string, list<object>>
     */
    public function groupedByCategory(int $version = 1): array
    {
        $rows = DB::table('au_values')
            ->where('version', $version)
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->category][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<string, string>
     */
    public function categoryLabels(): array
    {
        return [
            'values' => 'AU Values',
            'core' => 'Core Competencies',
            'functional' => 'Functional Competencies',
            'leadership' => 'Leadership Competencies',
        ];
    }
}
