<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaleMemoArchive extends Model
{
    protected $fillable = [
        'memo_type',
        'memo_id',
        'document_number',
        'title',
        'staff_id',
        'responsible_person_id',
        'budget_total',
        'previous_status',
        'memo_updated_at',
        'archived_at',
        'trigger',
        'archived_by_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'budget_total' => 'float',
            'memo_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->memo_type) {
            'activity' => 'Activity',
            'single_memo' => 'Single memo',
            'special_memo' => 'Special memo',
            'non_travel_memo' => 'Non-travel memo',
            'change_request' => 'Change request',
            default => ucfirst(str_replace('_', ' ', (string) $this->memo_type)),
        };
    }
}
