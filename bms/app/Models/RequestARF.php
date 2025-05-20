<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestARF extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'request_arfs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'staff_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'arf_number',
        'request_date',
        'division_id',
        'location_id',
        'activity_title',
        'purpose',
        'start_date',
        'end_date',
        'requested_amount',
        'accounting_code',
        'budget_breakdown',
        'attachment',
        'status',
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
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'division_id' => 'integer',
            'request_date' => 'date',
            'location_id' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'requested_amount' => 'decimal:2',
            'budget_breakdown' => 'array',
            'attachment' => 'array',
        ];
    }

    /**
     * Get the staff member associated with the ARF request.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the division associated with the ARF request.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the forward workflow for this ARF request.
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    /**
     * Get the reverse workflow for this ARF request.
     */
    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }
    
    /**
     * Generate a unique ARF number.
     * 
     * @return string
     */
    public static function generateARFNumber(): string
    {
        $prefix = 'ARF';
        $year = date('Y');
        $month = date('m');
        
        // Get the latest ARF number for this month
        $latestARF = self::where('arf_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = 1;
        
        if ($latestARF) {
            // Extract the number part from the ARF number
            $parts = explode('-', $latestARF->arf_number);
            $lastNumber = intval(substr(end($parts), 6)); // Extract the sequence number
            $nextNumber = $lastNumber + 1;
        }
        
        // Format the ARF number: ARF-YYYYMM-0001
        return sprintf("%s-%s%s-%04d", $prefix, $year, $month, $nextNumber);
    }
}
