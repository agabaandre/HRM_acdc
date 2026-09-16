<?php

namespace Modules\Settings\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures divisions.risk_focal_person exists and is seeded from focal_person.
 */
class DivisionRiskFocalService
{
    public static function ensureColumnAndBackfill(): void
    {
        if (! Schema::hasTable('divisions')) {
            return;
        }

        if (! Schema::hasColumn('divisions', 'risk_focal_person')) {
            Schema::table('divisions', function (Blueprint $table): void {
                $table->unsignedInteger('risk_focal_person')->nullable()->after('focal_person');
            });
        }

        DB::table('divisions')
            ->whereNull('risk_focal_person')
            ->whereNotNull('focal_person')
            ->update([
                'risk_focal_person' => DB::raw('focal_person'),
            ]);
    }
}
