<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Support\LeavePermissions;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_holiday_rules')) {
            Schema::create('leave_holiday_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->nullable()->unique();
                $table->string('name', 150);
                $table->string('recurrence', 20);
                $table->unsignedTinyInteger('month')->nullable();
                $table->unsignedTinyInteger('day')->nullable();
                $table->date('once_date')->nullable();
                $table->string('scope', 20)->default('country');
                $table->string('country_iso2', 2)->nullable();
                $table->unsignedInteger('duty_station_id')->nullable();
                $table->boolean('grants_compensatory_if_weekend')->default(true);
                $table->json('compensatory_duty_station_ids')->nullable();
                $table->string('source', 20)->default('manual');
                $table->string('openholidays_id', 64)->nullable();
                $table->boolean('is_movable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['scope', 'country_iso2', 'is_active'], 'leave_holiday_scope_iso_active');
            });
        }

        if (Schema::hasTable('nationalities')) {
            Schema::table('nationalities', function (Blueprint $table): void {
                if (! Schema::hasColumn('nationalities', 'independence_month')) {
                    $table->unsignedTinyInteger('independence_month')->nullable();
                }
                if (! Schema::hasColumn('nationalities', 'independence_day')) {
                    $table->unsignedTinyInteger('independence_day')->nullable();
                }
            });
        }

        if (Schema::hasTable('duty_stations') && ! Schema::hasColumn('duty_stations', 'country_iso2')) {
            Schema::table('duty_stations', function (Blueprint $table): void {
                $table->string('country_iso2', 2)->nullable();
            });
        }

        if (Schema::hasTable('staff_leave_compensatory_credits')) {
            Schema::table('staff_leave_compensatory_credits', function (Blueprint $table): void {
                if (! Schema::hasColumn('staff_leave_compensatory_credits', 'kind')) {
                    $table->string('kind', 20)->default('other');
                }
                if (! Schema::hasColumn('staff_leave_compensatory_credits', 'source_holiday_rule_id')) {
                    $table->unsignedInteger('source_holiday_rule_id')->nullable();
                }
                if (! Schema::hasColumn('staff_leave_compensatory_credits', 'source_date')) {
                    $table->date('source_date')->nullable();
                }
            });
            try {
                Schema::table('staff_leave_compensatory_credits', function (Blueprint $table): void {
                    $table->unique(['staff_id', 'kind', 'source_date'], 'staff_comp_credit_holiday_unique');
                });
            } catch (\Throwable) {
                // Index already present or duplicates exist.
            }
        }

        $perm = [
            'id' => LeavePermissions::MANAGE_HOLIDAYS,
            'name' => 'manage_leave_holidays',
            'definition' => 'Manage Leave Holidays',
            'module' => 'leave',
        ];
        $existing = DB::table('permissions')->where('id', $perm['id'])->first();
        if ($existing) {
            DB::table('permissions')->where('id', $perm['id'])->update([
                'name' => $perm['name'],
                'definition' => $perm['definition'],
                'module' => $perm['module'],
            ]);
        } elseif (! DB::table('permissions')->where('name', $perm['name'])->exists()) {
            DB::table('permissions')->insert($perm);
        }

        foreach ([10, 20, 22] as $groupId) {
            if (! Schema::hasTable('user_groups') || ! DB::table('user_groups')->where('id', $groupId)->exists()) {
                continue;
            }
            $exists = DB::table('group_permissions')
                ->where('group_id', $groupId)
                ->where('permission_id', LeavePermissions::MANAGE_HOLIDAYS)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('group_permissions')->insert([
                'group_id' => $groupId,
                'permission_id' => LeavePermissions::MANAGE_HOLIDAYS,
                'last_updated' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_leave_compensatory_credits')) {
            Schema::table('staff_leave_compensatory_credits', function (Blueprint $table): void {
                foreach (['source_date', 'source_holiday_rule_id', 'kind'] as $column) {
                    if (Schema::hasColumn('staff_leave_compensatory_credits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        Schema::dropIfExists('leave_holiday_rules');
    }
};
