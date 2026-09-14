<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Staff;
use App\Jobs\SendNotificationEmailJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    /**
     * Create and dispatch a notification
     */
    public function createNotification(array $data): Notification
    {
        $notification = Notification::create([
            'staff_id' => $data['staff_id'],
            'model_id' => $data['model_id'] ?? null,
            'model_type' => $data['model_type'] ?? null,
            'message' => $data['message'],
            'title' => $data['title'] ?? Notification::DEFAULT_TITLE,
            'type' => $data['type'],
            'is_read' => false
        ]);

        // Dispatch email job to queue
        if (isset($data['send_email']) && $data['send_email']) {
            $this->dispatchEmailNotification($notification, $data);
        }

        Log::info('Notification created', [
            'notification_id' => $notification->id,
            'staff_id' => $data['staff_id'],
            'type' => $data['type']
        ]);

        return $notification;
    }

    /**
     * Create multiple notifications for multiple staff members
     */
    public function createBulkNotifications(array $staffIds, array $data): array
    {
        $notifications = [];
        
        foreach ($staffIds as $staffId) {
            $notificationData = array_merge($data, ['staff_id' => $staffId]);
            $notifications[] = $this->createNotification($notificationData);
        }

        return $notifications;
    }

    /**
     * Create daily pending approvals notifications
     */
    public function createDailyPendingApprovalsNotifications(): array
    {
        $approvers = $this->getAllApprovers();
        $notifications = [];

        foreach ($approvers as $approver) {
            $pendingApprovalsService = new PendingApprovalsService([
                'staff_id' => $approver['staff_id'],
                'division_id' => $approver['division_id'],
                'permissions' => [],
                'name' => $approver['fname'] . ' ' . $approver['lname'],
                'email' => $approver['work_email'],
                'base_url' => config('app.url')
            ]);

            $summaryStats = $pendingApprovalsService->getSummaryStats();

            // Only create notification if there are pending items
            if ($summaryStats['total_pending'] > 0) {
                $notification = $this->createNotification([
                    'staff_id' => $approver['staff_id'],
                    'model_id' => null,
                    'model_type' => null,
                    'message' => "You have {$summaryStats['total_pending']} pending approval(s) requiring your attention.",
                    'title' => 'Pending approvals',
                    'type' => 'daily_pending_approvals',
                    'send_email' => true,
                    'pending_approvals' => $pendingApprovalsService->getPendingApprovals(),
                    'summary_stats' => $summaryStats
                ]);

                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Remind approvers whose queue contains items received at their level more than N days ago (N = approval_warning_days, default 7).
     * Scheduled daily at 11:00; each run only notifies approvers who still have such items (stops once cleared).
     *
     * @return list<Notification>
     */
    public function createStalePendingApprovalsReminders(): array
    {
        $approvers = $this->getAllApprovers();
        $notifications = [];

        foreach ($approvers as $approver) {
            $pendingApprovalsService = new PendingApprovalsService([
                'staff_id' => $approver['staff_id'],
                'division_id' => $approver['division_id'],
                'permissions' => [],
                'name' => $approver['fname'] . ' ' . $approver['lname'],
                'email' => $approver['work_email'],
                'base_url' => config('app.url'),
            ]);

            $days = $pendingApprovalsService->getApprovalWarningThresholdDays();
            $stale = $pendingApprovalsService->getStalePendingItems($days);

            if (count($stale) === 0) {
                continue;
            }

            $count = count($stale);
            $pendingUrl = url(route('pending-approvals.index', [], false));
            $message = "Gentle reminder: {$count} pending item(s) at your level for more than {$days} day(s). Please return for archiving or approve to advance the trail.";

            $notifications[] = $this->createNotification([
                'staff_id' => $approver['staff_id'],
                'model_id' => null,
                'model_type' => null,
                'message' => $message,
                'title' => 'Pending approval reminder',
                'type' => 'stale_pending_approvals_reminder',
                'send_email' => true,
                'email_view_context' => [
                    'stalePendingItems' => $stale,
                    'approvalWarningDays' => $days,
                    'pendingApprovalsUrl' => $pendingUrl,
                    'staleCount' => $count,
                ],
            ]);
        }

        return $notifications;
    }

    /**
     * Escalate stale pending items to creator, HOD, and senior/configured approvers (all workflows).
     *
     * @return list<Notification>
     */
    public function createStaleApprovalEscalations(): array
    {
        return app(StaleApprovalEscalationService::class)->createEscalationNotifications();
    }

    /**
     * Remind memo creators about stale draft documents that still hold budget (see budget_stale_draft_reminders_enabled).
     *
     * @return list<Notification>
     */
    public function createStaleDraftMemosReminders(): array
    {
        $settings = new BudgetCommitmentSettings();
        if (! $settings->staleDraftRemindersEnabled() || $settings->draftBudgetCutoff() === null) {
            return [];
        }

        $service = new StaleDraftMemosService($settings);
        $staffIds = $service->staffIdsWithStaleDrafts();
        $notifications = [];
        $months = $settings->draftMaxAgeMonths();

        foreach ($staffIds as $staffId) {
            $items = $service->getStaleDraftsForStaff((int) $staffId);
            if ($items === []) {
                continue;
            }

            $count = count($items);
            $schedule = new \App\Services\StaleDraftArchiveSchedule();
            $nextRun = $schedule->nextWeeklyRun()->format('l, j F Y \a\t H:i');
            $message = "You have {$count} stale draft memo(s) older than {$months} month(s) with budget allocated. "
                . 'Please delete or submit them to release fund code balances.';
            if ((new BudgetCommitmentSettings())->staleDraftAutoArchiveEnabled()) {
                $message .= " Unacted stale drafts are auto-archived weekly (next run: {$nextRun}).";
            }

            $notifications[] = $this->createNotification([
                'staff_id' => (int) $staffId,
                'model_id' => null,
                'model_type' => null,
                'message' => $message,
                'title' => 'Stale draft memos',
                'type' => 'stale_draft_memos_reminder',
                'send_email' => true,
                'email_view_context' => [
                    'staleDraftItems' => $items,
                    'staleCount' => $count,
                    'draftMaxAgeMonths' => $months,
                    'nextWeeklyArchiveRun' => (new BudgetCommitmentSettings())->staleDraftAutoArchiveEnabled()
                        ? (new \App\Services\StaleDraftArchiveSchedule())->nextWeeklyRun()->format('l, j F Y \a\t H:i')
                        : null,
                ],
            ]);
        }

        return $notifications;
    }

    /**
     * Dispatch email notification to queue
     */
    private function dispatchEmailNotification(Notification $notification, array $data): void
    {
        try {
            // Get the recipient staff member
            $recipient = Staff::find($notification->staff_id);
            if (!$recipient) {
                Log::warning('Recipient not found for notification', [
                    'notification_id' => $notification->id,
                    'staff_id' => $notification->staff_id
                ]);
                return;
            }

            // Extract the model from the notification (can be null for daily notifications)
            $model = $this->getModelFromNotification($notification);

            $template = match ($notification->type) {
                'daily_pending_approvals' => 'emails.daily-pending-approvals-notification',
                'stale_pending_approvals_reminder' => 'emails.stale-pending-approvals-reminder',
                'stale_pending_approvals_escalation' => 'emails.stale-pending-approvals-escalation',
                'stale_draft_memos_reminder' => 'emails.stale-draft-memos-reminder',
                default => 'emails.generic-notification',
            };

            $emailViewContext = $data['email_view_context'] ?? [];

            $job = new SendNotificationEmailJob(
                $model,
                $recipient,
                $notification->type ?? 'notification',
                $notification->message ?? 'You have a new notification',
                $template,
                is_array($emailViewContext) ? $emailViewContext : []
            );

            $isStaleApprovalMail = in_array($notification->type, ['stale_pending_approvals_escalation', 'stale_pending_approvals_reminder'], true);

            // Stale approval alerts must not sit behind a long general notification queue.
            if ($isStaleApprovalMail) {
                dispatch_sync($job);
            } else {
                dispatch($job)->onQueue('default')->delay(now()->addSeconds(5));
            }

            Log::info('Email notification job dispatched', [
                'notification_id' => $notification->id,
                'staff_id' => $notification->staff_id,
                'type' => $notification->type,
                'sync' => $isStaleApprovalMail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch email notification job', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the model instance from notification
     */
    private function getModelFromNotification(Notification $notification)
    {
        // For daily notifications, model can be null
        if (!$notification->model_type || !$notification->model_id) {
            return null;
        }

        try {
            return $notification->model_type::find($notification->model_id);
        } catch (\Exception $e) {
            Log::error('Failed to load model from notification', [
                'notification_id' => $notification->id,
                'model_type' => $notification->model_type,
                'model_id' => $notification->model_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all staff who are approvers
     */
    public function getAllApprovers(): array
    {
        $approvers = [];

        // 1. Division-linked roles that receive daily pending-approval digests
        $divisionApprovers = \DB::table('divisions')
            ->select('division_head as staff_id')
            ->whereNotNull('division_head')
            ->union(
                \DB::table('divisions')
                    ->select('focal_person as staff_id')
                    ->whereNotNull('focal_person')
            )
            ->union(
                \DB::table('divisions')
                    ->select('director_id as staff_id')
                    ->whereNotNull('director_id')
            )
            ->union(
                \DB::table('divisions')
                    ->select('admin_assistant as staff_id')
                    ->whereNotNull('admin_assistant')
            )
            ->union(
                \DB::table('divisions')
                    ->select('finance_officer as staff_id')
                    ->whereNotNull('finance_officer')
            )
            ->get()
            ->pluck('staff_id')
            ->unique()
            ->filter()
            ->toArray();

        // 2. Get regular approvers from approvers table
        $regularApprovers = \DB::table('approvers')
            ->distinct()
            ->pluck('staff_id')
            ->toArray();

        // 3. Combine and get unique staff IDs
        $allApproverIds = array_unique(array_merge($divisionApprovers, $regularApprovers));

        // 4. Get staff details for all approvers
        $approvers = Staff::whereIn('staff_id', $allApproverIds)
            ->where('active', 1)
            ->whereNotNull('work_email')
            ->get()
            ->toArray();

        return $approvers;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): bool
    {
        return $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Get unread notifications for a staff member
     */
    public function getUnreadNotifications(int $staffId): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('staff_id', $staffId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get notification count for a staff member
     */
    public function getNotificationCount(int $staffId): int
    {
        return Notification::where('staff_id', $staffId)
            ->where('is_read', false)
            ->count();
    }
}
