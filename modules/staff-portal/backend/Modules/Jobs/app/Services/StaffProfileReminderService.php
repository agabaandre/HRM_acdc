<?php

namespace Modules\Jobs\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Staff\Services\StaffPortalAccountService;

class StaffProfileReminderService
{
    public function __construct(
        private EmailNotificationService $mail,
        private StaffPortalAccountService $accounts,
    ) {}

    /**
     * @return array{queued:int, skipped:int}
     */
    public function queueIncompleteProfileReminders(): array
    {
        if (! Schema::hasColumn('staff', 'passport_biodata_page')) {
            return ['queued' => 0, 'skipped' => 0];
        }

        $queued = 0;
        $skipped = 0;
        $bucket = (string) (int) floor(time() / 172800);
        $latest = '(SELECT staff_id, MAX(staff_contract_id) AS cid FROM staff_contracts GROUP BY staff_id)';

        $rows = DB::table('staff as s')
            ->join('user as u', 'u.auth_staff_id', '=', 's.staff_id')
            ->join(DB::raw($latest.' as latest'), 'latest.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'latest.cid')
            ->where('u.status', 1)
            ->whereIn('sc.status_id', $this->accounts->eligibleContractStatusIds())
            ->whereRaw("TRIM(COALESCE(s.work_email, '')) != ''")
            ->get([
                's.staff_id', 's.title', 's.fname', 's.lname', 's.work_email',
                's.passport_biodata_page', 's.residential_address_duty_station',
                's.number_of_dependants', 's.next_of_kin_json',
            ]);

        foreach ($rows as $row) {
            $missing = $this->missingLabels($row);
            if ($missing === []) {
                $skipped++;

                continue;
            }
            $staffId = (int) $row->staff_id;
            $entryId = md5($staffId.'-PROFILEEXT-'.$bucket);
            if ($this->mail->entryExists($entryId)) {
                $skipped++;

                continue;
            }
            $name = trim(($row->title ?? '').' '.($row->fname ?? '').' '.($row->lname ?? ''));
            $body = $this->mail->render('staff_profile_completion_reminder', [
                'name' => $name,
                'missing' => $missing,
                'profile_url' => rtrim((string) config('jobs.schedule.portal_base_url'), '/').'/profile',
            ]);
            $to = $this->mail->appendSystemInbox((string) $row->work_email);
            if ($this->mail->queue(
                'Staff Portal System',
                $to,
                $body,
                'Complete your staff profile',
                $staffId,
                date('Y-m-d'),
                date('Y-m-d'),
                $entryId,
            )) {
                $queued++;
            }
        }

        return compact('queued', 'skipped');
    }

    /**
     * @return list<string>
     */
    protected function missingLabels(object $staff): array
    {
        $missing = [];
        $passport = trim((string) ($staff->passport_biodata_page ?? ''));
        if ($passport === '') {
            $missing[] = 'Passport biodata page';
        }
        if (trim((string) ($staff->residential_address_duty_station ?? '')) === '') {
            $missing[] = 'Residential address at duty station';
        }
        if ($staff->number_of_dependants === null || $staff->number_of_dependants === '') {
            $missing[] = 'Number of dependants';
        }
        $kin = json_decode((string) ($staff->next_of_kin_json ?? '[]'), true);
        $validKin = 0;
        if (is_array($kin)) {
            foreach ($kin as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $rel = $row['relationship_id'] ?? null;
                $phone = trim((string) ($row['phone'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                if ($name !== '' && $rel && $phone !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validKin++;
                }
            }
        }
        if ($validKin < 1) {
            $missing[] = 'Next of kin';
        }

        return $missing;
    }
}
