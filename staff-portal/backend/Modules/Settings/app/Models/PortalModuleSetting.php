<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalModuleSetting extends Model
{
    protected $table = 'portal_module_settings';

    protected $primaryKey = 'module_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'module_key',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
