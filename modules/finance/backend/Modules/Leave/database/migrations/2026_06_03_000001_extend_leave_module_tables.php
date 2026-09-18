<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                if (! Schema::hasColumn('leave_types', 'code')) {
                    $table->string('code', 40)->nullable()->after('leave_name');
                }
                if (! Schema::hasColumn('leave_types', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('accrual_rate');
                }
                if (! Schema::hasColumn('leave_types', 'requires_hr_approval')) {
                    $table->boolean('requires_hr_approval')->default(false)->after('is_active');
                }
                if (! Schema::hasColumn('leave_types', 'requires_medical_certificate')) {
                    $table->boolean('requires_medical_certificate')->default(false)->after('requires_hr_approval');
                }
                if (! Schema::hasColumn('leave_types', 'medical_report_after_days')) {
                    $table->unsignedSmallInteger('medical_report_after_days')->nullable()->after('requires_medical_certificate');
                }
                if (! Schema::hasColumn('leave_types', 'max_instances')) {
                    $table->unsignedSmallInteger('max_instances')->nullable()->after('medical_report_after_days');
                }
                if (! Schema::hasColumn('leave_types', 'max_days_per_year')) {
                    $table->decimal('max_days_per_year', 8, 2)->nullable()->after('max_instances');
                }
                if (! Schema::hasColumn('leave_types', 'min_days_per_year')) {
                    $table->decimal('min_days_per_year', 8, 2)->nullable()->after('max_days_per_year');
                }
                if (! Schema::hasColumn('leave_types', 'deduct_compensatory_first')) {
                    $table->boolean('deduct_compensatory_first')->default(false)->after('min_days_per_year');
                }
                if (! Schema::hasColumn('leave_types', 'policy_notes')) {
                    $table->text('policy_notes')->nullable()->after('deduct_compensatory_first');
                }
                if (! Schema::hasColumn('leave_types', 'sort_order')) {
                    $table->unsignedSmallInteger('sort_order')->default(0)->after('policy_notes');
                }
            });
        }

        if (! Schema::hasTable('leave_policy_settings')) {
            Schema::create('leave_policy_settings', function (Blueprint $table): void {
                $table->string('setting_key', 80)->primary();
                $table->json('setting_value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_leave_opening_balances')) {
            Schema::create('staff_leave_opening_balances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->unsignedInteger('leave_id');
                $table->unsignedSmallInteger('calendar_year');
                $table->decimal('opening_days', 8, 2)->default(0);
                $table->decimal('carried_forward_days', 8, 2)->default(0);
                $table->decimal('compensatory_days', 8, 2)->default(0);
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('updated_by_user_id')->nullable();
                $table->timestamps();
                $table->unique(['staff_id', 'leave_id', 'calendar_year'], 'staff_leave_opening_unique');
            });
        }

        if (! Schema::hasTable('staff_leave_compensatory_credits')) {
            Schema::create('staff_leave_compensatory_credits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->decimal('days', 8, 2);
                $table->decimal('days_used', 8, 2)->default(0);
                $table->string('reason', 500)->nullable();
                $table->date('granted_on');
                $table->date('expires_on')->nullable();
                $table->unsignedInteger('granted_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_compensatory_credits');
        Schema::dropIfExists('staff_leave_opening_balances');
        Schema::dropIfExists('leave_policy_settings');

        if (Schema::hasTable('leave_types')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                foreach ([
                    'code', 'is_active', 'requires_hr_approval', 'requires_medical_certificate',
                    'medical_report_after_days', 'max_instances', 'max_days_per_year',
                    'min_days_per_year', 'deduct_compensatory_first', 'policy_notes', 'sort_order',
                ] as $column) {
                    if (Schema::hasColumn('leave_types', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
