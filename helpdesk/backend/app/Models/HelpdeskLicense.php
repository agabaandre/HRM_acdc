<?php

namespace App\Models;

use App\Services\LicenseExpiryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskLicense extends Model
{
    protected $table = 'helpdesk_licenses';

    protected $fillable = [
        'name',
        'vendor',
        'license_key',
        'seats_total',
        'seats_used',
        'purchase_date',
        'duration_months',
        'expiry_date',
        'warning_days_before',
        'cost',
        'renewal_cost',
        'status',
        'notes',
        'created_by_user_id',
    ];

    protected $appends = ['expiry'];

    protected function casts(): array
    {
        return [
            'seats_total' => 'integer',
            'seats_used' => 'integer',
            'purchase_date' => 'date',
            'duration_months' => 'integer',
            'expiry_date' => 'date',
            'warning_days_before' => 'integer',
            'cost' => 'decimal:2',
            'renewal_cost' => 'decimal:2',
            'created_by_user_id' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getExpiryAttribute(): array
    {
        return app(LicenseExpiryService::class)->snapshot(
            $this->expiry_date?->format('Y-m-d'),
            (int) ($this->warning_days_before ?: 30)
        );
    }
}
