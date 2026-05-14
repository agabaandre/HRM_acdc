<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->helpdeskProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => StaffPhotoUrl::forUser($this->resource),
            'profile' => $p ? [
                'staff_id' => $p->staff_id,
                'sap_no' => $p->sap_no ? trim((string) $p->sap_no) : null,
                'role' => $p->role,
                'directorate_id' => $p->directorate_id,
                'division_id' => $p->division_id,
                'duty_station' => $p->duty_station ? trim((string) $p->duty_station) : null,
            ] : null,
        ];
    }
}
