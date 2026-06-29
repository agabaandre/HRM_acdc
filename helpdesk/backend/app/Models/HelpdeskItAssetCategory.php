<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskItAssetCategory extends Model
{
    protected $table = 'helpdesk_it_asset_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'default_useful_life_years',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_useful_life_years' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(HelpdeskItAsset::class, 'category_id');
    }
}
