<?php

namespace Modules\Lookup\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Staff / contract contact status (legacy table: status).
 */
class ContactStatus extends Model
{
    protected $table = 'status';

    protected $primaryKey = 'status_id';

    public $timestamps = false;

    protected $fillable = ['status'];
}
