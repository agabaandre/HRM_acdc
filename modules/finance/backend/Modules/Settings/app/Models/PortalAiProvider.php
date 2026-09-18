<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalAiProvider extends Model
{
    protected $table = 'portal_ai_providers';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'driver',
        'api_endpoint',
        'model',
        'api_key',
        'description',
        'is_default',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
