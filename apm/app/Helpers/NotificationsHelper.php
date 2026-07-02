<?php
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Models\Staff;
use App\Models\Matrix;
use Carbon\Carbon;
use App\Mail\MatrixNotification;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

if (!function_exists('get_matrix_notification_recipient')) {
    /**
     * Get the staff member who should be notified for matrix approval
     * This should return the NEXT approver, not the current one
     * 
     * @param Model $model
     * @return Staff|null
     */
    function get_matrix_notification_recipient($model)
    {
        if ($model->overall_status === 'approved') {
            return null;
        }

        // Use the ApprovalService to get the next approver
        $approvalService = new \App\Services\ApprovalService();
        $nextApprover = $approvalService->getNextApprover($model);
        
        if (!$nextApprover) {
            return null;
        }

        // Get the actual staff member who should receive the notification
        return $approvalService->getNotificationRecipient($model);
    }
}

if (!function_exists('matrix_notification_view_data')) {
    /**
     * @return array<string, mixed>
     */
    function matrix_notification_view_data($matrix, Staff $recipient, string $type, string $message): array
    {
        $matrix->loadMissing(['staff', 'division', 'focalPerson']);

        $divisionName = trim((string) ($matrix->division->division_name ?? $matrix->division->name ?? ''));
        if ($divisionName === '') {
            $divisionName = 'N/A';
        }

        $keyResultAreas = [];
        $kra = $matrix->key_result_area;
        if (is_string($kra)) {
            $decoded = json_decode($kra, true);
            $kra = is_array($decoded) ? $decoded : [];
        }
        if (is_array($kra)) {
            foreach ($kra as $item) {
                $desc = is_array($item) ? ($item['description'] ?? '') : (string) $item;
                $desc = trim($desc);
                if ($desc !== '') {
                    $keyResultAreas[] = $desc;
                }
            }
        }

        $createdBy = trim(($matrix->staff->fname ?? '').' '.($matrix->staff->lname ?? ''));
        $focalPerson = $matrix->focalPerson
            ? trim($matrix->focalPerson->fname.' '.$matrix->focalPerson->lname)
            : null;

        return [
            'resource' => $matrix,
            'resource_type' => ucfirst(class_basename($matrix)),
            'recipient' => $recipient,
            'message' => $message,
            'type' => $type,
            'division_name' => $divisionName,
            'matrix_period' => trim(($matrix->quarter ?? '').' '.($matrix->year ?? '')),
            'matrix_display_title' => method_exists($matrix, 'listDisplayTitle')
                ? $matrix->listDisplayTitle()
                : ('Matrix #'.($matrix->id ?? '')),
            'created_by_name' => $createdBy !== '' ? $createdBy : 'N/A',
            'focal_person_name' => $focalPerson,
            'key_result_areas' => $keyResultAreas,
            'matrix_url' => $matrix->resource_url,
        ];
    }
}

if (!function_exists('matrix_notification_subject')) {
    function matrix_notification_subject($matrix, string $type): string
    {
        $prefix = env('MAIL_SUBJECT_PREFIX', 'Africa CDC APM').': ';
        $resource = ucfirst(class_basename($matrix));
        $divisionName = trim((string) ($matrix->division->division_name ?? $matrix->division->name ?? ''));

        switch ($type) {
            case 'created':
                if ($divisionName !== '') {
                    return $prefix.sprintf(
                        'New Quarterly Travel Matrix — %s (%s %s)',
                        $divisionName,
                        $matrix->quarter ?? '',
                        $matrix->year ?? ''
                    );
                }

                return $prefix.sprintf(
                    'New Quarterly Travel Matrix (%s %s)',
                    $matrix->quarter ?? '',
                    $matrix->year ?? ''
                );
            case 'approval':
            case 'matrix_approval':
                return $prefix.$resource.' Approval Required';
            case 'returned':
            case 'matrix_returned':
                return $prefix.$resource.' Returned for Revision';
            default:
                return $prefix.$resource.' Notification';
        }
    }
}

