<?php

namespace App\Models;

use App\Enums\ReleaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'version',
        'name',
        'description',
        'status',
        'target_date',
        'released_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'target_date' => 'date',
            'released_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function enhancements(): BelongsToMany
    {
        return $this->belongsToMany(Enhancement::class)->withTimestamps();
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'target_date' => $this->target_date?->toDateString(),
            'released_at' => $this->released_at?->toDateString(),
            'enhancements' => $this->relationLoaded('enhancements')
                ? $this->enhancements->map(fn (Enhancement $e) => [
                    'id' => $e->id,
                    'reference' => $e->reference,
                    'name' => $e->name,
                ])->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
