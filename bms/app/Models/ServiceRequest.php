<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'request_number',
        'request_date',
        'staff_id',
        'activity_id',
        'workflow_id',
        'reverse_workflow_id',
        'division_id',
        'service_title',
        'description',
        'justification',
        'required_by_date',
        'location',
        'estimated_cost',
        'priority',
        'service_type',
        'specifications',
        'attachments',
        'status',
        'remarks',
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
            'staff_id' => 'integer',
            'activity_id' => 'integer',
            'workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'division_id' => 'integer',
            'request_date' => 'date',
            'required_by_date' => 'date',
            'estimated_cost' => 'decimal:2',
            'specifications' => 'array',
            'attachments' => 'array',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }
    
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
    
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
    
    /**
     * Generate a unique request number.
     * 
     * @return string
     */
    public static function generateRequestNumber(): string
    {
        $prefix = 'SRV';
        $year = date('Y');
        $month = date('m');
        
        // Get the latest request number for this month
        $latestRequest = self::where('request_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = 1;
        
        if ($latestRequest) {
            // Extract the number part from the request number
            $parts = explode('-', $latestRequest->request_number);
            $lastNumber = intval(substr(end($parts), 6)); // Extract the sequence number
            $nextNumber = $lastNumber + 1;
        }
        
        // Format the request number: SRV-YYYYMM-0001
        return sprintf("%s-%s%s-%04d", $prefix, $year, $month, $nextNumber);
    }
}
