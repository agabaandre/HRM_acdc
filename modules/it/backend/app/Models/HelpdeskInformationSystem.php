<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskInformationSystem extends Model
{
    protected $table = 'helpdesk_information_systems';

    protected $fillable = [
        'name',
        'description',
        'status',
        'host',
        'host_name',
        'ip',
        'domain',
        'os',
        'version',
        'last_update_on',
        'division_id',
        'focal_staff_id',
        'focal_name_raw',
        'mis_focal_staff_id',
        'mis_focal_name_raw',
        'system_profile_url',
        'user_manual_users_url',
        'user_manual_managers_url',
        'user_manual_technical_url',
        'faqs_url',
        'sops_url',
        'total_users',
        'estimated_annual_hosting_cost',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_update_on' => 'date',
            'division_id' => 'integer',
            'focal_staff_id' => 'integer',
            'mis_focal_staff_id' => 'integer',
            'total_users' => 'integer',
            'estimated_annual_hosting_cost' => 'decimal:2',
            'created_by_user_id' => 'integer',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(HelpdeskInformationSystemModule::class, 'information_system_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(
            HelpdeskInformationSystemLanguage::class,
            'helpdesk_information_system_language',
            'information_system_id',
            'language_id',
        )->orderBy('name');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
