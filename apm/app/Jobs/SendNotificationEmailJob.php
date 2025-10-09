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
                    'model_id' => $this->model->id,
                    'model_type' => get_class($this->model),
                    'recipient_id' => $this->recipient->staff_id ?? 'unknown'
                ]);
                return;
            }

            // Send email using the template system
            $result = sendEmailWithTemplate($this->recipient->work_email, $this->template, [
                'resource' => $this->model,
                'resource_type' => ucfirst(class_basename($this->model)),
                'recipient' => $this->recipient,
                'message' => $this->message,
                'type' => $this->type
            ]);

            if (!$result) {
                throw new \Exception('Failed to send email notification');
            }

            Log::info('Notification email sent successfully', [
                'model_id' => $this->model->id,
                'model_type' => get_class($this->model),
                'recipient_id' => $this->recipient->staff_id,
                'email' => $this->recipient->work_email,
                'type' => $this->type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'model_id' => $this->model->id,
                'model_type' => get_class($this->model),
                'recipient_id' => $this->recipient->staff_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }


    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification email job failed permanently', [
            'model_id' => $this->model->id,
            'model_type' => get_class($this->model),
            'recipient_id' => $this->recipient->staff_id ?? 'unknown',
            'error' => $exception->getMessage()
        ]);
    }
}