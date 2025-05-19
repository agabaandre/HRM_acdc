<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matrix extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'focal_person_id',
        'division_id',
        'year',
        'quarter',
        'key_result_area',
        'staff_id',
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
            'focal_person_id' => 'integer',
            'division_id' => 'integer',
            'key_result_area' => 'array',
            'staff_id' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function focalPerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'focal_person_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }
}