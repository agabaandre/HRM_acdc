<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet 3 — Reference & Methodology.
 */
class RiskLookupSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('rr_likelihoods')) {
            return;
        }

        $this->upsert('rr_likelihoods', [
            ['label' => 'Unlikely', 'score' => 1, 'sort_order' => 1],
            ['label' => 'Possible', 'score' => 2, 'sort_order' => 2],
            ['label' => 'Likely', 'score' => 3, 'sort_order' => 3],
            ['label' => 'Almost Certain', 'score' => 4, 'sort_order' => 4],
            ['label' => 'Certain', 'score' => 5, 'sort_order' => 5],
        ], 'score');

        $this->upsert('rr_impacts', [
            ['label' => 'Negligible', 'score' => 1, 'sort_order' => 1],
            ['label' => 'Minor', 'score' => 2, 'sort_order' => 2],
            ['label' => 'Moderate', 'score' => 3, 'sort_order' => 3],
            ['label' => 'Major', 'score' => 4, 'sort_order' => 4],
            ['label' => 'Critical', 'score' => 5, 'sort_order' => 5],
        ], 'score');

        $types = [
            'Strategic', 'Operational', 'Financial', 'Compliance', 'Reputational',
            'Project', 'Contextual', 'Public Health Emergency Response',
        ];
        foreach ($types as $i => $name) {
            if (! DB::table('rr_risk_types')->where('name', $name)->exists()) {
                DB::table('rr_risk_types')->insert([
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $themes = [
            '1. Sustainable financing and resource mobilization',
            '2. Workforce sustainability and institutional capability',
            '3. Fiduciary stewardship and internal control',
            '4. Program execution and delivery',
            '5. Country, partner, and geopolitical context',
            '6. Procurement and supply chain resilience',
            '7. Data governance and management reporting',
            '8. Integrity, compliance, and safeguarding',
            '9. Emergency readiness, duty of care, and biosafety/biosecurity',
        ];
        foreach ($themes as $i => $name) {
            if (! DB::table('rr_enterprise_themes')->where('name', $name)->exists()) {
                DB::table('rr_enterprise_themes')->insert([
                    'code' => (string) ($i + 1),
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (['Not Started', 'In Progress', 'Open - Extended', 'Closed - Mitigated', 'Overdue'] as $i => $name) {
            if (! DB::table('rr_statuses')->where('name', $name)->exists()) {
                DB::table('rr_statuses')->insert([
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $eff = [
            ['name' => 'Not Assessed', 'likelihood_reduction' => 0, 'is_assessed' => 0, 'sort_order' => 0],
            ['name' => 'Limited', 'likelihood_reduction' => 1, 'is_assessed' => 1, 'sort_order' => 1],
            ['name' => 'Partial', 'likelihood_reduction' => 2, 'is_assessed' => 1, 'sort_order' => 2],
            ['name' => 'Substantial', 'likelihood_reduction' => 3, 'is_assessed' => 1, 'sort_order' => 3],
            ['name' => 'Highly Effective', 'likelihood_reduction' => 4, 'is_assessed' => 1, 'sort_order' => 4],
        ];
        foreach ($eff as $row) {
            if (! DB::table('rr_mitigation_effectiveness')->where('name', $row['name'])->exists()) {
                DB::table('rr_mitigation_effectiveness')->insert($row + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $bands = [
            ['rating' => 'Low', 'band_key' => 'low', 'min_score' => 1, 'max_score' => 4, 'fill_color' => '#00B050', 'text_color' => '#FFFFFF', 'sort_order' => 1],
            ['rating' => 'Medium', 'band_key' => 'medium', 'min_score' => 5, 'max_score' => 9, 'fill_color' => '#FFFF00', 'text_color' => '#1A1A1A', 'sort_order' => 2],
            ['rating' => 'High', 'band_key' => 'high', 'min_score' => 10, 'max_score' => 15, 'fill_color' => '#FFC000', 'text_color' => '#1A1A1A', 'sort_order' => 3],
            ['rating' => 'Critical', 'band_key' => 'critical', 'min_score' => 16, 'max_score' => 25, 'fill_color' => '#C00000', 'text_color' => '#FFFFFF', 'sort_order' => 4],
        ];
        $hasBandKey = Schema::hasColumn('rr_rating_bands', 'band_key');
        foreach ($bands as $row) {
            $base = [
                'rating' => $row['rating'],
                'min_score' => $row['min_score'],
                'max_score' => $row['max_score'],
                'sort_order' => $row['sort_order'],
            ];
            if ($hasBandKey) {
                $base += [
                    'band_key' => $row['band_key'],
                    'fill_color' => $row['fill_color'],
                    'text_color' => $row['text_color'],
                ];
            }
            if (! DB::table('rr_rating_bands')->where('rating', $row['rating'])->exists()) {
                DB::table('rr_rating_bands')->insert($base + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($hasBandKey) {
                DB::table('rr_rating_bands')->where('rating', $row['rating'])->update([
                    'band_key' => $row['band_key'],
                    'fill_color' => $row['fill_color'],
                    'text_color' => $row['text_color'],
                    'updated_at' => now(),
                ]);
            }
        }

        if ($hasBandKey && Schema::hasTable('rr_rating_key_versions') && ! DB::table('rr_rating_key_versions')->exists()) {
            app(\App\Services\RatingBandResolver::class)->publish('Initial (Excel)');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsert(string $table, array $rows, string $uniqueKey): void
    {
        foreach ($rows as $row) {
            if (DB::table($table)->where($uniqueKey, $row[$uniqueKey])->exists()) {
                continue;
            }
            DB::table($table)->insert($row + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
