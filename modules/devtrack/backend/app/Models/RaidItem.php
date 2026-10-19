<?php

namespace App\Models;

use App\Enums\RaidStatus;
use App\Enums\RaidType;
use App\Enums\UrgencyLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaidItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'type',
        'title',
        'description',
        'severity',
        'status',
        'owner_id',
        'due_date',
        'mitigation',
    ];

    protected function casts(): array
    {
        return [
            'type' => RaidType::class,
            'status' => RaidStatus::class,
            'severity' => UrgencyLevel::class,
            'due_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity?->value,
            'severity_label' => $this->severity?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null,
            'owner_id' => $this->owner_id,
            'due_date' => $this->due_date?->toDateString(),
            'mitigation' => $this->mitigation,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