if (!function_exists('render_matrix_notification_email')) {
    function render_matrix_notification_email($matrix, Staff $recipient, string $type, string $message): string
    {
        return view('emails.matrix-notification', matrix_notification_view_data($matrix, $recipient, $type, $message))->render();
    }
}

if (!function_exists('send_matrix_notification')) {
    /**
     * Send a notification to the appropriate staff member for matrix approval
     * This will create a database notification and send an email
     * 
     * @param Model $matrix
     * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned', etc.)
     * @return Notification|null
     */
    function send_matrix_notification( $model, $type = 'approval',$recipients = null)
    {
        $recipient = $recipients ? $recipients : get_matrix_notification_recipient($model);

        if (!$recipient) {
            return null;
        }

        $model->loadMissing(['staff', 'division', 'focalPerson']);

        $divisionName = trim((string) ($model->division->division_name ?? $model->division->name ?? 'your division'));
        if ($divisionName === '') {
            $divisionName = 'your division';
        }

        $message = '';
        $resource = ucfirst(class_basename($model));
        switch($type) {
            case 'approval':
                $message = sprintf(
                    '%s #%d requires your approval. Created by %s %s.',
                    $resource,
                    $model->id,
                    $model->staff->fname,
                    $model->staff->lname
                );
                break;
            case 'created':
                $message = sprintf(
                    'A new quarterly travel matrix for %s (%s %s) has been created by %s %s. Division members are invited to add their planned activities.',
                    $divisionName,
                    $model->quarter,
                    $model->year,
                    $model->staff->fname,
                    $model->staff->lname
                );
                break;
            case 'returned':
                $message = sprintf(
                    '%s #%d has been returned for revision by %s %s.',
                    $resource,
                    $model->id,
                    $model->staff->fname,
                    $model->staff->lname
                );
                break;
            default:
                $message = sprintf(
                    '%s #%d requires your attention.',
                    $resource,
                    $model->id
                );
        }

        $recipientsList = ($recipient instanceof Staff || ! is_iterable($recipient))
            ? collect([$recipient])
            : collect($recipient);

        $recipientsWithEmail = $recipientsList->filter(function ($staffMember) {
            return $staffMember instanceof Staff && ! empty($staffMember->work_email);
        })->values();

        if ($recipientsWithEmail->isEmpty()) {
            return null;
        }

        dispatchMatrixNotificationJob($model, $recipientsWithEmail, $type, $message);

        return true;
    }
}

