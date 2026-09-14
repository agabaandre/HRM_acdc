<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskSupportGroup extends Model
{
    protected $table = 'helpdesk_support_groups';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'helpdesk_support_group_members', 'group_id', 'user_id')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(HelpdeskCategory::class, 'helpdesk_support_group_categories', 'group_id', 'category_id')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'assigned_group_id');
    }
}
