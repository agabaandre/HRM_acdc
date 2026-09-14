<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class PortalUiTranslation extends Model
{
    protected $table = 'portal_ui_translations';

    protected $fillable = [
        'locale_code',
        'group_key',
        'item_key',
        'value',
    ];
}
