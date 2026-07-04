<?php

namespace App\Services;

use App\Models\ApprovalTrail;
use App\Models\OtherMemoApprovalTrail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes “received at this approval step” using the same SQL rules as
 * {@see \App\Http\Controllers\ApproverDashboardHelper::getAverageApprovalTimeAll()}.
 *
 * After an approver returns a document for revision, only trails after that return
 * count toward submit/receipt time so resubmission delay is attributed to the submitter.
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
        $floor = $this->lastOtherMemoReturnBefore($memoId, $actedAt);

        if ($seq <= 1) {
            $t = $this->maxOtherMemoSubmitAfter($memoId, $actedAt, $floor);

            return $t ? Carbon::parse($t) : null;
        }

        $prev = $this->maxOtherMemoPrevApprovalAfter($memoId, $seq, $actedAt, $floor);

        if ($prev !== null) {
            return Carbon::parse($prev);
        }

        $t = $this->maxOtherMemoSubmitAfter($memoId, $actedAt, $floor);

        return $t ? Carbon::parse($t) : null;
    }

    /**
     * CASE expression: receipt instant for an approval_trails approve/reject row aliased as $atAlias.
     */
    public function receivedTimeCaseSql(string $atAlias = 'at'): string
    {
        $floor = self::lastReturnFloorSql($atAlias);
        $submitMatch = self::submitWorkflowMatchSql('sub_at', $atAlias);
        $prevMatch = "prev_at.model_type = {$atAlias}.model_type
                               AND prev_at.model_id = {$atAlias}.model_id
                               AND prev_at.forward_workflow_id = {$atAlias}.forward_workflow_id";

        $submitSubquery = "
                            (SELECT MAX(sub_at.updated_at)
                             FROM approval_trails sub_at
                             WHERE sub_at.model_type = {$atAlias}.model_type
                               AND sub_at.model_id = {$atAlias}.model_id
                               AND {$submitMatch}
                               AND sub_at.approval_order = 0
                               AND sub_at.action IN ('submitted', 'resubmitted')
                               AND sub_at.is_archived = 0
                               AND sub_at.updated_at <= {$atAlias}.updated_at
                               AND sub_at.updated_at > {$floor})";

        $prevSubquery = function (string $orderCondition) use ($prevMatch, $atAlias, $floor): string {
            return "
                            (SELECT MAX(prev_at.updated_at)
                             FROM approval_trails prev_at
                             WHERE {$prevMatch}
                               AND {$orderCondition}
                               AND prev_at.action IN ('approved', 'rejected')
                               AND prev_at.is_archived = 0
                               AND prev_at.updated_at <= {$atAlias}.updated_at
                               AND prev_at.updated_at > {$floor})";
        };

        $prevLtOrder = $prevSubquery("prev_at.approval_order < {$atAlias}.approval_order");
        $prevLtTwo = $prevSubquery('prev_at.approval_order < 2');

        return "
                    CASE
                        WHEN {$atAlias}.approval_order >= 3 THEN COALESCE({$prevLtOrder}, {$submitSubquery})
                        WHEN {$atAlias}.approval_order = 2 THEN COALESCE({$prevLtTwo}, {$submitSubquery})
                        WHEN {$atAlias}.approval_order = 1 THEN {$submitSubquery}
                        ELSE NULL
                    END";
    }

    /**
     * Latest submit/resubmit after the last return before final approval (workflow stats).
     */
    public static function workflowStatsSubmittedTimeSql(string $atAlias = 'at'): string
    {
        return "(
            SELECT MAX(sub_t.updated_at)
            FROM approval_trails sub_t
            WHERE sub_t.model_id = {$atAlias}.model_id
              AND sub_t.model_type = {$atAlias}.model_type
              AND sub_t.action IN ('submitted', 'resubmitted')
              AND sub_t.is_archived = 0
              AND sub_t.updated_at > COALESCE((
                SELECT MAX(ret.updated_at)
                FROM approval_trails ret
                WHERE ret.model_id = {$atAlias}.model_id
                  AND ret.model_type = {$atAlias}.model_type
                  AND ret.action = 'returned'
                  AND ret.updated_at <= COALESCE((
                    SELECT MAX(la.updated_at)
                    FROM approval_trails la
                    WHERE la.model_id = {$atAlias}.model_id
                      AND la.model_type = {$atAlias}.model_type
                      AND la.action IN ('approved', 'rejected')
                      AND la.is_archived = 0
                      AND la.forward_workflow_id = {$atAlias}.forward_workflow_id
                  ), sub_t.updated_at)
              ), '1970-01-01')
        )";
    }

    /**
     * Lower bound for receipt/submit lookups: last return strictly before the action instant.
     */
    public static function lastReturnFloorSql(string $atAlias): string
    {
        return "COALESCE(
            (SELECT MAX(ret.updated_at)
             FROM approval_trails ret
             WHERE ret.model_type = {$atAlias}.model_type
               AND ret.model_id = {$atAlias}.model_id
               AND ret.action = 'returned'
               AND ret.updated_at < {$atAlias}.updated_at),
            '1970-01-01'
        )";
    }

    public static function submitWorkflowMatchSql(string $subAlias, string $atAlias): string
    {
        return "(
            {$subAlias}.forward_workflow_id = {$atAlias}.forward_workflow_id
            OR ({$subAlias}.forward_workflow_id IS NULL AND {$atAlias}.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = {$atAlias}.model_id LIMIT 1) = {$atAlias}.forward_workflow_id)
            OR ({$subAlias}.forward_workflow_id IS NULL AND {$atAlias}.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = {$atAlias}.model_id LIMIT 1) = {$atAlias}.forward_workflow_id)
            OR ({$subAlias}.forward_workflow_id IS NULL AND {$atAlias}.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND {$atAlias}.forward_workflow_id IS NOT NULL)
        )";
    }

    /**
     * Last return trail time for a document (includes archived returns).
     */
    public function lastReturnBeforeForModel(string $modelType, int $modelId, ?Carbon $before = null): ?Carbon
    {
        $query = DB::table('approval_trails')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('action', 'returned');

        if ($before !== null) {
            $query->where('updated_at', '<', $before);
        }

        $timestamp = $query->max('updated_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
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
        $receivedCase = $this->receivedTimeCaseSql('at');

        return "
                SELECT {$receivedCase} as received_time
                FROM approval_trails at
                WHERE at.id = ?
                ";
    }

    protected function lastOtherMemoReturnBefore(int $memoId, Carbon $before): ?string
    {
        return DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('action', 'returned')
            ->where('created_at', '<', $before)
            ->max('created_at');
    }

    protected function maxOtherMemoSubmitAfter(int $memoId, Carbon $actedAt, ?string $floor): ?string
    {
        $query = DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('approval_order', 0)
            ->whereIn('action', ['submitted', 'resubmitted'])
            ->where('created_at', '<=', $actedAt);

        if ($floor !== null) {
            $query->where('created_at', '>', $floor);
        }

        return $query->max('created_at');
    }

    protected function maxOtherMemoPrevApprovalAfter(int $memoId, int $seq, Carbon $actedAt, ?string $floor): ?string
    {
        $query = DB::table('other_memos_approval_trails')
            ->where('other_memo_id', $memoId)
            ->where('approval_order', '<', $seq)
            ->where('action', 'approved')
            ->where('created_at', '<=', $actedAt);

        if ($floor !== null) {
            $query->where('created_at', '>', $floor);
        }

        return $query->max('created_at');
    }
}
