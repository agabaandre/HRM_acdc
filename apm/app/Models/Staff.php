<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Staff extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $primaryKey = 'staff_id';
    public $incrementing = true;
    protected $keyType = 'int';
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
        'duty_station_name',
        'status',
        'tel_1',
        'whatsapp',
        'private_email',
        'photo',
        'signature',
        'physical_location',
        'supervisor_id',
        'active',
        'created_at',
        'updated_at',
    ];

    
    protected $appends = ['division_days','other_days'];


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
            'directorate_id' => 'integer',
            'duty_station_id' => 'integer',
            'supervisor_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active staff.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['Active', 'Due','Expired','Under Renewal']);
    }

    /**
     * Get the full name of the staff member.
     */
    public function getNameAttribute()
    {
        // Handles possible nulls and extra spaces
        return trim(collect([$this->title, $this->fname, $this->lname, $this->oname])
            ->filter()
            ->implode(' '));
    }

    
    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    public function dutyStation(): BelongsTo
    {
        return $this->belongsTo(DutyStation::class, 'duty_station_id');
    }

    public function matrices(): HasMany
    {
        return $this->hasMany(Matrix::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class,'staff_id');
    }

    public function nonTravelMemos(): HasMany
    {
        return $this->hasMany(NonTravelMemo::class);
    }
    

    public function division(){
        return $this->belongsTo(Division::class,'division_id');
    }

    public function participant_schedules()
    {
        return $this->hasMany(ParticipantSchedule::class, 'participant_id', 'staff_id');
    }
    
    public function getDivisionDaysAttribute(): array
    {
        return $this->participant_schedules()
            ->where('is_home_division', 1)
            ->where('international_travel', 1)
            ->get()
            ->groupBy(fn($s) =>  $s->quarter . '-' . $s->year)
            ->map(fn($group) => $group->sum('participant_days'))
            ->toArray(); // returns: ['Q2-2025' => 12, ...]
    }

    public function getOtherDaysAttribute(): array
    {
        return $this->participant_schedules()
            ->where('is_home_division', 0)
            ->where('international_travel', 1)
            ->get()
            ->groupBy(fn($s) =>  $s->quarter . '-' . $s->year)
            ->map(fn($group) => $group->sum('participant_days'))
            ->toArray(); // returns: ['Q2-2025' => 5, ...]
    }

}
