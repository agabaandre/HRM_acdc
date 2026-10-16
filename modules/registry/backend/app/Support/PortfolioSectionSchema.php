<?php

namespace App\Support;

/**
 * Field schemas aligned to Africa CDC Funding Portfolio.xlsx sheets.
 */
final class PortfolioSectionSchema
{
    public const EDITABLE = [
        'extramural',
        'indirect_cost',
        'administrative_cost',
        'investments',
        'afef',
    ];

    /**
     * @return list<array{key: string, label: string, type: string, hint?: string}>
     */
    public static function fields(string $section): array
    {
        return match ($section) {
            'extramural' => [
                ['key' => 'funder_id', 'label' => 'APM funder', 'type' => 'funder', 'hint' => 'From APM /funders'],
                ['key' => 'funder', 'label' => 'Funder name (display)', 'type' => 'text'],
                ['key' => 'grant_id', 'label' => 'Grant', 'type' => 'grant', 'hint' => 'From Grant management'],
                ['key' => 'grant', 'label' => 'Grant / programme name', 'type' => 'text'],
                ['key' => 'approved_budget', 'label' => 'Approved budget (USD)', 'type' => 'number'],
                ['key' => 'released_budget', 'label' => 'Released budget (USD)', 'type' => 'number'],
                ['key' => 'commitment', 'label' => 'Commitment (USD)', 'type' => 'number'],
                ['key' => 'actual_expenditure', 'label' => 'Actual expenditure (USD)', 'type' => 'number'],
                ['key' => 'budget_funding_balance', 'label' => 'Budget funding balance (USD)', 'type' => 'number'],
                [
                    'key' => 'execution_pct',
                    'label' => 'Execution %',
                    'type' => 'computed',
                    'hint' => 'Auto: actual expenditure ÷ released budget (or approved if released is 0)',
                ],
                ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
            ],
            'indirect_cost' => [
                ['key' => 'funder_id', 'label' => 'APM funder', 'type' => 'funder', 'hint' => 'From APM /funders'],
                ['key' => 'funder', 'label' => 'Funder name (display)', 'type' => 'text'],
                ['key' => 'grant_id', 'label' => 'Grant', 'type' => 'grant', 'hint' => 'From Grant management'],
                ['key' => 'grant_name', 'label' => 'Grant name (display)', 'type' => 'text'],
                ['key' => 'end_date', 'label' => 'End date', 'type' => 'date'],
                ['key' => 'total_grant', 'label' => 'Total grant / budget', 'type' => 'number'],
                ['key' => 'direct_cost_usd', 'label' => 'Direct cost (USD)', 'type' => 'number'],
                ['key' => 'acdc_indirect_cost', 'label' => 'ACDC indirect cost', 'type' => 'number'],
                ['key' => 'indirect_pct', 'label' => '% of indirect cost', 'type' => 'number'],
                ['key' => 'funds_received', 'label' => 'Funds received (disbursement)', 'type' => 'number'],
                ['key' => 'total_expenses', 'label' => 'Total expenses', 'type' => 'number'],
                ['key' => 'indirect_to_earn', 'label' => 'Indirect cost to be earned', 'type' => 'number'],
                ['key' => 'budget_code', 'label' => 'Budget code', 'type' => 'text'],
                ['key' => 'indirect_committed', 'label' => 'Indirect cost committed', 'type' => 'number'],
                ['key' => 'indirect_available', 'label' => 'Indirect cost available', 'type' => 'number'],
                ['key' => 'comment', 'label' => 'Comment', 'type' => 'textarea'],
            ],
            'administrative_cost' => [
                ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
                ['key' => 'clearing_document', 'label' => 'Clearing document', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                ['key' => 'text', 'label' => 'Text / period', 'type' => 'text'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                ['key' => 'funds_center', 'label' => 'Funds center', 'type' => 'text'],
                ['key' => 'fund', 'label' => 'Fund', 'type' => 'text'],
            ],
            'investments' => [
                ['key' => 'gl_account', 'label' => 'GL account', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account name', 'type' => 'text'],
                ['key' => 'bank_name', 'label' => 'Bank name', 'type' => 'text'],
                ['key' => 'account_number', 'label' => 'Account number', 'type' => 'text'],
                ['key' => 'account_type', 'label' => 'Member States / Partner', 'type' => 'text'],
                ['key' => 'region_id', 'label' => 'Region', 'type' => 'region', 'hint' => 'Staff Portal regions'],
                ['key' => 'region', 'label' => 'Region (display)', 'type' => 'text'],
                ['key' => 'country_id', 'label' => 'Country', 'type' => 'country', 'hint' => 'Staff Portal nationalities'],
                ['key' => 'country', 'label' => 'Country (display)', 'type' => 'text'],
                ['key' => 'location_id', 'label' => 'Location / duty station', 'type' => 'location', 'hint' => 'Staff Portal duty stations'],
                ['key' => 'location', 'label' => 'Location (display)', 'type' => 'text'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                ['key' => 'bank_balance_usd', 'label' => 'Bank balance (USD)', 'type' => 'number'],
                ['key' => 'invested_amount', 'label' => 'Invested amount (USD)', 'type' => 'number'],
                ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
            ],
            'afef' => [
                ['key' => 'category', 'label' => 'Category', 'type' => 'afef_category', 'hint' => 'From More → AfEF categories'],
                ['key' => 'label', 'label' => 'Description', 'type' => 'text'],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount (USD)', 'type' => 'number'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyRow(string $section): array
    {
        $row = [];
        foreach (self::fields($section) as $field) {
            $row[$field['key']] = match ($field['type']) {
                'number', 'computed' => 0,
                'funder', 'grant', 'region', 'country', 'location', 'afef_category' => null,
                default => '',
            };
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::EDITABLE as $section) {
            $out[$section] = [
                'key' => $section,
                'fields' => self::fields($section),
                'empty_row' => self::emptyRow($section),
            ];
        }

        return $out;
    }
}
