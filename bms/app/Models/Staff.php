<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'staff_id',
        'work_email',
        'sap_no',
        'title',
        'fname',
        'lname',
        'oname',
        'grade',
        'gender',
        'date_of_birth',
        'job_name',
        'contracting_institution',
        'contract_type',
        'nationality',
        'division_name',
        'division_id',
        'duty_station_id',
        'status',
        'tel_1',
        'whatsapp',
        'private_email',
        'photo',
        'physical_location',
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
            'date_of_birth' => 'date',
            'division_id' => 'integer',
            'duty_station_id' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active staff.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the full name of the staff member.
     */
    public function getNameAttribute()
    {
        return "{$this->fname} {$this->lname}";
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function dutyStation(): BelongsTo
    {
        return $this->belongsTo(DutyStation::class);
    }

    public function matrices(): HasMany
    {
        return $this->hasMany(Matrix::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function nonTravelMemos(): HasMany
    {
        return $this->hasMany(NonTravelMemo::class);
    }
}
