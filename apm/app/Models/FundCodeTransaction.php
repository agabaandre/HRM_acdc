<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundCodeTransaction extends Model
{
    protected $fillable = ['fund_code_id', 'amount', 'description', 'activity_id', 'matrix_id', 'channel', 'activity_budget_id', 'balance_before', 'balance_after', 'is_reversal', 'created_by'];

    public function fundCode()
    {
        return $this->belongsTo(FundCode::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function matrix()
    {
        return $this->belongsTo(Matrix::class);
    }

    public function activityBudget()
    {
        return $this->belongsTo(ActivityBudget::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function getCreatedByNameAttribute()
    {
        return $this->createdBy->name;
    }

}
