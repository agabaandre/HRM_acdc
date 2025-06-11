<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialMemo extends Model
{
    use HasFactory;

    protected $table = 'special_memos'; // Uses the 'activities' table

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'activity_id',
        'staff_id',
        'division_id',
        'date_from',
        'date_to',
        'location_id',
        'total_participants',
        'internal_participants',
        'total_external_participants',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'justification',
        'is_special_memo',
        'budget',
        'attachment',
        'status',
    ];

    /**
     * Casts for attribute types.
     */
    protected $casts = [
        'staff_id' => 'integer',
        'division_id' => 'integer',
        'request_type_id' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'location_id' => 'array',
        'internal_participants' => 'array',
        'budget' => 'array',
        'attachment' => 'array',
        'is_special_memo' => 'boolean',
    ];

    /**
     * Staff member who submitted the special memo.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
    
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
    /**
     * Request type relationship.
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }
}
