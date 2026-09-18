<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'payroll_audit_logs';

    protected $fillable = [
        'actor_user_id', 'action', 'entity_type', 'entity_id', 'before', 'after', 'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}