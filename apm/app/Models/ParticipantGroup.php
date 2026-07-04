<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParticipantGroup extends Model
{
    public const VIRTUAL_DIVISION_MEMBERS = 'division_members';

    protected $fillable = [
        'division_id',
        'name',
        'description',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'division_id' => 'integer',
            'created_by' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by', 'staff_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ParticipantGroupMember::class)->orderBy('sort_order')->orderBy('id');
    }
}
