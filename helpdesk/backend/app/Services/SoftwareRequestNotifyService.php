<?php

namespace App\Services;

use App\Mail\SoftwareRequestSubmittedMail;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskSoftwareRequest;
use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SoftwareRequestNotifyService
{
    public function notifyNewSubmission(HelpdeskSoftwareRequest $request): void
    {
        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost/staff/helpdesk'), '/');
        $url = $frontend.'/tools/software-requests';

        $emails = $this->recipientEmails();
        if ($emails === []) {
            return;
        }

        foreach ($emails as $email => $name) {
            try {
                Mail::to($email)->send(new SoftwareRequestSubmittedMail($request, $name, $url));
            } catch (\Throwable $e) {
                Log::warning('helpdesk.software_request_notify_failed', [
                    'software_request_id' => $request->id,
                    'email' => $email,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, string> email => name
     */
    public function recipientEmails(): array
    {
        $groupIds = HelpdeskSetting::softwareRequestNotifyGroupIds();
        $groups = collect();

        if ($groupIds !== []) {
            $groups = HelpdeskSupportGroup::query()
                ->whereIn('id', $groupIds)
                ->where('is_active', true)
                ->with('members:id,name,email')
                ->get();
        } else {
            $fallback = HelpdeskSupportGroup::query()
                ->where('slug', 'software-development')
                ->where('is_active', true)
                ->with('members:id,name,email')
                ->first();
            if ($fallback) {
                $groups = collect([$fallback]);
            }
        }

        $out = [];
        foreach ($groups as $group) {
            /** @var User $member */
            foreach ($group->members as $member) {
                $email = trim((string) $member->email);
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $out[$email] = (string) $member->name;
            }
        }

        return $out;
    }
}
