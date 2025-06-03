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

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // Activity status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
        'workplan_activity_code',
        'matrix_id',
        'staff_id',
        'date_from',
        'date_to',
        'total_participants',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_special_memo',
        'status',
        'fund_type_id',
    ];
    
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['formatted_dates'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'forward_workflow_id' => 'integer',
        'reverse_workflow_id' => 'integer',
        'matrix_id' => 'integer',
        'staff_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'is_special_memo' => 'boolean',
        'date_from' => 'date',
        'date_to' => 'date',
        'total_participants' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the matrix that owns the activity.
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    /**
     * Get the request type that owns the activity.
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * Get the staff member that owns the activity.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the fund type that owns the activity.
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * The locations that belong to the activity.
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'activity_location');
    }

    /**
     * The fund codes that belong to the activity.
     */
    public function fundCodes()
    {
        return $this->belongsToMany(FundCode::class, 'activity_fund_code');
    }

    /**
     * The participants that belong to the activity.
     */
    public function participants()
    {
        return $this->belongsToMany(Staff::class, 'activity_participant')
            ->withPivot('days')
            ->withTimestamps();
    }

    /**
     * Get the forward workflow for the activity.
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(ForwardWorkflow::class);
    }

    /**
     * Get the reverse workflow for the activity.
     */
    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(ReverseWorkflow::class);
    }

    /**
     * Get the service requests for the activity.
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * Get the approval trails for the activity.
     */
    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }
    
    /**
     * Get the formatted dates attribute.
     *
     * @return string
     */
    public function getFormattedDatesAttribute(): string
    {
        $startDate = $this->date_from->format('M j, Y');
        $endDate = $this->date_to->format('M j, Y');
        
        return $startDate . ' - ' . $endDate;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (empty($activity->workplan_activity_code)) {
                $activity->workplan_activity_code = $activity->generateActivityCode();
            }
        });
    }

    /**
     * Generate the next activity code.
     *
     * @return string
     */
    protected function generateActivityCode(): string
    {
        $prefix = 'AU/CDC/DHIS/QM';
        $quarter = 'Q' . $this->matrix->quarter;
        $year = $this->matrix->year;
        
        // Get the latest activity for this matrix
        $latestActivity = self::where('matrix_id', $this->matrix_id)
            ->where('workplan_activity_code', 'like', $prefix . '/' . $quarter . '/' . $year . '/%')
            ->orderBy('id', 'desc')
            ->first();
        
        // Extract the last sequence number
        $sequence = 1;
        if ($latestActivity) {
            $codeParts = explode('/', $latestActivity->workplan_activity_code);
            $lastSequence = (int) end($codeParts);
            $sequence = $lastSequence + 1;
        }
        
        // Format the sequence with leading zeros (4 digits)
        $sequencePadded = str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}/{$quarter}/{$year}/{$sequencePadded}";
    }
}
