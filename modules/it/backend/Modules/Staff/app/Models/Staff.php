<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    protected $table = 'staff';

    protected $primaryKey = 'staff_id';

    public $timestamps = false;

    protected $guarded = [];

    public function activeContract(): HasOne
    {
        return $this->hasOne(StaffContract::class, 'staff_id', 'staff_id')
            ->orderByDesc('staff_contract_id');
    }

    public function fullName(): string
    {
        return trim("{$this->fname} {$this->lname}");
    }
}
