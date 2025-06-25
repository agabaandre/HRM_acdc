<?php
if (!function_exists('show_midterm_approval_action')) {
    function show_midterm_approval_action($ppa, $approval_trail = [], $current_user = '')
    { 
       //dd($approval_trail);
        $staff_id = $current_user->staff_id ?? null;
        $isSupervisor1 = isset($ppa->supervisor_id) && $ppa->supervisor_id == $staff_id;
        $isSupervisor2 = isset($ppa->supervisor2_id) && $ppa->supervisor2_id == $staff_id;

        $last_action = (is_array($approval_trail) && count($approval_trail) > 0)
            ? (end($approval_trail)->action ?? null)
            : null;

        $supervisor1Approved = false;
        $supervisor2Approved = false;

        foreach ($approval_trail as $log) {
            if (isset($log->action, $log->staff_id)) {
                if ($log->action === 'Approved' && $log->staff_id == $ppa->supervisor_id) {
                    $supervisor1Approved = true;
                }
                if ($log->action === 'Approved' && $log->staff_id == $ppa->supervisor2_id) {
                    $supervisor2Approved = true;
                }
            }
        }

        // Logic to show Approve buttons or print options
        if ($isSupervisor1 && $ppa->midterm_draft_status == 0 && in_array($last_action, ['Midterm Submitted', 'Midterm Updated'])) {
            return 'show';
        } elseif ($isSupervisor2 && $supervisor1Approved && $ppa->midterm_draft_status == 0 && $last_action === 'Midterm Approved' && !$supervisor2Approved) {
            return 'show';
        } elseif (
            ($supervisor1Approved && is_null($ppa->supervisor2_id)) ||
            ($supervisor1Approved && $supervisor2Approved)
        ) {
            return '<a href="' . base_url('performance/midterm/print_ppa/' . $ppa->entry_id) . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id . '" 
                        class="btn btn-dark btn-sm me-2" target="_blank">
                        <i class="fa fa-print"></i> Print Midterm without Approval Trail
                    </a>' .
                   '<a href="' . base_url('performance/midterm/print_ppa/' . $ppa->entry_id) . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id . '/1' . '" 
                        class="btn btn-dark btn-sm" target="_blank">
                        <i class="fa fa-print"></i> Print Midterm With Approval Trail
                    </a>';
        } else {
            return false;
        }
    }
}
