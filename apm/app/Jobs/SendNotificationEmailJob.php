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

    /** @var array<string, mixed> */
    protected array $emailViewContext = [];

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $emailViewContext  Extra variables for specific email templates (e.g. stale pending list).
     */
    public function __construct($model, $recipient, string $type, string $message, string $template = 'emails.generic-notification', array $emailViewContext = [])
    {
        $this->model = $model;
        $this->recipient = $recipient;
        $this->type = $type;
        $this->message = $message;
        $this->template = $template;
        $this->emailViewContext = $emailViewContext;
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

            // Get admin assistant emails for CC (if enabled)
            $ccEmails = [];
            if (env('NOTIFICATION_CC_ADMIN_ASSISTANTS', true)) {
                $ccEmails = $this->getAdminAssistantEmails();
            }

            $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM') . ': ';
            $subject = $subjectPrefix . $this->message;

            $viewData = $this->buildNotificationViewData();
            $htmlContent = view($this->template, $viewData)->render();

            $exchangeError = $this->sendWithExchange($htmlContent, $subject, $ccEmails, ['system@africacdc.org']);

            if ($exchangeError === null) {
                Log::info('Notification email sent successfully via Exchange', [
                    'model_id' => $this->model ? $this->model->id : 'null',
                    'model_type' => $this->model ? get_class($this->model) : 'null',
                    'recipient_id' => $this->recipient ? $this->recipient->staff_id : 'null',
                    'email' => $this->recipient ? $this->recipient->work_email : 'null',
                    'type' => $this->type,
                ]);

                return;
            }

            Log::warning('Notification email Exchange send failed', [
                'model_id' => $this->model ? $this->model->id : 'null',
                'model_type' => $this->model ? get_class($this->model) : 'null',
                'recipient_id' => $this->recipient ? $this->recipient->staff_id : 'null',
                'email' => $this->recipient ? $this->recipient->work_email : 'null',
                'exchange_error' => $exchangeError,
            ]);

            if ($this->sendViaSmtpFallback($htmlContent, $subject, $ccEmails, ['system@africacdc.org'])) {
                Log::info('Notification email sent via SMTP fallback', [
                    'model_id' => $this->model ? $this->model->id : 'null',
                    'model_type' => $this->model ? get_class($this->model) : 'null',
                    'recipient_id' => $this->recipient ? $this->recipient->staff_id : 'null',
                    'email' => $this->recipient ? $this->recipient->work_email : 'null',
                    'type' => $this->type,
                ]);

                return;
            }

            throw new \RuntimeException(
                'Failed to send email notification (Exchange: '.$exchangeError.'). SMTP fallback also failed or is disabled.'
            );

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
     * Build view data for the notification template (shared by Exchange and SMTP paths).
     *
     * @return array<string, mixed>
     */
    private function buildNotificationViewData(): array
    {
        $viewData = [
            'resource' => $this->model,
            'resource_type' => $this->model ? ucfirst(class_basename($this->model)) : 'System',
            'staff' => $this->recipient,
            'recipient' => $this->recipient,
            'message' => $this->message,
            'type' => $this->type,
            'notification' => (object) [
                'created_at' => now(),
            ],
        ];

        if ($this->template === 'emails.daily-pending-approvals-notification') {
            $viewData = array_merge($viewData, [
                'approverTitle' => $this->recipient->job_title ?? 'Staff',
                'approverName' => $this->recipient->fname . ' ' . $this->recipient->lname,
                'summaryStats' => $this->getSummaryStats(),
                'pendingApprovals' => $this->getPendingApprovals(),
            ]);
        }

        if ($this->template === 'emails.returned-memos-notification') {
            $viewData = array_merge($viewData, [
                'staffName' => $this->recipient->fname . ' ' . $this->recipient->lname,
                'summaryStats' => $this->getReturnedMemosSummaryStats(),
                'returnedItems' => $this->getReturnedMemosItems(),
                'returnedMemosUrl' => config('app.url') . 'returned-memos',
            ]);
        }

        if ($this->template === 'emails.stale-pending-approvals-reminder' && $this->emailViewContext !== []) {
            $viewData = array_merge($viewData, $this->emailViewContext);
        }

        return $viewData;
    }

    /**
     * Send via Microsoft Graph (Exchange OAuth). Returns null on success, or an error string on failure.
     */
    private function sendWithExchange(string $htmlContent, string $subject, array $cc = [], array $bcc = []): ?string
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
                Log::error('Exchange service not configured');

                return 'Exchange OAuth is not configured (tenant_id / client_id / client_secret).';
            }

            $ok = $oauth->sendEmail(
                $this->recipient->work_email,
                $subject,
                $htmlContent,
                true,
                null,
                null,
                $cc,
                $bcc,
                []
            );

            if ($ok) {
                return null;
            }

            return $oauth->lastSendError ?? 'Graph sendMail returned failure (no error payload).';

        } catch (\Throwable $e) {
            Log::error('Exchange email sending failed', [
                'recipient' => $this->recipient ? $this->recipient->work_email : 'null',
                'error' => $e->getMessage(),
            ]);

            return $e->getMessage();
        }
    }

    /**
     * Optional SMTP fallback when Graph fails (uses Laravel "smtp" mailer).
     */
    private function sendViaSmtpFallback(string $htmlContent, string $subject, array $cc, array $bcc): bool
    {
        if (!filter_var(env('NOTIFICATION_EMAIL_USE_SMTP_FALLBACK', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $mailerName = env('NOTIFICATION_EMAIL_FALLBACK_MAILER', 'smtp');

        try {
            Mail::mailer($mailerName)->html($htmlContent, function ($message) use ($subject, $cc, $bcc) {
                $message->to($this->recipient->work_email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));

                foreach (array_filter($cc) as $addr) {
                    if (is_string($addr) && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                        $message->cc($addr);
                    }
                }
                foreach (array_filter($bcc) as $addr) {
                    if (is_string($addr) && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                        $message->bcc($addr);
                    }
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('SMTP fallback for notification email failed', [
                'mailer' => $mailerName,
                'recipient' => $this->recipient ? $this->recipient->work_email : 'null',
                'error' => $e->getMessage(),
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

    /**
     * Get admin assistant email addresses for the recipient approver
     * Handles both division-specific and non-division approvers
     */
    private function getAdminAssistantEmails(): array
    {
        if (!$this->recipient) {
            return [];
        }

        $adminAssistantEmails = [];

        try {
            // 1. Check if recipient is a division-specific approver
            // Get division where recipient is division_head, focal_person, or finance_officer
            $division = \App\Models\Division::where(function($query) {
                $query->where('division_head', $this->recipient->staff_id)
                      ->orWhere('focal_person', $this->recipient->staff_id)
                      ->orWhere('finance_officer', $this->recipient->staff_id);
            })->first();

            if ($division && $division->admin_assistant) {
                $adminAssistant = \App\Models\Staff::where('staff_id', $division->admin_assistant)
                    ->where('active', 1)
                    ->whereNotNull('work_email')
                    ->first();
                
                if ($adminAssistant && $adminAssistant->work_email) {
                    $adminAssistantEmails[] = $adminAssistant->work_email;
                }
            }

            // 2. Check if recipient is a non-division approver (from approvers table)
            $approver = \App\Models\Approver::where('staff_id', $this->recipient->staff_id)
                ->with('adminAssistant')
                ->first();

            if ($approver && $approver->admin_assistant) {
                $adminAssistant = \App\Models\Staff::where('staff_id', $approver->admin_assistant)
                    ->where('active', 1)
                    ->whereNotNull('work_email')
                    ->first();
                
                if ($adminAssistant && $adminAssistant->work_email) {
                    // Avoid duplicates
                    if (!in_array($adminAssistant->work_email, $adminAssistantEmails)) {
                        $adminAssistantEmails[] = $adminAssistant->work_email;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to get admin assistant emails', [
                'recipient_id' => $this->recipient->staff_id,
                'error' => $e->getMessage()
            ]);
        }

        return array_filter($adminAssistantEmails);
    }
}