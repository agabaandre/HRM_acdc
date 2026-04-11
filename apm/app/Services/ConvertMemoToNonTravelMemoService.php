<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ActivityBudget;
use App\Models\ApprovalTrail;
use App\Models\ChangeRequest;
use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\ParticipantSchedule;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Models\FundCode;
use App\Models\FundCodeTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts a returned single memo (Activity) or special memo into a Non-Travel memo,
 * migrates approval trails, and removes the source record.
 * Budget lines are not copied (formats differ); single-memo fund reservations are released on the fund codes.
 *
 * The new memo always has document_number null so {@see \App\Traits\HasDocumentNumber} assigns
 * a non-travel sequence; the source single-memo or special-memo document number is never copied.
 */
class ConvertMemoToNonTravelMemoService
{
    public function fromSingleMemoActivity(Activity $activity, int $categoryId): NonTravelMemo
    {
        if (!(int) ($activity->is_single_memo ?? 0)) {
            throw new InvalidArgumentException('Only single memo activities can be converted to a non-travel memo.');
        }
        $this->assertReturnedStatus($activity->overall_status);
        $this->assertCategory($categoryId);
        $this->assertNoBlockersForActivity($activity);

        return DB::transaction(function () use ($activity, $categoryId) {
            $oldActivityId = $activity->id;
            $memo = $this->createNonTravelFromActivity($activity, $categoryId);
            $this->migrateTrailsFromActivity($activity, $memo);
            $this->restoreFundBalancesAndRemoveSingleMemoBudget($activity);
            ParticipantSchedule::where('activity_id', $activity->id)->delete();
            $this->updateChangeRequestsFromActivity($activity, $memo);
            $this->deleteApprovalTrailsForActivity($activity->id);
            ActivityApprovalTrail::where('activity_id', $activity->id)->delete();
            $activity->delete();

            Log::info('Converted single memo activity to non-travel memo', [
                'old_activity_id' => $oldActivityId,
                'non_travel_memo_id' => $memo->id,
            ]);

            return $memo->fresh();
        });
    }

    public function fromSpecialMemo(SpecialMemo $specialMemo, int $categoryId): NonTravelMemo
    {
        $this->assertReturnedStatus($specialMemo->overall_status);
        $this->assertCategory($categoryId);
        $this->assertNoBlockersForSpecialMemo($specialMemo);

        return DB::transaction(function () use ($specialMemo, $categoryId) {
            $oldSpecialId = $specialMemo->id;
            $memo = $this->createNonTravelFromSpecialMemo($specialMemo, $categoryId);
            $this->migrateTrailsFromSpecialMemo($specialMemo, $memo);
            $this->updateChangeRequestsFromSpecialMemo($specialMemo, $memo);
            $this->deleteApprovalTrailsForSpecialMemo($specialMemo->id);
            $specialMemo->delete();

            Log::info('Converted special memo to non-travel memo', [
                'old_special_memo_id' => $oldSpecialId,
                'non_travel_memo_id' => $memo->id,
            ]);

            return $memo->fresh();
        });
    }

    private function assertReturnedStatus(?string $status): void
    {
        $s = strtolower(trim((string) $status));
        if ($s !== 'returned') {
            throw new InvalidArgumentException('Only memos with status "returned" can be converted to a non-travel memo.');
        }
    }

    private function assertCategory(int $categoryId): void
    {
        if ($categoryId < 1 || !NonTravelMemoCategory::whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException('Select a valid non-travel memo category.');
        }
    }

    private function assertNoBlockersForActivity(Activity $activity): void
    {
        if (ServiceRequest::where('activity_id', $activity->id)->exists()) {
            throw new RuntimeException('This memo is linked to a service request. Resolve or remove that link before converting.');
        }
        if (ServiceRequest::where('source_id', $activity->id)
            ->where(function ($q) {
                $q->where('model_type', Activity::class)
                    ->orWhere('model_type', 'App\\Models\\Activity')
                    ->orWhere('source_type', 'activity');
            })
            ->exists()) {
            throw new RuntimeException('This memo is linked to a service request. Resolve or remove that link before converting.');
        }
        if (RequestARF::where('source_id', $activity->id)->where('model_type', Activity::class)->exists()) {
            throw new RuntimeException('This memo has an Activity Request (ARF). Remove or complete it before converting.');
        }
    }

    private function assertNoBlockersForSpecialMemo(SpecialMemo $specialMemo): void
    {
        if (ServiceRequest::where('source_id', $specialMemo->id)
            ->where(function ($q) {
                $q->where('model_type', SpecialMemo::class)
                    ->orWhere('model_type', 'App\\Models\\SpecialMemo')
                    ->orWhere('source_type', 'special_memo');
            })
            ->exists()) {
            throw new RuntimeException('This memo is linked to a service request. Resolve or remove that link before converting.');
        }
        if (RequestARF::where('source_id', $specialMemo->id)->where('model_type', SpecialMemo::class)->exists()) {
            throw new RuntimeException('This memo has an Activity Request (ARF). Remove or complete it before converting.');
        }
    }

