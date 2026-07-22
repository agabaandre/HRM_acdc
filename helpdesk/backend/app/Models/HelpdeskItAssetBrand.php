<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskItAssetBrand extends Model
{
    protected $table = 'helpdesk_it_asset_brands';

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(HelpdeskItAsset::class, 'brand_id');
    }
}
