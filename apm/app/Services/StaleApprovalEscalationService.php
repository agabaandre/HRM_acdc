<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\OtherMemo;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Models\WorkflowDefinition;
use App\Support\GeneralWorkflowEscalationConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StaleApprovalEscalationService
{
    /** @var array<string, class-string<Model>> */
    private const CATEGORY_MODEL_MAP = [
        'Matrix' => Matrix::class,
        'Special Memo' => SpecialMemo::class,
        'Non-Travel Memo' => NonTravelMemo::class,
        'Single Memo' => Activity::class,
        'Service Request' => ServiceRequest::class,
        'ARF' => RequestARF::class,
        'Change Request' => ChangeRequest::class,
        'Other Memo' => OtherMemo::class,
    ];

    public function __construct(
        private NotificationService $notifications,
        private ApprovalService $approvals,
    ) {}

    /**
     * @return list<\App\Models\Notification>
     */
    public function createEscalationNotifications(): array
    {
        return $this->buildEscalationNotifications(persist: true);
    }

    /**
     * Preview escalation recipients without creating notifications.
     *
     * @return list<array{staff_id: int, name: string, email: string|null, item_count: int, reasons: list<string>}>
     */
    public function previewEscalationRecipients(): array
    {
        $built = $this->buildEscalationNotifications(persist: false);
        $preview = [];
        foreach ($built as $row) {
            $preview[] = $row;
        }

        return $preview;
    }

    /**
     * @return list<array<string, mixed>|Notification>
     */
    private function buildEscalationNotifications(bool $persist): array
    {
        $staleByKey = $this->collectUniqueStaleItems();
        if ($staleByKey === []) {
            return [];
        }

        $thresholdDays = (new PendingApprovalsService())->getApprovalWarningThresholdDays();
        $pendingUrl = url(route('pending-approvals.index', [], false));
        $byRecipient = [];

        foreach ($staleByKey as $item) {
            $model = $this->resolveModel($item);
            if (! $model) {
                continue;
            }

            $excludeStaffIds = $this->currentApproverStaffIds($model);
            foreach ($this->resolveEscalationStaffIds($model, $item, $excludeStaffIds) as $staffId => $reason) {
                $byRecipient[$staffId]['items'][] = array_merge($item, [
                    'escalation_reason' => $reason,
                    'stuck_approval_level' => (int) ($item['approval_level'] ?? $model->approval_level ?? 0),
                    'workflow_role' => $item['workflow_role'] ?? null,
                ]);
                $byRecipient[$staffId]['reasons'][$reason] = true;
            }
        }

        $created = [];
        foreach ($byRecipient as $staffId => $payload) {
            $staff = Staff::query()
                ->where('staff_id', (int) $staffId)
                ->where('active', 1)
                ->whereNotNull('work_email')
                ->first();

            if (! $staff) {
                continue;
            }

            $items = $payload['items'] ?? [];
            $count = count($items);
            if ($count === 0) {
                continue;
            }

            $reasonLabels = array_keys($payload['reasons'] ?? []);

            if (! $persist) {
                $created[] = [
                    'staff_id' => (int) $staffId,
                    'name' => trim($staff->fname . ' ' . $staff->lname),
                    'email' => $staff->work_email,
                    'item_count' => $count,
                    'reasons' => $reasonLabels,
                ];
                continue;
            }

            $message = "Escalation: {$count} document(s) have been pending approval longer than {$thresholdDays} day(s).";

            $created[] = $this->notifications->createNotification([
                'staff_id' => (int) $staffId,
                'model_id' => null,
                'model_type' => null,
                'message' => $message,
                'title' => 'Stale approval escalation',
                'type' => 'stale_pending_approvals_escalation',
                'send_email' => true,
                'email_view_context' => [
                    'stalePendingItems' => $items,
                    'approvalWarningDays' => $thresholdDays,
                    'pendingApprovalsUrl' => $pendingUrl,
                    'staleCount' => $count,
                    'escalationReasons' => $reasonLabels,
                ],
            ]);
        }

        if ($persist) {
            Log::info('Stale approval escalation notifications created', [
                'stale_items' => count($staleByKey),
                'notifications' => count($created),
            ]);
        }

        return $created;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectUniqueStaleItems(): array
    {
        $approvers = $this->notifications->getAllApprovers();
        $thresholdDays = (new PendingApprovalsService())->getApprovalWarningThresholdDays();
        $unique = [];

        foreach ($approvers as $approver) {
            $svc = new PendingApprovalsService([
                'staff_id' => $approver['staff_id'],
                'division_id' => $approver['division_id'] ?? null,
                'permissions' => [],
                'name' => trim(($approver['fname'] ?? '') . ' ' . ($approver['lname'] ?? '')),
                'email' => $approver['work_email'] ?? '',
                'base_url' => config('app.url'),
            ]);

            foreach ($svc->getStalePendingItems($thresholdDays) as $item) {
                $category = (string) ($item['category'] ?? '');
                $id = (int) ($item['id'] ?? 0);
                if ($category === '' || $id <= 0) {
                    continue;
                }
                $unique["{$category}:{$id}"] = $item;
            }
        }

        return $unique;
    }

    private function resolveModel(array $item): ?Model
    {
        $category = (string) ($item['category'] ?? '');
        $id = (int) ($item['id'] ?? 0);
        $class = self::CATEGORY_MODEL_MAP[$category] ?? null;
        if (! $class || $id <= 0) {
            return null;
        }

        $query = $class::query()->whereKey($id);
        if ($category !== 'Other Memo') {
            $query->with('division');
        } else {
            $query->with(['division', 'creator', 'staff']);
        }

        $model = $query->first();

        return $model instanceof Model ? $model : null;
    }

    /**
     * @return list<int>
     */
    private function currentApproverStaffIds(Model $model): array
    {
        if ($model instanceof OtherMemo) {
            $id = (int) ($model->current_approver_staff_id ?? 0);

            return $id > 0 ? [$id] : [];
        }

        $recipient = $this->approvals->getNotificationRecipient($model);
        if (! $recipient) {
            return [];
        }

        return [(int) $recipient->staff_id];
    }

    /**
     * @param  list<int>  $excludeStaffIds
     * @return array<int, string> staff_id => reason key
     */
    private function resolveEscalationStaffIds(Model $model, array $item, array $excludeStaffIds): array
    {
        $recipients = [];
        $exclude = array_fill_keys(array_map('intval', $excludeStaffIds), true);

        $creatorId = $this->creatorStaffId($model);
        if ($creatorId > 0 && ! isset($exclude[$creatorId])) {
            $recipients[$creatorId] = 'creator';
        }

        $hodId = $this->hodStaffId($model);
        if ($hodId > 0 && ! isset($exclude[$hodId])) {
            $recipients[$hodId] = 'hod';
        }

        if ($model instanceof OtherMemo) {
            return $recipients;
        }

        $workflowId = (int) ($model->forward_workflow_id ?? 0);
        $currentLevel = (int) ($model->approval_level ?? 0);
        if ($workflowId <= 0 || $currentLevel <= 0) {
            return $recipients;
        }

        if ($workflowId === GeneralWorkflowEscalationConfig::WORKFLOW_ID) {
            foreach (GeneralWorkflowEscalationConfig::escalationOrders() as $order) {
                $this->addApproversAtLevel($model, $workflowId, $order, $recipients, $exclude, 'configured_approver');
            }
        } else {
            $orders = WorkflowDefinition::query()
                ->where('workflow_id', $workflowId)
                ->where('is_enabled', 1)
                ->where('approval_order', '>', $currentLevel)
                ->orderBy('approval_order')
                ->pluck('approval_order')
                ->unique()
                ->all();

            foreach ($orders as $order) {
                $this->addApproversAtLevel($model, $workflowId, (int) $order, $recipients, $exclude, 'senior_approver');
            }
        }

        return $recipients;
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<int, true>  $exclude
     */
    private function addApproversAtLevel(
        Model $model,
        int $workflowId,
        int $approvalOrder,
        array &$recipients,
        array $exclude,
        string $reason,
    ): void {
        foreach ($this->approvals->getApproverStaffIdsForWorkflowLevel($model, $workflowId, $approvalOrder) as $staffId) {
            if (isset($exclude[$staffId])) {
                continue;
            }
            $recipients[$staffId] = $reason;
        }
    }

    private function creatorStaffId(Model $model): int
    {
        if ($model instanceof OtherMemo) {
            return (int) ($model->staff_id ?? 0);
        }

        return (int) ($model->staff_id ?? 0);
    }

    private function hodStaffId(Model $model): int
    {
        if (! method_exists($model, 'division') || ! $model->division) {
            return 0;
        }

        return (int) ($model->division->division_head ?? 0);
    }
}
