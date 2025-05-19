<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'fund_type_id',
        'division_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'fund_type_id' => 'integer',
            'division_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the fund type that owns the fund code.
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * Get the division that owns the fund code.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
