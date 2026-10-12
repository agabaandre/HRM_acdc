<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityReportRelated extends Model
{
    protected $table = 'activity_report_related';

    protected $fillable = [
        'activity_report_id',
        'related_source_id',
        'related_source_type',
    ];

    protected function casts(): array
    {
        return [
            'activity_report_id' => 'integer',
            'related_source_id' => 'integer',
        ];
    }

    public function activityReport(): BelongsTo
    {
        return $this->belongsTo(ActivityReport::class);
    }

    public function relatedSource(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_source_type', 'related_source_id');
    }
}
