<?php

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    protected $table = 'user_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $guarded = [];
}
