<?php

namespace App\Services;

use App\Models\ApprovalTrail;
use App\Models\ChangeRequest;
use App\Models\OtherMemo;
use App\Models\OtherMemoApprovalTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds subject/body context when a memo is approved and forwarded to the next approver.
 */
class MemoApprovalNotificationPresenter
{
    /**
     * @return array{message: string, view: array<string, mixed>}
     */
    public static function forForwardToNextApprover(Model $model): array
    {
        $resourceLabel = self::resourceLabel($model);
        $memoTitle = self::memoTitle($model);
        $documentNumber = self::documentNumber($model);
        $divisionName = self::divisionName($model);
        $approvedByName = self::lastApproverDisplayName($model);

        $message = self::buildMessage($resourceLabel, $memoTitle, $documentNumber, $approvedByName);

        return [
            'message' => $message,
            'view' => [
                'memo_title' => $memoTitle,
                'document_number_display' => $documentNumber,
                'division_name' => $divisionName,
                'approved_by_name' => $approvedByName,
                'resource_label' => $resourceLabel,
            ],
        ];
    }

    /**
     * First approver after submit/resubmit, or next approver after an approval step.
     *
     * @return array{message: string, view: array<string, mixed>}
     */
    public static function forOtherMemoAwaitingApproval(OtherMemo $memo): array
    {
        $hasApprovedTrail = OtherMemoApprovalTrail::query()
            ->where('other_memo_id', $memo->id)
            ->where('action', 'approved')
            ->exists();

        if ($hasApprovedTrail) {
            return self::forForwardToNextApprover($memo);
        }

        $resourceLabel = self::resourceLabel($memo);
        $memoTitle = self::memoTitle($memo);
        $documentNumber = self::documentNumber($memo);
        $divisionName = self::divisionName($memo);
        $submittedBy = self::otherMemoSubmitterDisplayName($memo);

        $message = $documentNumber !== null
            ? sprintf(
                '%s "%s" (%s) requires your approval. Submitted by %s.',
                $resourceLabel,
                $memoTitle,
                $documentNumber,
                $submittedBy
            )
            : sprintf(
                '%s "%s" requires your approval. Submitted by %s.',
                $resourceLabel,
                $memoTitle,
                $submittedBy
            );

        return [
            'message' => $message,
            'view' => [
                'memo_title' => $memoTitle,
                'document_number_display' => $documentNumber,
                'division_name' => $divisionName,
                'approved_by_name' => $submittedBy,
                'resource_label' => $resourceLabel,
            ],
        ];
    }

    public static function otherMemoSubmitterDisplayName(OtherMemo $memo): string
    {
        $trail = OtherMemoApprovalTrail::query()
            ->where('other_memo_id', $memo->id)
            ->whereIn('action', ['submitted', 'resubmitted'])
            ->orderByDesc('id')
            ->with('staff')
            ->first();

        if ($trail?->staff) {
            return self::formatStaffName($trail->staff);
        }

        $creator = $memo->relationLoaded('creator') ? $memo->creator : $memo->creator()->first();
        if ($creator) {
            return self::formatStaffName($creator);
        }

        return 'Submitter';
    }

    public static function resourceLabel(Model $model): string
    {
        return match (class_basename($model)) {
            'ChangeRequest' => 'Change request',
            'SpecialMemo' => 'Special memo',
            'NonTravelMemo' => 'Non-travel memo',
            'ServiceRequest' => 'Service request',
            'RequestARF' => 'ARF request',
            'Activity' => 'Single memo',
            'Matrix' => 'Matrix',
            'OtherMemo' => 'Other memo',
            default => Str::headline(class_basename($model)),
        };
    }

    public static function memoTitle(Model $model): string
    {
        if ($model instanceof OtherMemo) {
            $payloadTitle = $model->payload['title'] ?? null;
            if (is_string($payloadTitle) && trim(strip_tags($payloadTitle)) !== '') {
                return trim(strip_tags($payloadTitle));
            }

            return (string) ($model->memo_type_name_snapshot ?? 'Other memo');
        }

        if ($model instanceof ChangeRequest) {
            if (is_string($model->activity_title) && trim($model->activity_title) !== '') {
                return trim($model->activity_title);
            }
            $parent = $model->relationLoaded('parentMemo') ? $model->parentMemo : $model->parentMemo()->first();
            if ($parent) {
                $parentTitle = $parent->activity_title ?? $parent->title ?? null;
                if (is_string($parentTitle) && trim($parentTitle) !== '') {
                    return trim($parentTitle);
                }
            }
        }

        foreach (['activity_title', 'title'] as $field) {
            if (isset($model->{$field}) && is_string($model->{$field}) && trim($model->{$field}) !== '') {
                return trim($model->{$field});
            }
        }

        return self::resourceLabel($model);
    }

    public static function documentNumber(Model $model): ?string
    {
        $num = $model->document_number ?? null;
        if (is_string($num) && trim($num) !== '') {
            return trim($num);
        }

        if (isset($model->request_number) && is_string($model->request_number) && trim($model->request_number) !== '') {
            return trim($model->request_number);
        }

        if (isset($model->arf_number) && is_string($model->arf_number) && trim($model->arf_number) !== '') {
            return trim($model->arf_number);
        }

        return null;
    }

    public static function divisionName(Model $model): string
    {
        $division = $model->relationLoaded('division') ? $model->division : $model->division()->first();
        if (! $division) {
            return 'N/A';
        }

        return (string) ($division->division_name ?? $division->name ?? 'N/A');
    }

    public static function lastApproverDisplayName(Model $model): string
    {
        if (empty($model->id)) {
            return 'Previous approver';
        }

        if ($model instanceof OtherMemo) {
            $trail = OtherMemoApprovalTrail::query()
                ->where('other_memo_id', $model->id)
                ->where('action', 'approved')
                ->orderByDesc('id')
                ->with('staff')
                ->first();
            if ($trail?->staff) {
                return self::formatStaffName($trail->staff);
            }
        }

        $trail = ApprovalTrail::query()
            ->where('model_id', $model->id)
            ->where('model_type', $model::class)
            ->where('action', 'approved')
            ->where('is_archived', 0)
            ->orderByDesc('id')
            ->with('staff')
            ->first();

        if ($trail?->staff) {
            return self::formatStaffName($trail->staff);
        }

        return 'Previous approver';
    }

    /**
     * @param  object{title?: string, fname?: string, lname?: string}  $staff
     */
    public static function formatStaffName(object $staff): string
    {
        return trim(implode(' ', array_filter([
            $staff->title ?? '',
            $staff->fname ?? '',
            $staff->lname ?? '',
        ])));
    }

    private static function buildMessage(
        string $resourceLabel,
        string $memoTitle,
        ?string $documentNumber,
        string $approvedByName
    ): string {
        $titleQuoted = '"'.$memoTitle.'"';

        if ($documentNumber !== null) {
            return sprintf(
                '%s %s (%s) requires your approval. Approved by %s.',
                $resourceLabel,
                $titleQuoted,
                $documentNumber,
                $approvedByName
            );
        }

        return sprintf(
            '%s %s requires your approval. Approved by %s.',
            $resourceLabel,
            $titleQuoted,
            $approvedByName
        );
    }
}
