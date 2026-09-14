<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Model;

class StaffContract extends Model
{
    protected $table = 'staff_contracts';

    protected $primaryKey = 'staff_contract_id';

    public $timestamps = false;

    protected $guarded = [];
}
