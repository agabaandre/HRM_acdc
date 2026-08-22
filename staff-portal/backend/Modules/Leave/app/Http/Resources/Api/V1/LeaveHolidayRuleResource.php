<?php

namespace Modules\Leave\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Leave\Models\LeaveHolidayRule;

/** @mixin LeaveHolidayRule */
class LeaveHolidayRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => (string) $this->name,
            'recurrence' => (string) $this->recurrence,
            'month' => $this->month,
            'day' => $this->day,
            'once_date' => $this->once_date?->toDateString(),
            'scope' => (string) $this->scope,
            'country_iso2' => $this->country_iso2,
            'duty_station_id' => $this->duty_station_id,
            'grants_compensatory_if_weekend' => (bool) $this->grants_compensatory_if_weekend,
            'compensatory_duty_station_ids' => $this->compensatory_duty_station_ids,
            'source' => (string) $this->source,
            'openholidays_id' => $this->openholidays_id,
            'is_movable' => (bool) $this->is_movable,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
