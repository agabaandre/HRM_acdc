<?php

namespace Modules\Jobs\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class StaffBirthdayService
{
    public function __construct(private EmailNotificationService $mail) {}

    /**
     * @return array{queued:int, skipped:int}
     */
    public function queueTodaysBirthdays(): array
    {
        $today = new DateTimeImmutable('today');
        $md = $today->format('m-d');
        $queued = 0;
        $skipped = 0;

        $latest = '(SELECT staff_id, MAX(staff_contract_id) AS cid FROM staff_contracts GROUP BY staff_id)';
        $rows = DB::table('staff as s')
            ->join(DB::raw($latest.' as latest'), 'latest.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'latest.cid')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->whereRaw("DATE_FORMAT(s.date_of_birth, '%m-%d') = ?", [$md])
            ->get(['s.staff_id', 's.title', 's.fname', 's.lname', 's.work_email', 's.date_of_birth']);

        foreach ($rows as $row) {
            $dobRaw = trim((string) ($row->date_of_birth ?? ''));
            if ($dobRaw === '' || strlen($dobRaw) < 10) {
                $skipped++;

                continue;
            }
            try {
                $dob = new DateTimeImmutable(substr($dobRaw, 0, 10));
            } catch (\Throwable) {
                $skipped++;

                continue;
            }
            $age = (int) $today->diff($dob)->y;
            if ($age < 18 || $age > 100) {
                $skipped++;

                continue;
            }
            $email = trim((string) ($row->work_email ?? ''));
            if ($email === '' || ! str_contains($email, '@')) {
                $skipped++;

                continue;
            }

            $staffId = (int) $row->staff_id;
            $entryId = md5($staffId.'-BD-'.$today->format('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                $skipped++;

                continue;
            }

            DB::table('email_notifications')
                ->where('staff_id', $staffId)
                ->where('subject', 'AFRICA CDC Birthday Greetings')
                ->where('end_date', $today->format('Y-m-d'))
                ->where('status', '!=', 1)
                ->delete();

            $name = trim(($row->title ?? '').' '.($row->fname ?? '').' '.($row->lname ?? ''));
            $body = $this->mail->render('staff_bd', ['name' => $name]);
            $to = $this->mail->appendSystemInbox($email);
            if ($this->mail->queue(
                'Staff Portal System',
                $to,
                $body,
                'AFRICA CDC Birthday Greetings',
                $staffId,
                $today->format('Y-m-d'),
                $today->format('Y-m-d'),
                $entryId,
            )) {
                $queued++;
            }
        }

        return compact('queued', 'skipped');
    }
}
