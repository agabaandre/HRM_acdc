<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialMemo extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'staff_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'memo_number',
        'memo_date',
        'subject',
        'body',
        'division_id',
        'recipients',
        'attachment',
        'priority',
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
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'division_id' => 'integer',
            'memo_date' => 'date',
            'recipients' => 'array',
            'attachment' => 'array',
        ];
    }

    /**
     * Get the staff member that authored the special memo.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the division associated with the special memo.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the forward workflow for this special memo.
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    /**
     * Get the reverse workflow for this special memo.
     */
    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'reverse_workflow_id');
    }
    
    /**
     * Generate a unique memo number.
     * 
     * @return string
     */
    public static function generateMemoNumber(): string
    {
        $prefix = 'SPM';
        $year = date('Y');
        $month = date('m');
        
        // Get the latest memo number for this month
        $latestMemo = self::where('memo_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = 1;
        
        if ($latestMemo) {
            // Extract the number part from the memo number
            $parts = explode('-', $latestMemo->memo_number);
            $lastNumber = intval(substr(end($parts), 6)); // Extract the sequence number
            $nextNumber = $lastNumber + 1;
        }
        
        // Format the memo number: SPM-YYYYMM-0001
        return sprintf("%s-%s%s-%04d", $prefix, $year, $month, $nextNumber);
    }
}
