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

    public function staff(){
        return $this->belongsTo(Staff::class,"staff_id","staff_id");
    }

    public function approver_role(){
        return $this->belongsTo(WorkflowDefinition::class,"approval_order","approval_order");
    }
}
