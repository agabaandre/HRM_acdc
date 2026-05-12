<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;
    
    // Using standard Laravel 'id' as primary key

    protected $table="";
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = env('DIVISIONS_TABLE', 'divisions');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'division_name',
        'division_short_name',
        'division_head',
        'focal_person',
        'admin_assistant',
        'finance_officer',
        'finance_officer_oic_id',
        'finance_officer_oic_start_date',
        'finance_officer_oic_end_date',
        'directorate_id',
        'head_oic_id',
        'head_oic_start_date',
        'head_oic_end_date',
        'director_id',
        'director_oic_id',
        'director_oic_start_date',
        'director_oic_end_date',
        'category',
        'created_at',
        'updated_at',
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
            'name' => 'string',
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

    public function divisionHead(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'division_head', 'staff_id');
    }

    public function focalPerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'focal_person', 'staff_id');
    }

    public function adminAssistant(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'admin_assistant', 'staff_id');
    }

    public function financeOfficer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'finance_officer', 'staff_id');
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    /**
     * Divisions where this staff member is the named director or is the active director OIC (within OIC dates).
     */
    public static function queryForStaffActingAsDirector(int $staffId): Builder
    {
        $today = Carbon::now()->toDateString();

        return static::query()
            ->where(function ($q) use ($staffId, $today) {
                $q->where('director_id', $staffId)
                    ->orWhere(function ($q2) use ($staffId, $today) {
                        $q2->where('director_oic_id', $staffId)
                            ->where(function ($q3) use ($today) {
                                $q3->whereNull('director_oic_start_date')
                                    ->orWhereDate('director_oic_start_date', '<=', $today);
                            })
                            ->where(function ($q4) use ($today) {
                                $q4->whereNull('director_oic_end_date')
                                    ->orWhereDate('director_oic_end_date', '>=', $today);
                            });
                    });
            });
    }

    /**
     * Weekly brief: divisions this user may manage (named director or director OIC, or Head of Division / head OIC).
     */
    public static function queryForWeeklyBriefDivisionAuthority(int $staffId): Builder
    {
        $today = Carbon::now()->toDateString();

        return static::query()
            ->where(function ($outer) use ($staffId, $today) {
                $outer->where(function ($q) use ($staffId, $today) {
                    $q->where('director_id', $staffId)
                        ->orWhere(function ($q2) use ($staffId, $today) {
                            $q2->where('director_oic_id', $staffId)
                                ->where(function ($q3) use ($today) {
                                    $q3->whereNull('director_oic_start_date')
                                        ->orWhereDate('director_oic_start_date', '<=', $today);
                                })
                                ->where(function ($q4) use ($today) {
                                    $q4->whereNull('director_oic_end_date')
                                        ->orWhereDate('director_oic_end_date', '>=', $today);
                                });
                        });
                })->orWhere(function ($q) use ($staffId, $today) {
                    $q->where('division_head', $staffId)
                        ->orWhere(function ($q2) use ($staffId, $today) {
                            $q2->where('head_oic_id', $staffId)
                                ->where(function ($q3) use ($today) {
                                    $q3->whereNull('head_oic_start_date')
                                        ->orWhereDate('head_oic_start_date', '<=', $today);
                                })
                                ->where(function ($q4) use ($today) {
                                    $q4->whereNull('head_oic_end_date')
                                        ->orWhereDate('head_oic_end_date', '>=', $today);
                                });
                        });
                });
            });
    }

    /**
     * True if this staff member is the division director or is the acting director (OIC) for today.
     */
    public function staffActsAsDivisionDirector(int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }
        if ((int) ($this->director_id ?? 0) === $staffId) {
            return true;
        }
        if ((int) ($this->director_oic_id ?? 0) !== $staffId) {
            return false;
        }
        $today = Carbon::now()->toDateString();
        $start = $this->director_oic_start_date;
        $end = $this->director_oic_end_date;
        if ($start !== null && $start !== '' && Carbon::parse($start)->toDateString() > $today) {
            return false;
        }
        if ($end !== null && $end !== '' && Carbon::parse($end)->toDateString() < $today) {
            return false;
        }

        return true;
    }

    /**
     * Weekly brief access: division director / director OIC, or effective Head of Division (same rules as approvals).
     */
    public function staffActsAsWeeklyBriefDivisionAuthority(int $staffId): bool
    {
        if ($this->staffActsAsDivisionDirector($staffId)) {
            return true;
        }
        if (! function_exists('effective_division_head_staff_id')) {
            return false;
        }
        $head = effective_division_head_staff_id($this);

        return $head !== null && (int) $head === $staffId;
    }

    /**
     * Staff id that should receive director-scoped weekly brief actions (OIC takes precedence when active).
     */
    public function primaryOrActiveDirectorStaffIdForWeeklyBrief(): ?int
    {
        $oic = (int) ($this->director_oic_id ?? 0);
        if ($oic > 0 && $this->staffActsAsDivisionDirector($oic)) {
            return $oic;
        }
        $d = (int) ($this->director_id ?? 0);

        return $d > 0 ? $d : null;
    }
}