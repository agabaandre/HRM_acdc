<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $table = 'payroll_settings';

    protected $fillable = [
        'default_currency',
        'enabled_currencies',
        'period_close_day',
        'jurisdiction_default',
    ];

    protected $casts = [
        'enabled_currencies' => 'array',
        'period_close_day' => 'integer',
    ];
}