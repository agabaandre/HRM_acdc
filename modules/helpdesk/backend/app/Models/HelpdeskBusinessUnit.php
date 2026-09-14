<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskBusinessUnit extends Model
{
    protected $table = 'helpdesk_business_units';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'allows_anonymous',
        'allows_asset_link_on_resolve',
        'allows_information_system_link_on_resolve',
        'support_mailbox',
        'email_intake_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_anonymous' => 'boolean',
            'allows_asset_link_on_resolve' => 'boolean',
            'allows_information_system_link_on_resolve' => 'boolean',
            'email_intake_enabled' => 'boolean',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(HelpdeskCategory::class, 'business_unit_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'business_unit_id');
    }
}