if (!function_exists('send_matrix_email_notification')) {
    /**
     * Send an email notification for matrix approval
     * 
     * @param Model $model
     * @param string $type The type of notification
     * @return bool
     */
    function send_matrix_email_notification($model, $type = 'approval')
    {
        try {
            // Use the centralized email system
            $recipient = get_matrix_notification_recipient($model);
            
            if (!$recipient || !$recipient->work_email) {
                return false;
            }

            // Generate message based on type
            $message = '';
            $resource = ucfirst(class_basename($model));
            switch($type) {
                case 'approval':
                    $message = sprintf(
                        '%s #%d requires your approval. Created by %s %s.',
                        $resource,
                        $model->id,
                        $model->staff->fname,
                        $model->staff->lname
                    );
                    break;
                case 'returned':
                    $message = sprintf(
                        '%s #%d has been returned for revision by %s %s.',
                        $resource,
                        $model->id,
                        $model->staff->fname,
                        $model->staff->lname
                    );
                    break;
                default:
                    $message = sprintf(
                        '%s #%d requires your attention.',
                        $resource,
                        $model->id
                    );
            }

            // Send email using centralized system
            return sendMatrixNotification($model, $recipient, $type, $message);
        } catch (Exception $e) {
            // Log the error but don't break the approval process
            Log::error('Email notification failed', [
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
        return true;
    }
}

if (!function_exists('send_generic_email_notification')) {
    /**
     * Send an email notification using the appropriate template based on model type
     * 
     * @param Model $model
     * @param string $type The type of notification
     * @return bool
     */
    function send_generic_email_notification($model, $type = 'approval')
    {
        try {
            // Get the recipient
            $recipient = get_matrix_notification_recipient($model);
            
            if (!$recipient || !$recipient->work_email) {
                return false;
            }

            $model->loadMissing(['staff', 'division']);

            // Generate message based on type
            $message = '';
            $emailViewContext = [];
            $resource = ucfirst(class_basename($model));
            switch ($type) {
                case 'approved':
                    if (($model->overall_status ?? '') === 'pending') {
                        $forward = \App\Services\MemoApprovalNotificationPresenter::forForwardToNextApprover($model);
                        $message = $forward['message'];
                        $emailViewContext = $forward['view'];
                    } else {
                        $message = sprintf(
                            '%s #%d has been fully approved.',
                            $resource,
                            $model->id
                        );
                    }
                    break;
                case 'approval':
                    $message = sprintf(
                        '%s #%d requires your approval. Created by %s %s.',
                        $resource,
                        $model->id,
                        $model->staff->fname,
                        $model->staff->lname
                    );
                    break;
                case 'returned':
                    $message = sprintf(
                        '%s #%d has been returned for revision by %s %s.',
                        $resource,
                        $model->id,
                        $model->staff->fname,
                        $model->staff->lname
                    );
                    break;
                case 'submitted':
                    $message = sprintf(
                        '%s #%d has been submitted for approval by %s %s.',
                        $resource,
                        $model->id,
                        $model->staff->fname,
                        $model->staff->lname
                    );
                    break;
                default:
                    $message = sprintf(
                        '%s #%d requires your attention.',
                        $resource,
                        $model->id
                    );
            }

            // Determine the appropriate email template based on model type
            $modelClass = get_class($model);
            $template = 'emails.generic-notification'; // Default template
            
            switch ($modelClass) {
                case 'App\Models\Matrix':
                    $template = 'emails.matrix-notification';
                    break;
                case 'App\Models\RequestARF':
                    $template = 'emails.arf-notification';
                    break;
                case 'App\Models\SpecialMemo':
                    $template = 'emails.special-memo-notification';
                    break;
                case 'App\Models\NonTravelMemo':
                    $template = 'emails.matrix-notification'; // Use matrix template for Non-Travel Memo
                    break;
                case 'App\Models\Activity':
                    $template = 'emails.matrix-notification'; // Use matrix template for Single Memo
                    break;
                case 'App\Models\ServiceRequest':
                    $template = 'emails.matrix-notification'; // Use matrix template for Service Request
                    break;
                case 'App\Models\ChangeRequest':
                    $template = 'emails.matrix-notification'; // Use matrix template for Change Request
                    break;
                default:
                    $template = 'emails.generic-notification';
            }

            // Queue the email notification instead of sending directly
            \App\Jobs\SendNotificationEmailJob::dispatch($model, $recipient, $type, $message, $template, $emailViewContext);
            
            // Also create a database notification
            \App\Models\Notification::create([
                'staff_id' => $recipient->staff_id,
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'message' => $message,
                'type' => $type,
                'is_read' => false
            ]);
            
            return true;

        } catch (Exception $e) {
            // Log the error but don't break the approval process
            Log::error('Generic email notification failed', [
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

if (!function_exists('mark_matrix_notifications_read')) {
    /**
     * Mark all notifications as read for a staff member on a specific matrix
     * 
     * @param int $staff_id The staff ID
     * @param int $matrix_id The matrix ID
     * @return int Number of notifications marked as read
     */
    function mark_matrix_notifications_read($staff_id, $matrix_id)
    {
        return Notification::where('staff_id', $staff_id)
            ->where('model_id', $matrix_id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }
}

if (!function_exists('get_staff_unread_notifications_count')) {
    /**
     * Get the count of unread notifications for a staff member
     * 
     * @param int $staff_id The staff ID
     * @param string|null $type Optional notification type to filter by
     * @return int Number of unread notifications
     */
    function get_staff_unread_notifications_count( $type = null)
    {
        $user = session('user', []);
        $staff_id = $user['staff_id'];
        $query = Notification::where('staff_id', $staff_id)
            ->where('is_read', false);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->count();
    }
}
