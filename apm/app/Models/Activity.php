<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Activity extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'returned';

    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
        'workplan_activity_code',
        'matrix_id',
        'staff_id',
        'responsible_person_id',
        'date_from',
        'date_to',
        'total_participants',
        'total_external_participants',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_special_memo',
        'status',
        'fund_type_id',

        // JSON fields
        'location_id',
        'internal_participants',
        'budget_id',
        'budget',
        'attachment',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['formatted_dates'];

    protected $casts = [
        'id' => 'integer',
        'forward_workflow_id' => 'integer',
        'reverse_workflow_id' => 'integer',
        'matrix_id' => 'integer',
        'staff_id' => 'integer',
        'responsible_person_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'is_special_memo' => 'boolean',
        'date_from' => 'date',
        'date_to' => 'date',
        'total_participants' => 'integer',
        'total_external_participants' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // JSON
        'location_id' => 'array',
        'internal_participants' => 'array',
        'budget_id' => 'array',
        'budget' => 'array',
        'attachment' => 'array',
    ];

    // --- Relationships ---

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id');
    }

    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }

    // JSON-BASED: location_id[] mapped to Location model
    public function getLocationsAttribute()
    {
        return Location::whereIn('id', $this->location_id ?? [])->get();
    }

    // JSON-BASED: budget_id[] mapped to FundCode model
    public function getFundCodesAttribute()
    {
        return FundCode::whereIn('id', $this->budget_id ?? [])->get();
    }

    // JSON-BASED: internal_participants[] mapped to Staff
    public function getInternalParticipantsDetailsAttribute()
    {
        return Staff::whereIn('staff_id', $this->internal_participants ?? [])->get();
    }


    // --- Accessors & Utility ---

    public function getFormattedDatesAttribute(): string
    {
        return $this->date_from->format('M j, Y') . ' - ' . $this->date_to->format('M j, Y');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (empty($activity->workplan_activity_code)) {
                $activity->workplan_activity_code = $activity->generateActivityCode();
            }
        });
    }

    protected function generateActivityCode(): string
    {
        $division_name = user_session('division_name');
        $short = ucwords($this->generateShortCodeFromDivision($division_name));
        $prefix = 'AU/CDC/' . $short . '/QM';
        $quarter = 'Q' . $this->matrix->quarter;
        $year = substr($this->matrix->year, -2);
    
        $latestActivity = self::where('matrix_id', $this->matrix_id)
            ->where('workplan_activity_code', 'like', "{$prefix}/{$quarter}/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();
    
        $sequence = $latestActivity
            ? ((int) last(explode('/', $latestActivity->workplan_activity_code)) + 1)
            : 1;
    
        return "{$prefix}/{$quarter}/{$year}/" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate short code from division name by removing joining words and using initials
     */
    protected function generateShortCodeFromDivision(string $name): string
    {
        $ignore = ['of', 'and', 'for', 'the', 'in'];
        $words = preg_split('/\s+/', strtolower($name));
        $initials = array_map(function ($word) use ($ignore) {
            return in_array($word, $ignore) ? '' : strtoupper($word[0]);
        }, $words);
    
        return implode('', array_filter($initials));
    }

    public function getBudgetAttribute($value)
    {
        return json_decode($value); // or false for object
    }
    
}
