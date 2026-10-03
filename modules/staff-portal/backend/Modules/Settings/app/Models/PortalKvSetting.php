<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalKvSetting extends Model
{
    protected $table = 'portal_kv_settings';

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['setting_key', 'setting_value', 'group'];
}
