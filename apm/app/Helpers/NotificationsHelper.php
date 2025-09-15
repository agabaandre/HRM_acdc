<?php
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Models\Staff;
use App\Models\Matrix;
use Carbon\Carbon;
use App\Mail\MatrixNotification;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;

if (!function_exists('get_matrix_notification_recipient')) {
    /**
     * Get the staff member who should be notified for matrix approval
     * 
     * @param Modal $matrix
     * @return Staff|null
     */
    function get_matrix_notification_recipient($matrix)
    {
        if ($matrix->overall_status === 'approved') {
            return null;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $matrix->approval_level)
            ->where('workflow_id', $matrix->forward_workflow_id)
            ->first();

        if (!$current_approval_point) {
            return null;
        }

        // Check for regular approvers first
        $approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->first();

        if ($approver) {
            return Staff::where('staff_id', $approver->staff_id)->first();
        }

        // Check for OIC approvers
        $oic_approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where('oic_staff_id', '!=', null)
            ->where('end_date', '>=', $today)
            ->first();

        if ($oic_approver) {
            return Staff::where('staff_id', $oic_approver->oic_staff_id)->first();
        }

        // Check for division-specific approvers
        if ($current_approval_point->is_division_specific) {
            $division = $matrix->division;
            if ($division && $division->{$current_approval_point->division_reference_column}) {
                return Staff::where('staff_id', $division->{$current_approval_point->division_reference_column})->first();
            }
        }

        return null;
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
         sendMatrixNotificationWithJob( $model, $type);
         return true;
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
