<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskInformationSystemModule extends Model
{
    protected $table = 'helpdesk_information_system_modules';

    protected $fillable = [
        'information_system_id',
        'name',
        'description',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'information_system_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function informationSystem(): BelongsTo
    {
        return $this->belongsTo(HelpdeskInformationSystem::class, 'information_system_id');
    }
}
