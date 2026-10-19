<?php

namespace App\Models;

use App\Enums\SprintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'committed_points',
        'completed_points',
    ];

    protected function casts(): array
    {
        return [
            'status' => SprintStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'committed_points' => 'integer',
            'completed_points' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $total = $this->work_items_count ?? $this->workItems()->count();
        $done = $this->work_items_done_count
            ?? $this->workItems()->where('status', 'completed')->count();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'name' => $this->name,
            'goal' => $this->goal,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'committed_points' => $this->committed_points,
            'completed_points' => $this->completed_points,
            'work_items_count' => $total,
            'work_items_done' => $done,
            'progress_percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
