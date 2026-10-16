<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinGrant extends Model
{
    protected $table = 'fin_grants';

    protected $fillable = [
        'name',
        'short_name',
        'funder_id',
        'funder_name',
        'end_date',
        'total_budget',
        'direct_cost_usd',
        'indirect_cost',
        'indirect_pct',
        'budget_code',
        'status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'funder_id' => 'integer',
            'end_date' => 'date',
            'total_budget' => 'float',
            'direct_cost_usd' => 'float',
            'indirect_cost' => 'float',
            'indirect_pct' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