    private function createNonTravelFromActivity(Activity $activity, int $categoryId): NonTravelMemo
    {
        $location = $activity->location_id;
        if (is_string($location)) {
            $location = json_decode($location, true) ?: [];
        }
        $attachment = $activity->attachment;
        if (is_string($attachment)) {
            $attachment = json_decode($attachment, true) ?: [];
        }

        return NonTravelMemo::create([
            'forward_workflow_id' => $activity->forward_workflow_id,
            'reverse_workflow_id' => $activity->reverse_workflow_id,
            'overall_status' => $activity->overall_status,
            'approval_level' => $activity->approval_level,
            'next_approval_level' => $activity->next_approval_level,
            'approval_order_map' => $activity->approval_order_map,
            'workplan_activity_code' => $activity->workplan_activity_code,
            'staff_id' => $activity->staff_id,
            'division_id' => $activity->division_id,
            'fund_type_id' => $activity->fund_type_id ?? 1,
            'memo_date' => $activity->date_from ?? now()->toDateString(),
            'location_id' => is_array($location) ? $location : [],
            'non_travel_memo_category_id' => $categoryId,
            'budget_id' => [],
            'activity_title' => $activity->activity_title,
            'background' => $activity->background ?? '',
            'activity_request_remarks' => $activity->activity_request_remarks ?? '',
            'justification' => '',
            'budget_breakdown' => [],
            'attachment' => is_array($attachment) ? $attachment : [],
            'is_draft' => strtolower((string) $activity->overall_status) === 'draft',
            'document_number' => null,
            'available_budget' => null,
        ]);
    }

    private function createNonTravelFromSpecialMemo(SpecialMemo $specialMemo, int $categoryId): NonTravelMemo
    {
        $location = $specialMemo->location_id;
        if (is_string($location)) {
            $location = json_decode($location, true) ?: [];
        }
        $attachment = $specialMemo->attachment;
        if (is_string($attachment)) {
            $attachment = json_decode($attachment, true) ?: [];
        }

        return NonTravelMemo::create([
            'forward_workflow_id' => $specialMemo->forward_workflow_id,
            'reverse_workflow_id' => $specialMemo->reverse_workflow_id ?? null,
            'overall_status' => $specialMemo->overall_status,
            'approval_level' => $specialMemo->approval_level,
            'next_approval_level' => $specialMemo->next_approval_level,
            'approval_order_map' => $specialMemo->approval_order_map,
            'workplan_activity_code' => $specialMemo->workplan_activity_code,
            'staff_id' => $specialMemo->staff_id,
            'division_id' => $specialMemo->division_id,
            'fund_type_id' => $specialMemo->fund_type_id ?? 1,
            'memo_date' => $specialMemo->date_from ?? now()->toDateString(),
            'location_id' => is_array($location) ? $location : [],
            'non_travel_memo_category_id' => $categoryId,
            'budget_id' => [],
            'activity_title' => $specialMemo->activity_title,
            'background' => $specialMemo->background ?? '',
            'activity_request_remarks' => $specialMemo->activity_request_remarks ?? '',
            'justification' => $specialMemo->justification ?? '',
            'budget_breakdown' => [],
            'attachment' => is_array($attachment) ? $attachment : [],
            'is_draft' => (bool) ($specialMemo->is_draft ?? false),
            'document_number' => null,
            'available_budget' => null,
        ]);
    }

    private function migrateTrailsFromActivity(Activity $activity, NonTravelMemo $memo): void
    {
        $morph = ApprovalTrail::query()
            ->where('model_id', $activity->id)
            ->where(function ($q) {
                $q->where('model_type', Activity::class)
                    ->orWhere('model_type', 'App\\Models\\Activity');
            })
            ->orderBy('id')
            ->get();

        if ($morph->isNotEmpty()) {
            foreach ($morph as $trail) {
                $new = $trail->replicate();
                $new->model_id = $memo->id;
                $new->model_type = NonTravelMemo::class;
                $new->matrix_id = null;
                $new->save();
            }
            return;
        }

        $raw = ActivityApprovalTrail::where('activity_id', $activity->id)->orderBy('id')->get();
        foreach ($raw as $t) {
            $at = new ApprovalTrail([
                'model_id' => $memo->id,
                'model_type' => NonTravelMemo::class,
                'matrix_id' => null,
                'staff_id' => $t->staff_id,
                'oic_staff_id' => $t->oic_staff_id,
                'action' => ActivityApprovalTrail::mapActionForPromotionToApprovalTrail($t->action),
                'remarks' => $t->remarks,
                'approval_order' => $t->approval_order,
                'forward_workflow_id' => $t->forward_workflow_id,
                'is_archived' => $t->is_archived ?? 0,
            ]);
            $at->created_at = $t->created_at;
            $at->updated_at = $t->updated_at ?? $t->created_at;
            $at->save(['timestamps' => false]);
        }
    }

