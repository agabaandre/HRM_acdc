<?php

namespace Modules\Jobs\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Staff\Services\StaffPortalAccountService;

class ContractReminderService
{
    public function __construct(
        private EmailNotificationService $mail,
        private StaffPortalAccountService $accounts,
    ) {}

    /**
     * @return array{due:int, expired:int, restored:int}
     */
    public function markDueContracts(): array
    {
        $today = new DateTimeImmutable('today');
        $due = 0;
        $expired = 0;
        $restored = 0;

        $rows = DB::table('staff_contracts')
            ->whereIn('status_id', [1, 2])
            ->get(['staff_contract_id', 'end_date', 'staff_id']);

        foreach ($rows as $row) {
            $end = (string) ($row->end_date ?? '');
            if ($end === '') {
                continue;
            }
            try {
                $endDate = new DateTimeImmutable(substr($end, 0, 10));
            } catch (\Throwable) {
                continue;
            }

            $dateDiff = (int) $today->diff($endDate)->format('%r%a');
            $staffId = (int) $row->staff_id;
            $staff = DB::table('staff')->where('staff_id', $staffId)->first();
            $name = trim(($staff->fname ?? '').' '.($staff->lname ?? '')) ?: ('Staff #'.$staffId);
            $workEmail = trim((string) ($staff->work_email ?? ''));
            $supervisorId = (int) (DB::table('staff_contracts')
                ->where('staff_id', $staffId)
                ->orderByDesc('staff_contract_id')
                ->value('first_supervisor') ?? 0);
            $supervisorEmail = $supervisorId > 0
                ? trim((string) (DB::table('staff')->where('staff_id', $supervisorId)->value('work_email') ?? ''))
                : '';

            if ($dateDiff > 0 && $dateDiff <= 90) {
                $to = $this->mail->appendSystemInbox(implode(';', array_filter([$workEmail, $supervisorEmail])));
                $body = $this->mail->render('due_contract', ['name' => $name, 'date2' => $endDate->format('Y-m-d')]);
                $this->mail->queue(
                    'system',
                    $to,
                    $body,
                    'Contract Due for Renewal Notice',
                    $staffId,
                    $endDate->format('Y-m-d'),
                    now()->toDateTimeString(),
                    md5($staffId.'-DU-'.date('Y-m-d')),
                );
                DB::table('staff_contracts')->where('staff_contract_id', $row->staff_contract_id)->update(['status_id' => 2]);
                DB::table('staff')->where('staff_id', $staffId)->update(['flag' => 1]);
                $this->accounts->syncForStaff($staffId);
                $due++;
            } elseif ($dateDiff <= 0) {
                $copied = trim((string) config('jobs.schedule.contracts_status_copied_emails'));
                $to = $this->mail->appendSystemInbox(implode(';', array_filter([$workEmail, $supervisorEmail, $copied])));
                $body = $this->mail->render('expired_contract', ['name' => $name, 'date2' => $endDate->format('Y-m-d')]);
                $this->mail->queue(
                    'system',
                    $to,
                    $body,
                    'Expired Contract Notice',
                    $staffId,
                    $endDate->format('Y-m-d'),
                    now()->toDateTimeString(),
                    md5($staffId.'-EX-'.date('Y-m-d')),
                );
                DB::table('staff_contracts')->where('staff_contract_id', $row->staff_contract_id)->update(['status_id' => 3]);
                DB::table('staff')->where('staff_id', $staffId)->update(['flag' => 1]);
                $this->accounts->syncForStaff($staffId);
                $expired++;
            } elseif ($dateDiff > 90) {
                DB::table('staff_contracts')->where('staff_contract_id', $row->staff_contract_id)->update(['status_id' => 1]);
                DB::table('staff')->where('staff_id', $staffId)->update(['flag' => 0]);
                $this->accounts->syncForStaff($staffId);
                $restored++;
            }
        }

        return compact('due', 'expired', 'restored');
    }

    /**
     * Clear due/expired notifications for staff whose latest contract is healthy again.
     *
     * @return array{cleared_notifications:int, cleared_flags:int}
     */
    public function auditExtendedContracts(): array
    {
        $clearedNotifications = 0;
        $clearedFlags = 0;

        $latest = DB::table('staff_contracts as sc')
            ->join(DB::raw('(SELECT staff_id, MAX(staff_contract_id) AS cid FROM staff_contracts GROUP BY staff_id) latest'), function ($join) {
                $join->on('latest.cid', '=', 'sc.staff_contract_id');
            })
            ->whereIn('sc.status_id', [1, 7])
            ->get(['sc.staff_id']);

        foreach ($latest as $row) {
            $staffId = (int) $row->staff_id;
            $deleted = DB::table('email_notifications')
                ->where('staff_id', $staffId)
                ->whereIn('subject', [
                    'Contract Due for Renewal Notice',
                    'Expired Contract Notice',
                ])
                ->delete();
            $clearedNotifications += $deleted;
            $clearedFlags += DB::table('staff')->where('staff_id', $staffId)->where('flag', 1)->update(['flag' => 0]);
        }

        return [
            'cleared_notifications' => $clearedNotifications,
            'cleared_flags' => $clearedFlags,
        ];
    }
}
