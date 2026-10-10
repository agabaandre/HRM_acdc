<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pra_org_unit_mappings')) {
            return;
        }

        Schema::table('pra_org_unit_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('pra_org_unit_mappings', 'pra_division_id')) {
                $table->string('pra_division_id', 64)->nullable()->after('pra_code');
                $table->index('pra_division_id');
            }
            if (! Schema::hasColumn('pra_org_unit_mappings', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('match_source');
            }
        });

        // Wire-back key: PRA workplan API exposes division.code (no numeric id today).
        foreach (DB::table('pra_org_unit_mappings')->select(['id', 'pra_code', 'pra_division_id'])->get() as $row) {
            if ($row->pra_division_id !== null && $row->pra_division_id !== '') {
                continue;
            }
            DB::table('pra_org_unit_mappings')
                ->where('id', $row->id)
                ->update(['pra_division_id' => strtoupper((string) $row->pra_code)]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pra_org_unit_mappings')) {
            return;
        }

        Schema::table('pra_org_unit_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('pra_org_unit_mappings', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }
            if (Schema::hasColumn('pra_org_unit_mappings', 'pra_division_id')) {
                $table->dropIndex(['pra_division_id']);
                $table->dropColumn('pra_division_id');
            }
        });
    }
};
