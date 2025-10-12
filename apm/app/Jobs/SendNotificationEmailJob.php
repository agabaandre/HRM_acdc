<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as BusQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class SendNotificationEmailJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $model;
    protected $recipient;
    protected $type;
    protected $message;
    protected $template;

    /**
     * Create a new job instance.
     */
    public function __construct($model, $recipient, string $type, string $message, string $template = 'emails.generic-notification')
    {
        $this->model = $model;
        $this->recipient = $recipient;
        $this->type = $type;
        $this->message = $message;
        $this->template = $template;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!$this->recipient || !$this->recipient->work_email) {
                Log::warning('Recipient not found or no email address', [
                    'model_id' => $this->model ? $this->model->id : 'null',
                    'model_type' => $this->model ? get_class($this->model) : 'null',
                    'recipient_id' => $this->recipient ? ($this->recipient->staff_id ?? 'unknown') : 'null'
                ]);
                return;
            }

            // Use the same Exchange service that works for matrix notifications
            $result = $this->sendWithExchange();

            if (!$result) {
                throw new \Exception('Failed to send email notification via Exchange');
            }

            Log::info('Notification email sent successfully via Exchange', [
                'model_id' => $this->model ? $this->model->id : 'null',
                'model_type' => $this->model ? get_class($this->model) : 'null',
                'recipient_id' => $this->recipient ? $this->recipient->staff_id : 'null',
                'email' => $this->recipient ? $this->recipient->work_email : 'null',
                'type' => $this->type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'model_id' => $this->model ? $this->model->id : 'null',
                'model_type' => $this->model ? get_class($this->model) : 'null',
                'recipient_id' => $this->recipient ? ($this->recipient->staff_id ?? 'unknown') : 'null',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send email using Exchange service (same as matrix notifications)
     */
    private function sendWithExchange(): bool
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
                Log::error('Exchange service not configured - Exchange is required for all emails');
                throw new \Exception('Exchange service not configured. Please check your Exchange credentials.');
            }

            // Generate subject
            $prefix = env('MAIL_SUBJECT_PREFIX', 'APM') . ": ";
            $subject = $prefix . $this->message;

            // Generate HTML content
            $htmlContent = view($this->template, [
                'resource' => $this->model,
                'resource_type' => ucfirst(class_basename($this->model)),
                'recipient' => $this->recipient,
                'message' => $this->message,
                'type' => $this->type
            ])->render();

            // Send via Exchange
            return $oauth->sendEmail(
                $this->recipient->work_email,
                $subject,
                $htmlContent
            );

        } catch (\Exception $e) {
            Log::error('Exchange email sending failed', [
                'recipient' => $this->recipient ? $this->recipient->work_email : 'null',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification email job failed permanently', [
            'model_id' => $this->model ? $this->model->id : 'null',
            'model_type' => $this->model ? get_class($this->model) : 'null',
            'recipient_id' => $this->recipient ? ($this->recipient->staff_id ?? 'unknown') : 'null',
            'error' => $exception->getMessage()
        ]);
    }
}