<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalEmailProvider extends Model
{
    protected $table = 'portal_email_providers';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'driver',
        'config',
        'from_address',
        'from_name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        // secrets are redacted in API responses, not hidden entirely
    ];
}
