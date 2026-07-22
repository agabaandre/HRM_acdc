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
        'responsible_staff_id',
        'expiry_alert_last_sent_at',
    ];

    protected $appends = ['expiry', 'responsible_person'];

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
            'responsible_staff_id' => 'integer',
            'expiry_alert_last_sent_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array{staff_id:int,name:string,email:string}|null
     */
    public function getResponsiblePersonAttribute(): ?array
    {
        $staffId = (int) ($this->responsible_staff_id ?? 0);
        if ($staffId < 1) {
            return null;
        }

        $resolved = app(\App\Services\StaffDirectoryLookupService::class)->resolveByStaffId($staffId);
        if ($resolved === null) {
            return [
                'staff_id' => $staffId,
                'name' => 'Staff #'.$staffId,
                'email' => '',
            ];
        }

        return [
            'staff_id' => $staffId,
            'name' => $resolved['name'],
            'email' => $resolved['work_email'],
        ];
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
