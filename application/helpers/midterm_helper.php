<?php
if (!function_exists('show_midterm_approval_action')) {
    function show_midterm_approval_action($ppa, $approval_trail = [], $current_user = '')
    { 
       //dd($approval_trail);
        $staff_id = $current_user->staff_id ?? null;
        //dd($ppa);
        $isSupervisor1 = isset($ppa->midterm_supervisor_1) && $ppa->midterm_supervisor_1 == $staff_id;
        $isSupervisor2 = isset($ppa->midterm_supervisor_2) && $ppa->midterm_supervisor_2 == $staff_id;

        // Get the most recent action (approval trail is ordered by most recent first from model)
        $last_action = (is_array($approval_trail) && count($approval_trail) > 0)
            ? (reset($approval_trail)->action ?? null)
            : null;

        $supervisor1Approved = false;
        $supervisor2Approved = false;

        foreach ($approval_trail as $log) {
            if (isset($log->action, $log->staff_id)) {
                if ($log->action === 'Approved' && $log->staff_id == $ppa->midterm_supervisor_1) {
                    $supervisor1Approved = true;
                }
                if ($log->action === 'Approved' && $log->staff_id == $ppa->midterm_supervisor_2) {
                    $supervisor2Approved = true;
                }
            }
        }

        // Check if midterm was returned after approval (last action is "Returned")
        $isReturned = false;
        if (!empty($approval_trail) && is_array($approval_trail)) {
            // Get the most recent action (approval trail should be ordered by most recent first)
            $most_recent_action = reset($approval_trail);
            if ($most_recent_action && isset($most_recent_action->action) && $most_recent_action->action === 'Returned') {
                $isReturned = true;
            }
        }

        $requiresSecond = function_exists('ppa_phase_requires_second_supervisor')
            && ppa_phase_requires_second_supervisor('midterm')
            && !empty($ppa->midterm_supervisor_2);

        // Logic to show Approve buttons or print options
        if ($isSupervisor1 && $ppa->midterm_draft_status == 0 && in_array($last_action, ['Submitted', 'Updated']) && !$isReturned) {
            return 'show';
        } elseif ($isSupervisor2 && $requiresSecond && $supervisor1Approved && $ppa->midterm_draft_status == 0 && $last_action === 'Approved' && !$supervisor2Approved && !$isReturned) {
            return 'show';
        } elseif (
            // Approved after first supervisor when second supervisor is not required,
            // or after both when it is required.
            (((int) $ppa->midterm_draft_status === 2) || ($supervisor1Approved && (!$requiresSecond || $supervisor2Approved)))
            && !$isReturned
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
