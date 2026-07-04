<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantGroupMember extends Model
{
    protected $fillable = [
        'participant_group_id',
        'staff_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'participant_group_id' => 'integer',
            'staff_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ParticipantGroup::class, 'participant_group_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
