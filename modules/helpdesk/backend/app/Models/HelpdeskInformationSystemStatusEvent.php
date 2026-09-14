<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskInformationSystemStatusEvent extends Model
{
    protected $table = 'helpdesk_information_system_status_events';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'changed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'changed_by_user_id' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
