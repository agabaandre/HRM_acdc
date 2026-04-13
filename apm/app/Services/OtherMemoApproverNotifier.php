<?php

namespace App\Services;

use App\Jobs\SendNotificationEmailJob;
use App\Models\ApmApiUser;
use App\Models\OtherMemo;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the current other-memo approver (FCM + email) when a memo is submitted or advances in sequence.
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

        $doc = $memo->document_number ? $memo->document_number . ' — ' : '';
        $typeName = $memo->memo_type_name_snapshot ?? 'Other memo';
        $message = $doc . $typeName . ' is waiting for your approval.';
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

        if (! empty($staff->work_email)) {
            $emailBody = $message . ' Open: ' . $openUrl;
            SendNotificationEmailJob::dispatch(
                $memo,
                $staff,
                'other_memo_approval',
                $emailBody,
                'emails.generic-notification'
            );
        }
    }
}
