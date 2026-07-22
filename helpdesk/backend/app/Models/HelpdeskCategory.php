<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskCategory extends Model
{
    protected $table = 'helpdesk_categories';

    protected $fillable = [
        'business_unit_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
        'default_priority',
        'ai_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function businessUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HelpdeskBusinessUnit::class, 'business_unit_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'category_id');
    }

    public function slaRules(): HasMany
    {
        return $this->hasMany(HelpdeskSlaRule::class, 'category_id');
    }

    public function kbArticles(): HasMany
    {
        return $this->hasMany(HelpdeskKbArticle::class, 'category_id');
    }
}
