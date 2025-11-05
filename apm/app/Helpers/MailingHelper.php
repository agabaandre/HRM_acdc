<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Mail\MatrixNotification;
use App\Models\Matrix;
use App\Models\Staff;
use Illuminate\Support\Facades\View;
use App\Jobs\SendMatrixNotificationJob;
use Illuminate\Database\Eloquent\Model;
use AgabaandreOffice365\ExchangeEmailService\ExchangeEmailService;
use Illuminate\Support\Facades\Mail;

// ============================================================================
// CENTRALIZED EMAIL DISPATCHER - PRIMARY ENTRY POINT FOR ALL EMAILS
// ============================================================================

/**
 * Central email dispatcher - ALL emails should go through this function
 * This ensures Exchange is used as primary method with PHPMailer fallback
 * 
 * Automatically adds system@africacdc.org as BCC for audit purposes
 * 
 * @param string|array $to Email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $fromEmail From email address
 * @param string $fromName From name
 * @param array $cc CC recipients
 * @param array $bcc BCC recipients (system@africacdc.org will be added automatically)
 * @param array $attachments Attachments
 * @return bool
 */
function sendEmail($to, $subject, $body, $fromEmail = null, $fromName = null, $cc = [], $bcc = [], $attachments = [])
{
    // Always add system@africacdc.org as BCC for audit purposes
    $systemBcc = 'system@africacdc.org';
    if (!in_array($systemBcc, $bcc)) {
        $bcc[] = $systemBcc;
    }
    
    // Use Exchange exclusively - no fallbacks
    return sendEmailWithExchange($to, $subject, $body, $fromEmail, $fromName, $cc, $bcc, $attachments);
}

/**
 * Send email using Exchange service (Office 365)
 * 
 * @param string|array $to Email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $fromEmail From email address
 * @param string $fromName From name
 * @param array $cc CC recipients
 * @param array $bcc BCC recipients
 * @param array $attachments Attachments
 * @return bool
 */
function sendEmailWithExchange($to, $subject, $body, $fromEmail = null, $fromName = null, $cc = [], $bcc = [], $attachments = [])
{
    try {
        $config = config('exchange-email');
        
        // Use the working implementation from local ExchangeEmailService
        require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
        
        $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
            $config['tenant_id'],
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri'] ?? 'http://localhost:8000/oauth/callback',
            'https://graph.microsoft.com/.default', // Correct scope for client credentials
            'client_credentials' // Force client credentials
        );
        
        if (!$oauth->isConfigured()) {
            \Log::error('Exchange service not configured, falling back to PHPMailer');
            return sendEmailWithPHPMailer($to, $subject, $body, $fromEmail, $fromName, $cc, $bcc, $attachments);
        }
        
        // Get client credentials token
        $oauth->getClientCredentialsToken();
        
        return $oauth->sendEmail(
            $to,
            $subject,
            $body,
            true, // HTML email
            $fromEmail ?: env('MAIL_FROM_ADDRESS'),
            $fromName ?: env('MAIL_FROM_NAME', 'Africa CDC APM'),
            $cc,
            $bcc,
            $attachments
        );
        
    } catch (Exception $e) {
        \Log::error('Exchange email failed - Exchange is required for all emails: ' . $e->getMessage());
        throw new \Exception('Exchange email failed: ' . $e->getMessage());
    }
}

/**
 * Send email using PHPMailer (fallback method)
 * 
 * @param string|array $to Email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $fromEmail From email address
 * @param string $fromName From name
 * @param array $cc CC recipients
 * @param array $bcc BCC recipients
 * @param array $attachments Attachments
 * @return bool
 */
