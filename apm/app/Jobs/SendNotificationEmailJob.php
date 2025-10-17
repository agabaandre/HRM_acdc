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
            $result = $this->sendWithExchange([], ['system@africacdc.org']);

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
    private function sendWithExchange(array $cc = [], array $bcc = []): bool
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

            // Generate HTML content with appropriate data based on template
            $viewData = [
                'resource' => $this->model,
                'resource_type' => $this->model ? ucfirst(class_basename($this->model)) : 'System',
                'staff' => $this->recipient, // Template expects $staff variable
                'recipient' => $this->recipient,
                'message' => $this->message,
                'type' => $this->type,
                'notification' => (object) [
                    'created_at' => now()
                ]
            ];

            // Add specific data for daily pending approvals template
            if ($this->template === 'emails.daily-pending-approvals-notification') {
                $viewData = array_merge($viewData, [
                    'approverTitle' => $this->recipient->job_title ?? 'Staff',
                    'approverName' => $this->recipient->fname . ' ' . $this->recipient->lname,
                    'summaryStats' => $this->getSummaryStats(),
                    'pendingApprovals' => $this->getPendingApprovals()
                ]);
            }
            
            // Add specific data for returned memos template
            if ($this->template === 'emails.returned-memos-notification') {
                $viewData = array_merge($viewData, [
                    'staffName' => $this->recipient->fname . ' ' . $this->recipient->lname,
                    'summaryStats' => $this->getReturnedMemosSummaryStats(),
                    'returnedItems' => $this->getReturnedMemosItems(),
                    'returnedMemosUrl' => config('app.url') . 'returned-memos'
                ]);
            }

            $htmlContent = view($this->template, $viewData)->render();

            // Send via Exchange
            return $oauth->sendEmail(
                $this->recipient->work_email,
                $subject,
                $htmlContent,
                true, // isHtml
                null, // fromEmail
                null, // fromName
                $cc,  // cc recipients
                $bcc, // bcc recipients
                []    // attachments
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

    /**
     * Get summary stats for daily pending approvals
     */
    private function getSummaryStats(): array
    {
        if (!$this->recipient) {
            return ['total_pending' => 0, 'by_category' => []];
        }

        $sessionData = [
            'staff_id' => $this->recipient->staff_id,
            'division_id' => $this->recipient->division_id,
            'permissions' => [],
            'name' => $this->recipient->fname . ' ' . $this->recipient->lname,
            'email' => $this->recipient->work_email,
            'base_url' => config('app.url')
        ];

        $pendingApprovalsService = new \App\Services\PendingApprovalsService($sessionData);
        return $pendingApprovalsService->getSummaryStats();
    }

    /**
     * Get pending approvals for daily pending approvals
     */
    private function getPendingApprovals(): array
    {
        if (!$this->recipient) {
            return [];
        }

        $sessionData = [
            'staff_id' => $this->recipient->staff_id,
            'division_id' => $this->recipient->division_id,
            'permissions' => [],
            'name' => $this->recipient->fname . ' ' . $this->recipient->lname,
            'email' => $this->recipient->work_email,
            'base_url' => config('app.url')
        ];

        $pendingApprovalsService = new \App\Services\PendingApprovalsService($sessionData);
        return $pendingApprovalsService->getPendingApprovals();
    }

    /**
     * Get summary stats for returned memos
     */
    private function getReturnedMemosSummaryStats(): array
    {
        if (!$this->recipient) {
            return ['total_returned' => 0, 'by_category' => []];
        }

        $sessionData = [
            'staff_id' => $this->recipient->staff_id,
            'division_id' => $this->recipient->division_id ?? null,
            'permissions' => [],
            'name' => $this->recipient->fname . ' ' . $this->recipient->lname,
            'email' => $this->recipient->work_email,
            'base_url' => config('app.url')
        ];

        $returnedMemosService = new \App\Services\ReturnedMemosService($sessionData);
        return $returnedMemosService->getSummaryStats();
    }

    /**
     * Get returned memos items
     */
    private function getReturnedMemosItems(): array
    {
        if (!$this->recipient) {
            return [];
        }

        $sessionData = [
            'staff_id' => $this->recipient->staff_id,
            'division_id' => $this->recipient->division_id ?? null,
            'permissions' => [],
            'name' => $this->recipient->fname . ' ' . $this->recipient->lname,
            'email' => $this->recipient->work_email,
            'base_url' => config('app.url')
        ];

        $returnedMemosService = new \App\Services\ReturnedMemosService($sessionData);
        return $returnedMemosService->getReturnedMemos();
    }
}