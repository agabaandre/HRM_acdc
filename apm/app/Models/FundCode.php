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
        'year',
        'code',
        'name',
        'description',
        'fund_type_id',
        'division_id',
        'is_active',
        'end_date',
        'available_balance',
    ];

    protected $casts = [
        'id' => 'integer',
        'year' => 'integer',
        'fund_type_id' => 'integer',
        'division_id' => 'integer',
        'is_active' => 'boolean',
        'end_date' => 'date',
        'available_balance' => 'decimal:2',
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
}
