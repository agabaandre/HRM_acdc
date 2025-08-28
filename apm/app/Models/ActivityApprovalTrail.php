<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityApprovalTrail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'matrix_id',
        'activity_id',
        'staff_id',
        'oic_staff_id',
        'action',
        'remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'matrix_id' => 'integer',
        'activity_id' => 'integer',
        'staff_id' => 'integer',
        'oic_staff_id' => 'integer',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"staff_id","staff_id");
    }

    public function inchargeStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"oic_staff_id","staff_id");
    }
}
