<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundCode extends Model
{
    use HasFactory;

    protected $table = 'fund_codes';

    protected $fillable = [
        'funder_id',
        'year',
        'code',
        'activity',
        'fund_type_id',
        'division_id',
        'cost_centre',
        'amert_code',
        'fund',
        'is_active',
        'budget_balance',
        'approved_budget',
        'uploaded_budget',
    ];

    protected $casts = [
        'id' => 'integer',
        'funder_id' => 'integer',
        'year' => 'integer',
        'fund_type_id' => 'integer',
        'division_id' => 'integer',
        'is_active' => 'boolean',
        'budget_balance' => 'string',
        'approved_budget' => 'string',
        'uploaded_budget' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
    public function funder(): BelongsTo
{
    return $this->belongsTo(Funder::class, 'funder_id');
}
}
