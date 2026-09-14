<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed a practical catalog of earnings, benefits, and deductions.
 * System types BASIC / TAX / LOAN_DED are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            // Extra earnings (taxable cash)
            [
                'code' => 'ALLOW',
                'name' => 'General Allowance',
                'category' => 'earning',
                'calc_method' => 'percent_of_base',
                'taxable' => true,
                'pre_tax' => false,
                'sort_order' => 10,
            ],
            [
                'code' => 'OT',
                'name' => 'Overtime',
                'category' => 'earning',
                'calc_method' => 'fixed',
                'taxable' => true,
                'pre_tax' => false,
                'sort_order' => 15,
            ],
            [
                'code' => 'BONUS',
                'name' => 'Bonus',
                'category' => 'earning',
                'calc_method' => 'fixed',
                'taxable' => true,
                'pre_tax' => false,
                'sort_order' => 16,
            ],
            // Benefits (non-basic recurring)
            [
                'code' => 'HOUSING',
                'name' => 'Housing Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => true,
                'pre_tax' => false,
                'sort_order' => 20,
            ],
            [
                'code' => 'TRANSPORT',
                'name' => 'Transport Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => true,
                'pre_tax' => false,
                'sort_order' => 21,
            ],
            [
                'code' => 'MEAL',
                'name' => 'Meal Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 22,
            ],
            [
                'code' => 'MEDICAL',
                'name' => 'Medical Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 23,
            ],
            [
                'code' => 'COMM',
                'name' => 'Communication Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 24,
            ],
            [
                'code' => 'EDU',
                'name' => 'Education Benefit',
                'category' => 'benefit',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 25,
            ],
            // Employee deductions
            [
                'code' => 'PENSION_EE',
                'name' => 'Pension (Employee)',
                'category' => 'deduction',
                'calc_method' => 'percent_of_base',
                'taxable' => false,
                'pre_tax' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'SOCIAL_EE',
                'name' => 'Social Security (Employee)',
                'category' => 'deduction',
                'calc_method' => 'percent_of_base',
                'taxable' => false,
                'pre_tax' => true,
                'sort_order' => 41,
            ],
            [
                'code' => 'HEALTH_EE',
                'name' => 'Health Insurance (Employee)',
                'category' => 'deduction',
                'calc_method' => 'percent_of_gross',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 42,
            ],
            [
                'code' => 'UNION',
                'name' => 'Union Dues',
                'category' => 'deduction',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 43,
            ],
            [
                'code' => 'OTHER_DED',
                'name' => 'Other Deduction',
                'category' => 'deduction',
                'calc_method' => 'fixed',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 49,
            ],
            // Employer contributions (info on slip / costing)
            [
                'code' => 'PENSION_ER',
                'name' => 'Pension (Employer)',
                'category' => 'employer_contrib',
                'calc_method' => 'percent_of_base',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 60,
            ],
            [
                'code' => 'SOCIAL_ER',
                'name' => 'Social Security (Employer)',
                'category' => 'employer_contrib',
                'calc_method' => 'percent_of_base',
                'taxable' => false,
                'pre_tax' => false,
                'sort_order' => 61,
            ],
        ];

        foreach ($rows as $row) {
            $existing = DB::table('payroll_wage_types')->where('code', $row['code'])->first();
            $payload = [
                'name' => $row['name'],
                'category' => $row['category'],
                'calc_method' => $row['calc_method'],
                'percent_base' => in_array($row['calc_method'], ['percent_of_base', 'percent_of_gross'], true) ? 'basic' : null,
                'default_amount' => null,
                'taxable' => $row['taxable'],
                'pre_tax' => $row['pre_tax'],
                'is_system' => false,
                'is_active' => true,
                'sort_order' => $row['sort_order'],
                'updated_at' => $now,
            ];

            if ($existing) {
                // Keep ALLOW name upgrade if it was the smoke "Allowance"
                DB::table('payroll_wage_types')->where('id', $existing->id)->update($payload);

                continue;
            }

            DB::table('payroll_wage_types')->insert($payload + [
                'code' => $row['code'],
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $codes = [
            'OT', 'BONUS', 'HOUSING', 'TRANSPORT', 'MEAL', 'MEDICAL', 'COMM', 'EDU',
            'PENSION_EE', 'SOCIAL_EE', 'HEALTH_EE', 'UNION', 'OTHER_DED',
            'PENSION_ER', 'SOCIAL_ER',
        ];
        DB::table('payroll_wage_types')->whereIn('code', $codes)->where('is_system', false)->delete();
    }
};
