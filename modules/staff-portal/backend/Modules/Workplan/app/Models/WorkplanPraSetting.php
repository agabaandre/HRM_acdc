<?php

namespace Modules\Workplan\Models;

use Illuminate\Database\Eloquent\Model;

class WorkplanPraSetting extends Model
{
    protected $table = 'workplan_pra_settings';

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
        ];
    }
}
