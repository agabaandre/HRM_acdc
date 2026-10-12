<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportingRegistryStatus extends Model
{
    protected $table = 'reporting_registry_statuses';

    protected $fillable = [
        'source_id',
        'source_type',
        'started_at',
        'started_by',
        'processed_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'started_at' => 'datetime',
            'started_by' => 'integer',
            'processed_at' => 'datetime',
            'processed_by' => 'integer',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
