<?php

namespace Modules\Lookup\Models;

use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    protected $table = 'contract_types';

    protected $primaryKey = 'contract_type_id';

    public $timestamps = false;

    protected $fillable = ['contract_type'];
}
