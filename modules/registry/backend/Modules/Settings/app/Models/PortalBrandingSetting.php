<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalBrandingSetting extends Model
{
    protected $table = 'portal_branding_settings';

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
