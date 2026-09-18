<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveHolidayRule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'recurrence',
        'month',
        'day',
        'once_date',
        'scope',
        'country_iso2',
        'duty_station_id',
        'grants_compensatory_if_weekend',
        'compensatory_duty_station_ids',
        'source',
        'openholidays_id',
        'is_movable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'once_date' => 'date',
            'duty_station_id' => 'integer',
            'grants_compensatory_if_weekend' => 'boolean',
            'compensatory_duty_station_ids' => 'array',
            'is_movable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toOccurrenceArray(): array
    {
        return [
            'recurrence' => (string) $this->recurrence,
            'month' => $this->month,
            'day' => $this->day,
            'once_date' => $this->once_date?->toDateString(),
        ];
    }
}
