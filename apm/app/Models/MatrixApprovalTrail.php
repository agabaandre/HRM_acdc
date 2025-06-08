<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatrixApprovalTrail extends Model
{
    //

    protected $fillable = [
        'matrix_id',
        'staff_id',
        'oic_staff_id',
        'action',
        'remarks',
    ];
}
