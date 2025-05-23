<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;
    
    // Using standard Laravel 'id' as primary key

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'division_name',
        'division_head',
        'focal_person',
        'admin_assistant',
        'finance_officer',
        'staff_ids',
        'is_external',
        'directorate_id',
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
            'division_head' => 'integer',
            'focal_person' => 'integer',
            'admin_assistant' => 'integer',
            'finance_officer' => 'integer',
            'staff_ids' => 'array',
            'is_external' => 'boolean',
            'directorate_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function fundCodes(): HasMany
    {
        return $this->hasMany(FundCode::class);
    }

    public function matrices(): HasMany
    {
        return $this->hasMany(Matrix::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }
}