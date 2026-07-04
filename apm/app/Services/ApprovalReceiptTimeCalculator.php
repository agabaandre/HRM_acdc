<?php

namespace App\Services;

use App\Models\ApprovalTrail;
use App\Models\OtherMemoApprovalTrail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes “received at this approval step” using the same SQL rules as
 * {@see \App\Http\Controllers\ApproverDashboardHelper::getAverageApprovalTimeAll()}.
 */
class ApprovalReceiptTimeCalculator
{
    /**
     * Receipt instant for a saved approval_trails row (uses trail.updated_at as action instant).
     */
    public function receivedAtForApprovalTrail(ApprovalTrail $trail): ?Carbon
    {
        $row = DB::selectOne($this->approvalTrailReceivedTimeSql(), [$trail->id]);

        return $this->parseReceivedRow($row);
    }

    /**
     * Receipt instant for an other_memos_approval_trails approve/reject row (uses trail timestamps).
     */
    public function receivedAtForOtherMemoTrail(OtherMemoApprovalTrail $trail): ?Carbon
    {
        $memoId = (int) $trail->other_memo_id;
        $seq = (int) $trail->approval_order;
        $actedAt = Carbon::parse($trail->updated_at ?? $trail->created_at);

        $lastReturn = DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('action', 'returned')
            ->where('created_at', '<=', $actedAt)
            ->max('created_at');

        $afterReturn = $lastReturn ? Carbon::parse($lastReturn) : null;

        if ($seq <= 1) {
            $query = DB::table('other_memos_approval_trails')
                ->where('other_memo_id', $memoId)
                ->where('approval_order', 0)
                ->whereIn('action', ['submitted', 'resubmitted'])
                ->where('created_at', '<=', $actedAt);

            if ($afterReturn !== null) {
                $query->where('created_at', '>', $afterReturn);
            }

            $t = $query->max('created_at');

            return $t ? Carbon::parse($t) : null;
        }

        $prevQuery = DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('approval_order', '<', $seq)
            ->where('action', 'approved')
            ->where('created_at', '<=', $actedAt);

        if ($afterReturn !== null) {
            $prevQuery->where('created_at', '>', $afterReturn);
        }

        $prev = $prevQuery->max('created_at');

        if ($prev !== null) {
            return Carbon::parse($prev);
        }

        $subQuery = DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('approval_order', 0)
            ->whereIn('action', ['submitted', 'resubmitted'])
            ->where('created_at', '<=', $actedAt);

        if ($afterReturn !== null) {
            $subQuery->where('created_at', '>', $afterReturn);
        }

        $t = $subQuery->max('created_at');

        return $t ? Carbon::parse($t) : null;
    }

    /**
     * SQL expression: latest return-to-submitter before the current action row.
     */
    public static function sqlLastReturnBeforeActionExpr(string $atAlias = 'at'): string
    {
        return "(SELECT MAX(ret_at.updated_at)
                 FROM approval_trails ret_at
                 WHERE ret_at.model_type = {$atAlias}.model_type
                   AND ret_at.model_id = {$atAlias}.model_id
                   AND ret_at.forward_workflow_id = {$atAlias}.forward_workflow_id
                   AND ret_at.action = 'returned'
                   AND ret_at.is_archived = 0
                   AND ret_at.updated_at < {$atAlias}.updated_at)";
    }

    /**
     * SQL fragment: trail row must be after the most recent return (resubmit clock starts at new submission).
     */
    public static function sqlAfterLastReturnCondition(string $trailAlias, string $atAlias = 'at'): string
    {
        $lastReturn = self::sqlLastReturnBeforeActionExpr($atAlias);

        return "AND {$trailAlias}.updated_at > COALESCE({$lastReturn}, '1970-01-01 00:00:00')";
    }

    protected function parseReceivedRow(?object $row): ?Carbon
    {
        if (! $row || empty($row->received_time)) {
            return null;
        }

        return Carbon::parse($row->received_time);
    }

    protected function approvalTrailReceivedTimeSql(): string
    {
        $afterReturnPrev = self::sqlAfterLastReturnCondition('prev_at');
        $afterReturnSub = self::sqlAfterLastReturnCondition('sub_at');

        return "
                SELECT 
                    CASE
                        WHEN at.approval_order >= 3 THEN COALESCE(
                            (SELECT MAX(prev_at.updated_at)
                             FROM approval_trails prev_at
                             WHERE prev_at.model_type = at.model_type
                               AND prev_at.model_id = at.model_id
                               AND prev_at.forward_workflow_id = at.forward_workflow_id
                               AND prev_at.approval_order < at.approval_order
                               AND prev_at.action IN ('approved', 'rejected')
                               AND prev_at.is_archived = 0
                               AND prev_at.updated_at <= at.updated_at
                               {$afterReturnPrev}),
                            (SELECT MAX(sub_at.updated_at)
                             FROM approval_trails sub_at
                             WHERE sub_at.model_type = at.model_type
                               AND sub_at.model_id = at.model_id
                               AND (
                                   sub_at.forward_workflow_id = at.forward_workflow_id
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                               )
                               AND sub_at.approval_order = 0
                               AND sub_at.action IN ('submitted', 'resubmitted')
                               AND sub_at.is_archived = 0
                               AND sub_at.updated_at <= at.updated_at
                               {$afterReturnSub})
                        )
                        WHEN at.approval_order = 2 THEN COALESCE(
                            (SELECT MAX(prev_at.updated_at)
                             FROM approval_trails prev_at
                             WHERE prev_at.model_type = at.model_type
                               AND prev_at.model_id = at.model_id
                               AND prev_at.forward_workflow_id = at.forward_workflow_id
                               AND prev_at.approval_order < 2
                               AND prev_at.action IN ('approved', 'rejected')
                               AND prev_at.is_archived = 0
                               AND prev_at.updated_at <= at.updated_at
                               {$afterReturnPrev}),
                            (SELECT MAX(sub_at.updated_at)
                             FROM approval_trails sub_at
                             WHERE sub_at.model_type = at.model_type
                               AND sub_at.model_id = at.model_id
                               AND (
                                   sub_at.forward_workflow_id = at.forward_workflow_id
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                               )
                               AND sub_at.approval_order = 0
                               AND sub_at.action IN ('submitted', 'resubmitted')
                               AND sub_at.is_archived = 0
                               AND sub_at.updated_at <= at.updated_at
                               {$afterReturnSub})
                        )
                        WHEN at.approval_order = 1 THEN (
                            SELECT MAX(sub_at.updated_at)
                            FROM approval_trails sub_at
                            WHERE sub_at.model_type = at.model_type
                              AND sub_at.model_id = at.model_id
                              AND (
                                  sub_at.forward_workflow_id = at.forward_workflow_id
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                              )
                              AND sub_at.approval_order = 0
                              AND sub_at.action IN ('submitted', 'resubmitted')
                              AND sub_at.is_archived = 0
                              AND sub_at.updated_at <= at.updated_at
                              {$afterReturnSub}
                        )
                        ELSE NULL
                    END as received_time
                FROM approval_trails at
                WHERE at.id = ?
                ";
    }
}
