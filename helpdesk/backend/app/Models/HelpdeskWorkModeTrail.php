<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskWorkModeTrail extends Model
{
    protected $table = 'helpdesk_work_mode_trails';

    protected $fillable = [
        'helpdesk_profile_id',
        'user_id',
        'staff_id',
        'work_date',
        'first_work_mode',
        'last_work_mode',
        'switch_count',
        'first_set_at',
        'last_set_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'first_set_at' => 'datetime',
            'last_set_at' => 'datetime',
            'switch_count' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(HelpdeskProfile::class, 'helpdesk_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
