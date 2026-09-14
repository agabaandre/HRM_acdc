<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskWorkModeTrail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Self-service endpoint for agents/staff to declare where they're working from
 * right now (`remote` or `onsite`). Helps requesters see assignee availability
 * context on the ticket detail, lights up the agent-workload tiles on the TV
 * dashboard, and gives admins a live read on team distribution.
 *
 * - PUT /api/v1/me/work-mode  body: { work_mode: 'remote' | 'onsite' | null }
 *
 * Only staff roles (agent / supervisor / admin / auditor) may set a mode.
 * Sending `null` clears the previously declared mode.
 */
class MeWorkModeController extends Controller
{
    public function update(Request $request): MeResource
    {
        $user = $request->user();
        $profile = $user->helpdeskProfile;

        if (! $profile || ! $profile->isStaffRole()) {
            throw ValidationException::withMessages([
                'work_mode' => 'Only helpdesk staff can declare a work mode.',
            ]);
        }

        $validated = $request->validate([
            'work_mode' => [
                'nullable',
                'string',
                Rule::in(HelpdeskProfile::VALID_WORK_MODES),
            ],
        ]);

        $mode = $validated['work_mode'] ?? null;
        $profile->work_mode = $mode;
        $profile->work_mode_updated_at = now();
        $profile->save();
        $this->recordWorkModeTrail($profile, $mode);

        return new MeResource($user->fresh()->load('helpdeskProfile'));
    }

    private function recordWorkModeTrail(HelpdeskProfile $profile, ?string $mode): void
    {
        // Audit trail tracks days explicitly marked as remote/onsite.
        if (! in_array($mode, HelpdeskProfile::VALID_WORK_MODES, true)) {
            return;
        }

        $now = now();
        $row = HelpdeskWorkModeTrail::query()->firstOrNew([
            'helpdesk_profile_id' => $profile->id,
            'work_date' => $now->toDateString(),
        ]);

        if (! $row->exists) {
            $row->user_id = $profile->user_id;
            $row->staff_id = $profile->staff_id;
            $row->first_work_mode = $mode;
            $row->last_work_mode = $mode;
            $row->switch_count = 1;
            $row->first_set_at = $now;
            $row->last_set_at = $now;
            $row->save();

            return;
        }

        if ($row->last_work_mode !== $mode) {
            $row->switch_count = ((int) $row->switch_count) + 1;
        }
        $row->last_work_mode = $mode;
        $row->last_set_at = $now;
        $row->save();
    }
}
