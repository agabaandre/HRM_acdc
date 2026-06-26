<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('contract_days_until_end')) {
    /**
     * Signed day count from today to contract end (positive = future).
     */
    function contract_days_until_end($endDate): int
    {
        if ($endDate === null || $endDate === '' || $endDate === '0000-00-00') {
            return 0;
        }

        $today = new DateTime('today');
        $end = new DateTime((string) $endDate);

        return (int) $today->diff($end)->format('%r%a');
    }
}

if (!function_exists('contract_status_id_from_end_date')) {
    /**
     * Mirror jobs/jobs/mark_due_contracts: 1=Active, 2=Due (≤90 days), 3=Expired.
     */
    function contract_status_id_from_end_date($endDate): int
    {
        $days = contract_days_until_end($endDate);
        if ($days <= 0) {
            return 3;
        }
        if ($days <= 90) {
            return 2;
        }

        return 1;
    }
}

if (!function_exists('contract_reminder_subjects')) {
    /**
     * @return list<string>
     */
    function contract_reminder_subjects(): array
    {
        return [
            'Contract Due for Renewal Notice',
            'Expired Contract Notice',
        ];
    }
}

if (!function_exists('clear_contract_reminder_notifications')) {
    function clear_contract_reminder_notifications(int $staffId): void
    {
        $ci = &get_instance();
        $ci->db->where('staff_id', $staffId);
        $ci->db->where_in('subject', contract_reminder_subjects());
        $ci->db->delete('email_notifications');
    }
}

if (!function_exists('sync_staff_contract_status_after_save')) {
    /**
     * Recalculate date-driven contract status and clear stale inbox reminders after save.
     */
    function sync_staff_contract_status_after_save(int $contractId, int $staffId): void
    {
        $ci = &get_instance();
        $row = $ci->db->get_where('staff_contracts', ['staff_contract_id' => $contractId])->row();
        if (!$row || (int) $row->staff_id !== $staffId) {
            return;
        }

        $currentStatus = (int) $row->status_id;
        $computedStatus = contract_status_id_from_end_date($row->end_date);

        if (in_array($currentStatus, [1, 2, 3], true) && $currentStatus !== $computedStatus) {
            $ci->db->where('staff_contract_id', $contractId)->update('staff_contracts', [
                'status_id' => $computedStatus,
            ]);
            $currentStatus = $computedStatus;
        }

        $latestId = (int) $ci->db
            ->select('staff_contract_id')
            ->from('staff_contracts')
            ->where('staff_id', $staffId)
            ->order_by('staff_contract_id', 'DESC')
            ->limit(1)
            ->get()
            ->row('staff_contract_id');

        if ($latestId === $contractId) {
            $flag = in_array($currentStatus, [2, 3], true) ? 1 : 0;
            $ci->db->where('staff_id', $staffId)->update('staff', ['flag' => $flag]);
        }

        if ($computedStatus === 1) {
            clear_contract_reminder_notifications($staffId);
        }
    }
}

if (!function_exists('audit_extended_contracts')) {
    /**
     * Repair contracts extended after reminders were queued (stale Due/Expired status or flag).
     *
     * @return array{fixed_contracts: int, cleared_flags: int, cleared_notifications: int}
     */
    function audit_extended_contracts(): array
    {
        $ci = &get_instance();
        $threshold = (new DateTime('today'))->modify('+90 days')->format('Y-m-d');

        $rows = $ci->db->query(
            'SELECT staff_contract_id, staff_id FROM staff_contracts
             WHERE (status_id = 2 AND end_date > ?)
                OR (status_id = 3 AND end_date > CURDATE())',
            [$threshold]
        )->result_array();

        $fixedContracts = 0;
        foreach ($rows as $row) {
            sync_staff_contract_status_after_save((int) $row['staff_contract_id'], (int) $row['staff_id']);
            $fixedContracts++;
        }

        $clearedFlags = 0;
        $flaggedStaff = $ci->db->query(
            'SELECT s.staff_id
             FROM staff s
             INNER JOIN (
                 SELECT staff_id, MAX(staff_contract_id) AS latest_contract_id
                 FROM staff_contracts
                 GROUP BY staff_id
             ) latest ON latest.staff_id = s.staff_id
             INNER JOIN staff_contracts sc ON sc.staff_contract_id = latest.latest_contract_id
             WHERE s.flag = 1 AND sc.status_id = 1'
        )->result_array();

        foreach ($flaggedStaff as $row) {
            $ci->db->where('staff_id', (int) $row['staff_id'])->update('staff', ['flag' => 0]);
            $clearedFlags++;
        }

        $clearedNotifications = 0;
        $staleReminders = $ci->db->query(
            'SELECT DISTINCT en.staff_id
             FROM email_notifications en
             INNER JOIN (
                 SELECT staff_id, MAX(staff_contract_id) AS latest_contract_id
                 FROM staff_contracts
                 GROUP BY staff_id
             ) latest ON latest.staff_id = en.staff_id
             INNER JOIN staff_contracts sc ON sc.staff_contract_id = latest.latest_contract_id
             WHERE en.subject IN (?, ?)
               AND sc.end_date > ?',
            array_merge(contract_reminder_subjects(), [$threshold])
        )->result_array();

        foreach ($staleReminders as $row) {
            clear_contract_reminder_notifications((int) $row['staff_id']);
            $clearedNotifications++;
        }

        return [
            'fixed_contracts' => $fixedContracts,
            'cleared_flags' => $clearedFlags,
            'cleared_notifications' => $clearedNotifications,
        ];
    }
}
