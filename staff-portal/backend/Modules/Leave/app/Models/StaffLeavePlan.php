<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffLeavePlan extends Model
{
    public const STATUS_DRAFT = 1;

    public const STATUS_SUBMITTED = 0;

    protected $table = 'staff_leave_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'staff_id' => 'integer',
            'plan_year' => 'integer',
            'draft_status' => 'integer',
            'submitted_at' => 'datetime',
            'submitted_by_user_id' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StaffLeavePlanEntry::class, 'leave_plan_id')->orderBy('sort_order')->orderBy('start_date');
    }

    public function isDraft(): bool
    {
        return (int) $this->draft_status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return (int) $this->draft_status === self::STATUS_SUBMITTED;
    }
}