function sendEmailWithPHPMailer($to, $subject, $body, $fromEmail = null, $fromName = null, $cc = [], $bcc = [], $attachments = [])
{
    $mail = new PHPMailer(true);

    try {
        // Server settings for PHPMailer SMTP (separate from Exchange)
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = env('PHPMailer_HOST', env('MAIL_HOST'));
        $mail->SMTPAuth   = true;
        $mail->Username   = env('PHPMailer_USERNAME', env('MAIL_USERNAME'));
        $mail->Password   = env('PHPMailer_PASSWORD', env('MAIL_PASSWORD'));
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = env('PHPMailer_PORT', env('MAIL_PORT', 587));

        // Recipients
        $mail->setFrom(
            $fromEmail ?: env('PHPMailer_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')), 
            $fromName ?: env('PHPMailer_FROM_NAME', env('MAIL_FROM_NAME', 'Africa CDC APM'))
        );

        // Handle multiple recipients
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }

        // Add CC recipients
        foreach ($cc as $email) {
            $mail->addCC($email);
        }

        // Add BCC recipients
        foreach ($bcc as $email) {
            $mail->addBCC($email);
        }

        // Add attachments
        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $mail->addAttachment($attachment);
            } elseif (is_array($attachment)) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        \Log::error('PHPMailer email failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// CONVENIENCE FUNCTIONS FOR COMMON EMAIL TYPES
// ============================================================================

/**
 * Send a simple text/HTML email
 * 
 * @param string|array $to Email address(es)
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $fromEmail From email address
 * @param string $fromName From name
 * @return bool
 */
function sendSimpleEmail($to, $subject, $message, $fromEmail = null, $fromName = null)
{
    $body = "<p>{$message}</p><p>Best regards,<br>Africa CDC APM System</p>";
    return sendEmail($to, $subject, $body, $fromEmail, $fromName);
}

/**
 * Send email using a specific template
 * 
 * @param string $to Email address
 * @param string $template Template name (e.g., 'emails.matrix-notification')
 * @param array $data Template data
 * @param string $subject Email subject (optional, will be generated from template)
 * @return bool
 */
function sendEmailWithTemplate($to, $template, $data = [], $subject = null)
{
    try {
        // Generate subject if not provided
        if (!$subject) {
            $resourceType = $data['resource_type'] ?? 'Document';
            $type = $data['type'] ?? 'notification';
            
            switch($type) {
                case 'approval':
                    $subject = "{$resourceType} Approval Required - Africa CDC";
                    break;
                case 'returned':
                    $subject = "{$resourceType} Returned for Revision - Africa CDC";
                    break;
                case 'submitted':
                    $subject = "{$resourceType} Submitted for Approval - Africa CDC";
                    break;
                default:
                    $subject = "{$resourceType} Notification - Africa CDC";
            }
        }

        // Render the template
        $htmlContent = View::make($template, $data)->render();
        
        // Send email using centralized system
        return sendEmail($to, $subject, $htmlContent);
        
    } catch (Exception $e) {
        \Log::error('Template email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send notification email to staff member
 * 
 * @param Staff $staff Staff member
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $type Notification type
 * @return bool
 */
function sendStaffNotification(Staff $staff, $subject, $message, $type = 'notification')
{
    if (!$staff->work_email) {
        \Log::warning("No email address for staff ID: {$staff->staff_id}");
        return false;
    }

    $body = "
    <h2>APM Notification</h2>
    <p>Dear {$staff->fname} {$staff->lname},</p>
    <p>{$message}</p>
    <p>Best regards,<br>Africa CDC APM System</p>
    ";

    return sendEmail($staff->work_email, $subject, $body);
}

/**
 * Send daily pending approvals email
 * 
 * @param Staff $staff Staff member
 * @param array $pendingApprovals Pending approvals data
 * @param array $summaryStats Summary statistics
 * @return bool
 */
function sendDailyPendingApprovalsEmail(Staff $staff, $pendingApprovals, $summaryStats)
{
    if (!$staff->work_email) {
        return false;
    }

    $subject = 'Daily Pending Approvals Summary - ' . now()->format('M d, Y');
    
    $body = "
    <h2>Daily Pending Approvals Summary</h2>
    <p>Dear {$staff->fname} {$staff->lname},</p>
    <p>Here is your daily summary of pending approvals:</p>
    
    <h3>Summary Statistics</h3>
    <ul>
        <li>Total Pending: {$summaryStats['total']}</li>
        <li>Matrices: {$summaryStats['matrices']}</li>
        <li>Service Requests: {$summaryStats['service_requests']}</li>
        <li>ARF Requests: {$summaryStats['arf_requests']}</li>
    </ul>
    
    <p>Please review and take appropriate action on these pending items.</p>
    <p>Best regards,<br>Africa CDC APM System</p>
    ";

    return sendEmail($staff->work_email, $subject, $body);
}

/**
 * Send generic notification email
 * 
 * @param Staff $staff Staff member
 * @param string $title Email title
 * @param string $message Email message
 * @param array $data Additional data
 * @return bool
 */
function sendGenericNotificationEmail(Staff $staff, $title, $message, $data = [])
{
    if (!$staff->work_email) {
        return false;
    }

    $subject = $title;
    
    $body = "
    <h2>{$title}</h2>
    <p>Dear {$staff->fname} {$staff->lname},</p>
    <p>{$message}</p>
    ";

    // Add additional data if provided
    if (!empty($data)) {
        $body .= "<h3>Details:</h3><ul>";
        foreach ($data as $key => $value) {
            $body .= "<li><strong>{$key}:</strong> {$value}</li>";
        }
        $body .= "</ul>";
    }

    $body .= "<p>Best regards,<br>Africa CDC APM System</p>";

    return sendEmail($staff->work_email, $subject, $body);
}

// ============================================================================
// MATRIX NOTIFICATION FUNCTIONS (UPDATED TO USE CENTRAL DISPATCHER)
// ============================================================================

/**
 * Send matrix notification email using Exchange or PHPMailer
 * 
 * @param Model $matrix The matrix object
 * @param Staff $recipient The recipient staff member
 * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned')
 * @param string $message The notification message
 * @return bool
 */
function sendMatrixNotification($matrix, Staff $recipient, string $type, string $message)
{
    // Set subject based on type
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
            break;
    }
    
    // Build email body
    $body = "
    <h2>Matrix Notification</h2>
    <p><strong>Matrix:</strong> {$matrix->title}</p>
    <p><strong>Description:</strong> {$matrix->description}</p>
    <p><strong>Message:</strong> {$message}</p>
    <p>Please review and take appropriate action.</p>
    <p>Best regards,<br>Africa CDC APM System</p>
    ";
    
    // Use central email dispatcher
    return sendEmail(
        $recipient->work_email,
        $subject,
        $body
    );
}

/**
 * Send matrix notification email using Exchange service
 * 
 * @param Model $matrix The matrix object
 * @param Staff $recipient The recipient staff member
 * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned')
 * @param string $message The notification message
 * @return bool
 */
function sendMatrixNotificationWithExchange($matrix, Staff $recipient, string $type, string $message)
{
    try {
        $config = config('exchange-email');
        
        // Use the working implementation from local ExchangeEmailService
        require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
        
        $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
            $config['tenant_id'],
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri'] ?? 'http://localhost:8000/oauth/callback',
            'https://graph.microsoft.com/.default', // Correct scope for client credentials
            'client_credentials' // Force client credentials
        );
        
        if (!$oauth->isConfigured()) {
            \Log::error('Exchange service not configured - Exchange is required for all emails');
            throw new \Exception('Exchange service not configured. Please check your Exchange credentials.');
        }
        
        // Get client credentials token
        $oauth->getClientCredentialsToken();
        
        // Set subject based on type (same logic as PHPMailer version)
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
                break;
        }
        
        // Build email body (same logic as PHPMailer version)
        $body = "
        <h2>Matrix Notification</h2>
        <p><strong>Matrix:</strong> {$matrix->title}</p>
        <p><strong>Description:</strong> {$matrix->description}</p>
        <p><strong>Message:</strong> {$message}</p>
        <p>Please review and take appropriate action.</p>
        <p>Best regards,<br>Africa CDC APM System</p>
        ";
        
        return $oauth->sendEmail(
            $recipient->work_email,
            $subject,
            $body,
            true,
            env('MAIL_FROM_ADDRESS'),
            env('MAIL_FROM_NAME', 'Africa CDC APM')
        );
        
    } catch (Exception $e) {
        \Log::error('Exchange email failed, falling back to PHPMailer: ' . $e->getMessage());
        return sendMatrixNotificationWithPHPMailer($matrix, $recipient, $type, $message);
    }
}

/**
 * Send matrix notification email using custom PHPMailer but with the same view template
 * 
 * @param Model $matrix The matrix object
 * @param Staff $recipient The recipient staff member
 * @param string $type The type of notification (e.g., 'matrix_approval', 'matrix_returned')
 * @param string $message The notification message
 * @return bool
 */
function sendMatrixNotificationWithPHPMailer($matrix, Staff $recipient, string $type, string $message)
{
    // Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);

    try {
        // Server settings for PHPMailer SMTP (separate from Exchange)
        $mail->SMTPDebug = 0;                                    // Disable debug output for production
        $mail->isSMTP();                                         // Send using SMTP
        $mail->Host       = env('PHPMailer_HOST', env('MAIL_HOST'));                    // PHPMailer SMTP server
        $mail->SMTPAuth   = true;                                // Enable SMTP authentication
        $mail->Username   = env('PHPMailer_USERNAME', env('MAIL_USERNAME'));                // PHPMailer SMTP username
        $mail->Password   = env('PHPMailer_PASSWORD', env('MAIL_PASSWORD'));                // PHPMailer SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable implicit TLS encryption
        $mail->Port       = env('PHPMailer_PORT', env('MAIL_PORT', 587));                    // PHPMailer SMTP port

        // Recipients
        $mail->setFrom(env('PHPMailer_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')), env('PHPMailer_FROM_NAME', env('MAIL_FROM_NAME', 'Africa CDC APM')));
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

        // Build simple HTML content (avoiding complex Blade view dependencies)
        $htmlContent = "
        <h2>Matrix Notification</h2>
        <p><strong>Matrix:</strong> {$matrix->title}</p>
        <p><strong>Description:</strong> {$matrix->description}</p>
        <p><strong>Message:</strong> {$message}</p>
        <p>Please review and take appropriate action.</p>
        <p>Best regards,<br>Africa CDC APM System</p>
        ";

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
    // Get the recipient using the existing helper function
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

    // Dispatch the job to send email in background
    dispatchMatrixNotificationJob($model, $recipient, $type, $message);
    
    return true;
}
