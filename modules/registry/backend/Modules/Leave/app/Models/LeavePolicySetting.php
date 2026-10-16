<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePolicySetting extends Model
{
    protected $table = 'leave_policy_settings';

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
