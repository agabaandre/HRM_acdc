<?php

namespace App\Models;

use App\Services\ItAssetValuationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskItAsset extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_DEPLOYED = 'deployed';

    public const STATUS_REPAIR = 'repair';

    public const STATUS_RETIRED = 'retired';

    protected $table = 'helpdesk_it_assets';

    protected $fillable = [
        'asset_tag',
        'category_id',
        'name',
        'brand',
        'brand_id',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
        'assigned_staff_id',
        'assigned_name',
        'status',
        'location',
        'notes',
        'meta',
        'created_by_user_id',
    ];

    protected $appends = ['valuation'];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'brand_id' => 'integer',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'useful_life_years' => 'integer',
            'assigned_staff_id' => 'integer',
            'meta' => 'array',
            'created_by_user_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskItAssetCategory::class, 'category_id');
    }

    public function brandRelation(): BelongsTo
    {
        return $this->belongsTo(HelpdeskItAssetBrand::class, 'brand_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getValuationAttribute(): array
    {
        $life = (int) ($this->useful_life_years ?: $this->category?->default_useful_life_years ?: 3);

        return app(ItAssetValuationService::class)->snapshot(
            $this->purchase_date?->format('Y-m-d'),
            (float) $this->purchase_cost,
            (float) $this->salvage_value,
            $life
        );
    }

    public function resolvedUsefulLifeYears(): int
    {
        return (int) ($this->useful_life_years ?: $this->category?->default_useful_life_years ?: 3);
    }
}
