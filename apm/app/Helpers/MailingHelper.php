<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Mail\MatrixNotification;
use App\Models\Matrix;
use App\Models\Staff;
use Illuminate\Support\Facades\View;
use App\Jobs\SendMatrixNotificationJob;
use Illuminate\Database\Eloquent\Model;


/**
 * Send matrix notification email using custom PHPMailer but with the same view template
 * 
 * @param Model $matrix The matrix object
 * @param Staff $recipient The recipient staff member
 * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned')
 * @param string $message The notification message
 * @return bool
 */
function sendMatrixNotificationWithPHPMailer( $matrix, Staff $recipient, string $type, string $message)
{
    // Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0;                                    // Disable debug output for production
        $mail->isSMTP();                                         // Send using SMTP
        $mail->Host       = env('MAIL_HOST');                    // Set the SMTP server from env
        $mail->SMTPAuth   = true;                                // Enable SMTP authentication
        $mail->Username   = env('MAIL_USERNAME');                // SMTP username from env
        $mail->Password   = env('MAIL_PASSWORD');                // SMTP password from env
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable implicit TLS encryption
        $mail->Port       = env('MAIL_PORT');                    // TCP port from env

        // Recipients
        $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME', 'Africa CDC APM'));
        $mail->addAddress($recipient->work_email, $recipient->fname . ' ' . $recipient->lname);

        // Set subject based on type (same logic as MatrixNotification.php)
        $subject = '';
        switch($type) {
            case 'matrix_approval':
                $subject = 'Matrix Approval Required';
                break;
            case 'matrix_returned':
                $subject = 'Matrix Returned for Revision';
                break;
            default:
                $subject = 'Matrix Notification';
        }
        $mail->Subject = $subject;

        // Render the same view template that MatrixNotification uses
        $htmlContent = View::make('emails.matrix-notification', [
            'matrix' => $matrix,
            'recipient' => $recipient,
            'message' => $message,
            'type' => $type,
        ])->render();

        // Content
        $mail->isHTML(true);
        $mail->Body = $htmlContent;
        
        // Create plain text version
        $plainText = strip_tags($htmlContent);
        $mail->AltBody = $plainText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        \Log::error('Failed to send matrix notification email with PHPMailer: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send matrix notification with automatic recipient detection using custom PHPMailer
 * This function combines the logic from NotificationsHelper.php but uses PHPMailer
 * 
 * @param Model $matrix The matrix object
 * @param string $type The type of notification
 * @return bool
 */
function sendMatrixNotificationCustom( $matrix, string $type = 'matrix_approval')
{
    // Get the recipient using the existing helper function
    $recipient = get_matrix_notification_recipient($matrix);
    
    if (!$recipient || !$recipient->work_email) {
        return false;
    }

    // Generate message based on type
    $message = '';
    switch($type) {
        case 'matrix_approval':
            $message = sprintf(
                'Matrix #%d requires your approval. Created by %s %s.',
                $matrix->id,
                $matrix->staff->fname,
                $matrix->staff->lname
            );
            break;
        case 'matrix_returned':
            $message = sprintf(
                'Matrix #%d has been returned for revision by %s %s.',
                $matrix->id,
                $matrix->staff->fname,
                $matrix->staff->lname
            );
            break;
        default:
            $message = sprintf(
                'Matrix #%d requires your attention.',
                $matrix->id
            );
    }

    // Create notification record
    \App\Models\Notification::create([
        'staff_id' => $recipient->staff_id,
        'matrix_id' => $matrix->id,
        'message' => $message,
        'type' => $type,
        'is_read' => false
    ]);

    // Send the email using custom PHPMailer
    return sendMatrixNotificationWithPHPMailer($matrix, $recipient, $type, $message);
}

/**
 * Dispatch matrix notification job to send email in background
 * 
 * @param Model $matrix The matrix object
 * @param Staff $recipient The recipient staff member
 * @param string $type The type of notification
 * @param string $message The notification message
 * @return void
 */
function dispatchMatrixNotificationJob( $matrix, $recipient, string $type, string $message)
{
    SendMatrixNotificationJob::dispatch($matrix, $recipient, $type, $message);
}

/**
 * Send matrix notification with automatic recipient detection using background job
 * This function combines the logic from NotificationsHelper.php but uses a job
 * 
 * @param Model $matrix The matrix object
 * @param string $type The type of notification
 * @return bool
 */
function sendMatrixNotificationWithJob( $model, string $type = 'approval')
{
    // Get all staff members in the division
    $recipients = collect();
    
    if (method_exists($model, 'division') && $model->division) {
        $recipients = \App\Models\Staff::where('division_id', $model->division_id)
            ->where('active', 1)
            ->whereNotNull('work_email')
            ->get();
    }
    
    if ($recipients->isEmpty()) {
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

    // Dispatch the job to send email in background to all division staff
    dispatchMatrixNotificationJob($model, $recipients, $type, $message);
    
    return true;
}
