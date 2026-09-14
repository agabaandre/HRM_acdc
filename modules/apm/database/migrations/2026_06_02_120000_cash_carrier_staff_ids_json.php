<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['change_request', 'special_memos'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'cash_carrier_staff_ids')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $after = Schema::hasColumn($table, 'request_travel_with_cash')
                        ? 'request_travel_with_cash'
                        : 'supporting_reasons';
                    $blueprint->json('cash_carrier_staff_ids')->nullable()->after($after);
                });
            }

            if (Schema::hasColumn($table, 'cash_carrier_staff_id')) {
                foreach (DB::table($table)
                    ->whereNotNull('cash_carrier_staff_id')
                    ->where('cash_carrier_staff_id', '>', 0)
                    ->get(['id', 'cash_carrier_staff_id']) as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([
                            'cash_carrier_staff_ids' => json_encode([(int) $row->cash_carrier_staff_id]),
                        ]);
                }

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('cash_carrier_staff_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['change_request', 'special_memos'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'cash_carrier_staff_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $after = Schema::hasColumn($table, 'request_travel_with_cash')
                        ? 'request_travel_with_cash'
                        : 'supporting_reasons';
                    $blueprint->unsignedInteger('cash_carrier_staff_id')->nullable()->after($after);
                });
            }

            if (Schema::hasColumn($table, 'cash_carrier_staff_ids')) {
                foreach (DB::table($table)
                    ->whereNotNull('cash_carrier_staff_ids')
                    ->get(['id', 'cash_carrier_staff_ids']) as $row) {
                    $ids = json_decode((string) $row->cash_carrier_staff_ids, true);
                    $first = is_array($ids) && isset($ids[0]) ? (int) $ids[0] : null;
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['cash_carrier_staff_id' => $first > 0 ? $first : null]);
                }

                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('cash_carrier_staff_ids');
                });
            }
        }
    }
};
