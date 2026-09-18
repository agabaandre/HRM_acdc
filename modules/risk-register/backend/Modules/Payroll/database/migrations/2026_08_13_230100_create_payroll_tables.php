<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $table): void {
                $table->id();
                $table->char('default_currency', 3)->default('USD');
                $table->json('enabled_currencies')->nullable();
                $table->unsignedTinyInteger('period_close_day')->default(25);
                $table->string('jurisdiction_default', 64)->nullable();
                $table->timestamps();
            });

            DB::table('payroll_settings')->insert([
                'default_currency' => 'USD',
                'enabled_currencies' => json_encode(['USD', 'ETB', 'EUR']),
                'period_close_day' => 25,
                'jurisdiction_default' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('payroll_wage_types')) {
            Schema::create('payroll_wage_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('category', 32); // earning|benefit|tax|deduction|employer_contrib
                $table->string('calc_method', 32)->default('fixed'); // fixed|percent_of_base|percent_of_gross|manual|formula
                $table->string('percent_base', 40)->nullable();
                $table->decimal('default_amount', 14, 2)->nullable();
                $table->boolean('taxable')->default(true);
                $table->boolean('pre_tax')->default(false);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('payroll_wage_types')->insert([
                [
                    'code' => 'BASIC',
                    'name' => 'Basic Salary',
                    'category' => 'earning',
                    'calc_method' => 'fixed',
                    'percent_base' => null,
                    'default_amount' => null,
                    'taxable' => true,
                    'pre_tax' => false,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'TAX',
                    'name' => 'Income Tax Withholding',
                    'category' => 'tax',
                    'calc_method' => 'formula',
                    'percent_base' => null,
                    'default_amount' => null,
                    'taxable' => false,
                    'pre_tax' => false,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => 90,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'code' => 'LOAN_DED',
                    'name' => 'Loan / Advance Deduction',
                    'category' => 'deduction',
                    'calc_method' => 'manual',
                    'percent_base' => null,
                    'default_amount' => null,
                    'taxable' => false,
                    'pre_tax' => false,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => 80,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (! Schema::hasTable('payroll_tax_rules')) {
            Schema::create('payroll_tax_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('jurisdiction_code', 64)->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('applies_to', 16)->default('employee'); // employee|employer
                $table->unsignedBigInteger('wage_type_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['jurisdiction_code', 'effective_from']);
            });
        }

        if (! Schema::hasTable('payroll_tax_bands')) {
            Schema::create('payroll_tax_bands', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tax_rule_id');
                $table->decimal('from_amount', 14, 2)->default(0);
                $table->decimal('to_amount', 14, 2)->nullable();
                $table->decimal('rate_percent', 8, 4)->default(0);
                $table->decimal('fixed_amount', 14, 2)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('tax_rule_id')->references('id')->on('payroll_tax_rules')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_staff_pay')) {
            Schema::create('payroll_staff_pay', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id')->unique();
                $table->char('currency', 3);
                $table->decimal('basic_salary', 14, 2)->default(0);
                $table->string('bank_name', 120)->nullable();
                $table->string('bank_account', 80)->nullable();
                $table->string('bank_branch', 120)->nullable();
                $table->string('tax_identifier', 80)->nullable();
                $table->string('pay_status', 20)->default('active'); // active|held|terminated
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_staff_wage_items')) {
            Schema::create('payroll_staff_wage_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->unsignedBigInteger('wage_type_id');
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('percent', 8, 4)->nullable();
                $table->char('currency', 3)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['staff_id', 'is_active']);
                $table->foreign('wage_type_id')->references('id')->on('payroll_wage_types')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->string('label', 40);
                $table->string('status', 16)->default('open'); // open|closed
                $table->timestamps();
                $table->unique(['year', 'month']);
            });
        }

        if (! Schema::hasTable('payroll_fx_rates')) {
            Schema::create('payroll_fx_rates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('period_id');
                $table->char('currency', 3);
                $table->decimal('rate_to_default', 18, 8)->default(1);
                $table->timestamps();
                $table->unique(['period_id', 'currency']);
                $table->foreign('period_id')->references('id')->on('payroll_periods')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('period_id');
                $table->string('status', 16)->default('draft'); // draft|simulated|posted|cancelled
                $table->boolean('off_cycle')->default(false);
                $table->string('title', 160)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('simulated_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedInteger('posted_by_user_id')->nullable();
                $table->unsignedInteger('staff_count')->default(0);
                $table->decimal('total_gross_default', 16, 2)->default(0);
                $table->decimal('total_net_default', 16, 2)->default(0);
                $table->timestamps();
                $table->index(['period_id', 'status']);
                $table->foreign('period_id')->references('id')->on('payroll_periods')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_run_lines')) {
            Schema::create('payroll_run_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->unsignedInteger('staff_id');
                $table->char('currency', 3);
                $table->decimal('basic', 14, 2)->default(0);
                $table->decimal('gross', 14, 2)->default(0);
                $table->decimal('taxable', 14, 2)->default(0);
                $table->decimal('tax', 14, 2)->default(0);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->decimal('benefits', 14, 2)->default(0);
                $table->decimal('net', 14, 2)->default(0);
                $table->decimal('fx_rate_to_default', 18, 8)->default(1);
                $table->decimal('net_default', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['run_id', 'staff_id']);
                $table->foreign('run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_run_line_items')) {
            Schema::create('payroll_run_line_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('run_line_id');
                $table->unsignedBigInteger('wage_type_id')->nullable();
                $table->string('category', 32);
                $table->decimal('amount', 14, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index('run_line_id');
                $table->foreign('run_line_id')->references('id')->on('payroll_run_lines')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_payslips')) {
            Schema::create('payroll_payslips', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('run_line_id')->unique();
                $table->unsignedInteger('staff_id');
                $table->unsignedBigInteger('period_id');
                $table->unsignedBigInteger('run_id');
                $table->string('pdf_path', 500)->nullable();
                $table->json('ytd')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->index(['staff_id', 'period_id']);
            });
        }

        if (! Schema::hasTable('payroll_loans')) {
            Schema::create('payroll_loans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->string('type', 16); // loan|advance
                $table->char('currency', 3);
                $table->decimal('principal', 14, 2);
                $table->decimal('interest_rate', 8, 4)->default(0);
                $table->decimal('installment_amount', 14, 2)->nullable();
                $table->unsignedSmallInteger('installment_count')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedInteger('requested_by_user_id')->nullable();
                $table->unsignedInteger('supervisor_id')->nullable();
                $table->unsignedInteger('approved_by_user_id')->nullable();
                $table->string('rejected_reason', 500)->nullable();
                $table->timestamp('disbursed_at')->nullable();
                $table->unsignedBigInteger('start_period_id')->nullable();
                $table->unsignedBigInteger('wage_type_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['staff_id', 'status']);
            });
        }

        if (! Schema::hasTable('payroll_loan_schedules')) {
            Schema::create('payroll_loan_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('loan_id');
                $table->unsignedSmallInteger('sequence');
                $table->unsignedBigInteger('due_period_id')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('status', 16)->default('pending'); // pending|deducted|waived|skipped
                $table->unsignedBigInteger('run_line_item_id')->nullable();
                $table->timestamps();
                $table->unique(['loan_id', 'sequence']);
                $table->foreign('loan_id')->references('id')->on('payroll_loans')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_audit_logs')) {
            Schema::create('payroll_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->string('action', 64);
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
        Schema::dropIfExists('payroll_loan_schedules');
        Schema::dropIfExists('payroll_loans');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_run_line_items');
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_fx_rates');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_staff_wage_items');
        Schema::dropIfExists('payroll_staff_pay');
        Schema::dropIfExists('payroll_tax_bands');
        Schema::dropIfExists('payroll_tax_rules');
        Schema::dropIfExists('payroll_wage_types');
        Schema::dropIfExists('payroll_settings');
    }
};
