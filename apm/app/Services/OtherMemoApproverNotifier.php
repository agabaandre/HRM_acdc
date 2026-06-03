<?php

namespace App\Services;

use App\Jobs\SendNotificationEmailJob;
use App\Models\ApmApiUser;
use App\Models\Notification;
use App\Models\OtherMemo;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the current other-memo approver (FCM + email + in-app) when a memo is submitted or advances in sequence.
 * Runs during the submit/approve HTTP request — not via a scheduled job.
 */
class OtherMemoApproverNotifier
{
    public static function notifyCurrentApprover(?int $approverStaffId, OtherMemo $memo): void
    {
        if (! $approverStaffId || $approverStaffId <= 0) {
            return;
        }

        $staff = Staff::query()->where('staff_id', $approverStaffId)->where('active', 1)->first();
        if (! $staff) {
            return;
        }

        $presented = MemoApprovalNotificationPresenter::forOtherMemoAwaitingApproval($memo);
        $message = $presented['message'];
        $openUrl = url(route('other-memos.show', $memo, false));

        $fcm = app(FirebaseMessagingService::class);
        $apiUser = ApmApiUser::query()
            ->where('auth_staff_id', $approverStaffId)
            ->where('status', true)
            ->first();

        if ($apiUser && ! empty($apiUser->firebase_token) && $fcm->isConfigured()) {
            try {
                $fcm->sendToToken(
                    $apiUser->firebase_token,
                    'Other memo — approval needed',
                    $message,
                    [
                        'type' => 'other_memo_approval',
                        'other_memo_id' => (string) $memo->id,
                        'url' => $openUrl,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Other memo FCM notify failed', ['error' => $e->getMessage(), 'memo_id' => $memo->id]);
            }
        }

        Notification::create([
            'staff_id' => (int) $approverStaffId,
            'model_id' => $memo->id,
            'model_type' => OtherMemo::class,
            'message' => $message,
            'type' => 'other_memo_approval',
            'is_read' => false,
        ]);

        if (! empty($staff->work_email)) {
            SendNotificationEmailJob::dispatch(
                $memo,
                $staff,
                'approved',
                $message,
                'emails.matrix-notification',
                array_merge($presented['view'], ['skip_admin_cc' => true])
            )->afterResponse();
        }
    }
}
