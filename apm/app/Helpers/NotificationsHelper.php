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
                case 'created':
                        $message = sprintf(
                            '%s #%d has been created by %s %s.',
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

            // Dispatch the job to send email in background
        dispatchMatrixNotificationJob($model, $recipient, $type, $message);

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
            \App\Jobs\SendNotificationEmailJob::dispatch($model, $recipient, $type, $message, $template);
            
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
