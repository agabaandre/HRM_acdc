<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityBudget extends Model
{
    
    protected $fillable = [
        'fund_type_id',
        'activity_id',
        'matrix_id',
        'fund_code',
        'cost',
        'description',
        'unit_cost',
        'units',
        'days',
        'total',
    ];

    public function fund_type(){
        return $this->belongsTo(FundType::class);
    }

    public function activity(){
        return $this->belongsTo(Activity::class);
    }

    public function matrix(){
        return $this->belongsTo(Matrix::class);
    }
}
