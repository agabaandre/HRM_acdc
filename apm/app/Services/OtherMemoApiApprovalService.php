<?php

namespace App\Services;

use App\Models\OtherMemo;
use App\Models\OtherMemoApprovalTrail;

/**
 * API-driven approve / return / resubmit for {@see OtherMemo} (matches web {@see OtherMemoController} behaviour).
 */
class OtherMemoApiApprovalService
{
    public static function approve(OtherMemo $memo, int $staffId, ?string $remarks): OtherMemo
    {
        $seq = (int) $memo->active_sequence;
        OtherMemoApprovalTrail::create([
            'other_memo_id' => $memo->id,
            'approval_order' => $seq,
            'staff_id' => $staffId,
            'action' => 'approved',
            'remarks' => $remarks,
        ]);

        $total = $memo->approversCount();
        if ($seq >= $total) {
            $memo->overall_status = OtherMemo::STATUS_APPROVED;
            $memo->active_sequence = null;
            $memo->current_approver_staff_id = null;
            $memo->approved_at = now();
        } else {
            $memo->active_sequence = $seq + 1;
            $next = $memo->approverAtSequence($memo->active_sequence);
            $memo->current_approver_staff_id = $next['staff_id'] ?? null;
        }

        $memo->save();

        if ($memo->overall_status === OtherMemo::STATUS_PENDING && $memo->current_approver_staff_id) {
            OtherMemoApproverNotifier::notifyCurrentApprover(
                (int) $memo->current_approver_staff_id,
                $memo->fresh()
            );
        }

        return $memo->fresh();
    }

    public static function returnToCreator(OtherMemo $memo, int $staffId, string $remarks): OtherMemo
    {
        $seq = (int) $memo->active_sequence;
        OtherMemoApprovalTrail::create([
            'other_memo_id' => $memo->id,
            'approval_order' => $seq,
            'staff_id' => $staffId,
            'action' => 'returned',
            'remarks' => $remarks,
        ]);

        $memo->overall_status = OtherMemo::STATUS_RETURNED;
        $memo->returned_at_sequence = $seq;
        $memo->current_approver_staff_id = $memo->staff_id;
        $memo->active_sequence = null;
        $memo->save();

        return $memo->fresh();
    }

    /**
     * Resubmit after return (creator only). Requires returned_at_sequence.
     */
    public static function resubmit(OtherMemo $memo, int $staffId, ?string $comment): OtherMemo
    {
        $seq = (int) $memo->returned_at_sequence;
        $memo->overall_status = OtherMemo::STATUS_PENDING;
        $memo->active_sequence = $seq;
        $row = $memo->approverAtSequence($seq);
        $memo->current_approver_staff_id = $row['staff_id'] ?? null;
        $memo->returned_at_sequence = null;
        $memo->submitted_at = now();

        OtherMemoApprovalTrail::create([
            'other_memo_id' => $memo->id,
            'approval_order' => 0,
            'staff_id' => $staffId,
            'action' => 'resubmitted',
            'remarks' => $comment,
        ]);

        $memo->save();

        OtherMemoApproverNotifier::notifyCurrentApprover(
            $memo->current_approver_staff_id,
            $memo->fresh()
        );

        return $memo->fresh();
    }
}
