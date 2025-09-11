<?php

namespace App\Jobs;

use App\Models\Matrix;
use App\Models\Staff;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable as BusQueueable;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class SendMatrixNotificationJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public $model;
    public $recipient;
    public $type;
    public $message;
    public $tries = 3; // Number of retry attempts
    public $timeout = 60; // Timeout in seconds

    /**
     * Create a new job instance.
     */
    public function __construct($model, $recipient, string $type, string $message)
    {
        $this->model = $model;
        $this->recipient = $recipient;
        $this->type = $type;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create notification record first
            Notification::create([
                'staff_id' => $this->recipient->staff_id,
                'model_id' => $this->model->id,
                'message' => $this->message,
                'type' => $this->type,
                'is_read' => false
            ]);

            // Send email using custom PHPMailer
            $this->sendMatrixNotificationWithPHPMailer();
            
            Log::info('Resource notification email sent successfully', [
                'model_id' => $this->model->id,
                'recipient_id' => $this->recipient->staff_id,
                'type' => $this->type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send resource notification email in job', [
                'model_id' => $this->model->id,
                'recipient_id' => $this->recipient->staff_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Send matrix notification email using custom PHPMailer
     */
    private function sendMatrixNotificationWithPHPMailer(): void
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
            $mail->addAddress($this->recipient->work_email, $this->recipient->fname . ' ' . $this->recipient->lname);
            $mail->addBCC('system@africacdc.org');
            //system@africacdc.org
            // Set subject based on type (same logic as MatrixNotification.php)
            $subject = '';
            $prefix  = env('MAIL_SUBJECT_PREFIX','Approval Management sytem').": ";
            switch($this->type) {
                case 'approval':
                    $subject = ucfirst(class_basename($this->model)).' Approval Request';
                    break;
                case 'returned':
                    $subject = ucfirst(class_basename($this->model)).' Returned for Revision';
                    break;
                default:
                    $subject = ucfirst(class_basename($this->model)).' Notification';
            }

            $mail->Subject = $prefix.$subject;
            // Render the same view template that MatrixNotification uses
            $htmlContent = View::make('emails.matrix-notification', [
                'resource' => $this->model,
                'recipient' => $this->recipient,
                'message' => $this->message,
                'type' => $this->type
            ])->render();

            // Content
            $mail->isHTML(true);
            $mail->Body = $htmlContent;
            
            // Create plain text version
            $plainText = strip_tags($htmlContent);
            $mail->AltBody = $plainText;

            $result = $mail->send();

            Log::info("Mail sent:: ".$result);
            
        } catch (Exception $e) {
            Log::error('PHPMailer error in matrix notification job: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Matrix notification job failed permanently', [
            'model_id' => $this->model->id,
            'recipient_id' => $this->recipient->staff_id,
            'type' => $this->type,
            'error' => $exception->getMessage()
        ]);
    }
}