    private function migrateTrailsFromSpecialMemo(SpecialMemo $specialMemo, NonTravelMemo $memo): void
    {
        $trails = ApprovalTrail::query()
            ->where('model_id', $specialMemo->id)
            ->where(function ($q) {
                $q->where('model_type', SpecialMemo::class)
                    ->orWhere('model_type', 'App\\Models\\SpecialMemo');
            })
            ->orderBy('id')
            ->get();

        foreach ($trails as $trail) {
            $new = $trail->replicate();
            $new->model_id = $memo->id;
            $new->model_type = NonTravelMemo::class;
            $new->matrix_id = null;
            $new->save();
        }
    }

    /**
     * Same pattern as ActivityController::destroySingleMemo: restore fund code balances, clear
     * fund_code_transactions tied to activity budgets, then remove activity_budget rows.
     * Any remaining transactions for this activity_id are removed so the new non-travel memo starts with no ledger rows.
     */
    private function restoreFundBalancesAndRemoveSingleMemoBudget(Activity $activity): void
    {
        $activityBudgets = ActivityBudget::where('activity_id', $activity->id)->get();
        $createdBy = function_exists('user_session') ? user_session('staff_id') : null;

        foreach ($activityBudgets as $budget) {
            $fundCode = FundCode::find($budget->fund_code);
            if ($fundCode) {
                $fundCode->budget_balance = floatval($fundCode->budget_balance ?? 0) + floatval($budget->total ?? 0);
                $fundCode->save();

                FundCodeTransaction::create([
                    'fund_code_id' => $budget->fund_code,
                    'amount' => floatval($budget->total ?? 0),
                    'description' => 'Single memo converted to non-travel (budget cleared): ' . ($activity->activity_title ?? '') . ' - Fund Code: ' . $fundCode->code,
                    'activity_id' => $activity->id,
                    'matrix_id' => $activity->matrix_id,
                    'activity_budget_id' => $budget->id,
                    'balance_before' => floatval($fundCode->budget_balance ?? 0) - floatval($budget->total ?? 0),
                    'balance_after' => floatval($fundCode->budget_balance ?? 0),
                    'is_reversal' => true,
                    'created_by' => $createdBy,
                ]);
            }

            FundCodeTransaction::where('activity_budget_id', $budget->id)->delete();
        }

        ActivityBudget::where('activity_id', $activity->id)->delete();

        FundCodeTransaction::where('activity_id', $activity->id)->delete();
    }

    private function updateChangeRequestsFromActivity(Activity $activity, NonTravelMemo $memo): void
    {
        ChangeRequest::where('parent_memo_id', $activity->id)
            ->where(function ($q) {
                $q->where('parent_memo_model', Activity::class)
                    ->orWhere('parent_memo_model', 'App\\Models\\Activity');
            })
            ->update([
                'parent_memo_model' => NonTravelMemo::class,
                'parent_memo_id' => $memo->id,
                'activity_id' => null,
                'non_travel_memo_id' => $memo->id,
            ]);

        ChangeRequest::where('activity_id', $activity->id)->update([
            'activity_id' => null,
            'non_travel_memo_id' => $memo->id,
            'parent_memo_model' => NonTravelMemo::class,
            'parent_memo_id' => $memo->id,
        ]);
    }

    private function deleteApprovalTrailsForActivity(int $activityId): void
    {
        ApprovalTrail::where('model_id', $activityId)
            ->where(function ($q) {
                $q->where('model_type', Activity::class)
                    ->orWhere('model_type', 'App\\Models\\Activity');
            })
            ->delete();
    }

    private function deleteApprovalTrailsForSpecialMemo(int $specialMemoId): void
    {
        ApprovalTrail::where('model_id', $specialMemoId)
            ->where(function ($q) {
                $q->where('model_type', SpecialMemo::class)
                    ->orWhere('model_type', 'App\\Models\\SpecialMemo');
            })
            ->delete();
    }

    private function updateChangeRequestsFromSpecialMemo(SpecialMemo $specialMemo, NonTravelMemo $memo): void
    {
        ChangeRequest::where('parent_memo_id', $specialMemo->id)
            ->where(function ($q) {
                $q->where('parent_memo_model', SpecialMemo::class)
                    ->orWhere('parent_memo_model', 'App\\Models\\SpecialMemo');
            })
            ->update([
                'parent_memo_model' => NonTravelMemo::class,
                'parent_memo_id' => $memo->id,
                'special_memo_id' => null,
                'non_travel_memo_id' => $memo->id,
            ]);

        ChangeRequest::where('special_memo_id', $specialMemo->id)->update([
            'special_memo_id' => null,
            'non_travel_memo_id' => $memo->id,
            'parent_memo_model' => NonTravelMemo::class,
            'parent_memo_id' => $memo->id,
        ]);
    }
}
