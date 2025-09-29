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
     * Dispatch email notification to queue
     */
    private function dispatchEmailNotification(Notification $notification, array $data): void
    {
        try {
            SendNotificationEmailJob::dispatch($notification, $data)
                ->onQueue('notifications')
                ->delay(now()->addSeconds(5)); // Small delay to prevent overwhelming the queue

            Log::info('Email notification job dispatched', [
                'notification_id' => $notification->id,
                'staff_id' => $notification->staff_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch email notification job', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get all staff who are approvers
     */
    private function getAllApprovers(): array
    {
        $approvers = [];

        // 1. Get division-specific approvers from divisions table
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
