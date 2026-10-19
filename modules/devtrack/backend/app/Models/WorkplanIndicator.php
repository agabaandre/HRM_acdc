<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkplanIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'directorate',
        'programme',
        'year',
        'target',
        'unit',
        'baseline',
        'q1',
        'q2',
        'q3',
        'q4',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'directorate' => $this->directorate,
            'programme' => $this->programme,
            'year' => $this->year,
            'target' => $this->target,
            'unit' => $this->unit,
            'baseline' => $this->baseline,
            'q1' => $this->q1,
            'q2' => $this->q2,
            'q3' => $this->q3,
            'q4' => $this->q4,
            'is_active' => $this->is_active,
            'projects_count' => $this->projects_count ?? $this->projects()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
