<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('staff_portal_eligible_contract_status_ids')) {
    /**
     * Contract statuses that should have an active portal login.
     *
     * @return list<int>
     */
    function staff_portal_eligible_contract_status_ids(): array
    {
        return [1, 2, 7];
    }
}

if (!function_exists('staff_latest_contract_status_id')) {
    function staff_latest_contract_status_id(int $staffId): ?int
    {
        $ci = &get_instance();

        $latestContractId = $ci->db
            ->select_max('staff_contract_id')
            ->where('staff_id', $staffId)
            ->get('staff_contracts')
            ->row('staff_contract_id');

        if (!$latestContractId) {
            return null;
        }

        $statusId = $ci->db
            ->select('status_id')
            ->where('staff_contract_id', (int) $latestContractId)
            ->get('staff_contracts')
            ->row('status_id');

        return $statusId !== null ? (int) $statusId : null;
    }
}

if (!function_exists('sync_staff_portal_account')) {
    /**
     * Create, enable, or disable a portal user account from the staff member's latest contract.
     *
     * @return array{action: string, changed: bool}
     */
    function sync_staff_portal_account(int $staffId): array
    {
        $ci = &get_instance();
        $staff = $ci->db->get_where('staff', ['staff_id' => $staffId])->row();

        if (!$staff || trim((string) $staff->work_email) === '') {
            return ['action' => 'skipped_no_email', 'changed' => false];
        }

        $statusId = staff_latest_contract_status_id($staffId);
        if ($statusId === null) {
            return ['action' => 'skipped_no_contract', 'changed' => false];
        }

        $eligible = in_array($statusId, staff_portal_eligible_contract_status_ids(), true);
        $existing = $ci->db->get_where('user', ['auth_staff_id' => $staffId])->row();

        if ($eligible) {
            if ($existing) {
                if ((int) $existing->status !== 1) {
                    $ci->db->where('auth_staff_id', $staffId)->update('user', ['status' => 1]);

                    return ['action' => 'enabled', 'changed' => $ci->db->affected_rows() > 0];
                }

                return ['action' => 'already_active', 'changed' => false];
            }

            $ci->db->replace('user', [
                'name' => trim($staff->lname . ' ' . $staff->fname),
                'status' => 1,
                'auth_staff_id' => $staffId,
                'password' => $ci->argonhash->make(setting()->default_password),
                'role' => 17,
            ]);

            return ['action' => 'created', 'changed' => $ci->db->affected_rows() > 0];
        }

        if ($existing && (int) $existing->status !== 0) {
            $ci->db->where('auth_staff_id', $staffId)->update('user', ['status' => 0]);

            return ['action' => 'disabled', 'changed' => $ci->db->affected_rows() > 0];
        }

        return ['action' => 'already_inactive', 'changed' => false];
    }
}

if (!function_exists('sync_all_staff_portal_accounts')) {
    /**
     * Bulk sync used by manage_accounts background job.
     *
     * @return array{created: int, enabled: int, disabled: int}
     */
    function sync_all_staff_portal_accounts(): array
    {
        $ci = &get_instance();
        $subquery = '
            SELECT MAX(staff_contract_id) AS latest_contract_id
            FROM staff_contracts
            GROUP BY staff_id
        ';

        $staffRows = $ci->db->query("
            SELECT DISTINCT s.staff_id
            FROM staff s
            JOIN staff_contracts sc ON s.staff_id = sc.staff_id
            WHERE sc.staff_contract_id IN ($subquery)
              AND s.work_email != ''
        ")->result();

        $stats = ['created' => 0, 'enabled' => 0, 'disabled' => 0];
        foreach ($staffRows as $row) {
            $result = sync_staff_portal_account((int) $row->staff_id);
            if (!$result['changed']) {
                continue;
            }
            if ($result['action'] === 'created') {
                $stats['created']++;
            } elseif ($result['action'] === 'enabled') {
                $stats['enabled']++;
            } elseif ($result['action'] === 'disabled') {
                $stats['disabled']++;
            }
        }

        return $stats;
    }
}
