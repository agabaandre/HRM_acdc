<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskSlaRule extends Model
{
    protected $table = 'helpdesk_sla_rules';

    protected $fillable = [
        'name',
        'category_id',
        'response_minutes',
        'resolution_minutes',
        'business_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }
}
