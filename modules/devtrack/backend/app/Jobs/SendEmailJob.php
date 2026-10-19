<?php

namespace App\Jobs;

use App\Exceptions\PermanentEmailDeliveryException;
use App\Models\EmailLog;
use App\Services\EmailDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public readonly int $emailLogId,
    ) {
        $this->onQueue('emails');
    }

    public function handle(EmailDispatchService $dispatch): void
    {
        try {
            $dispatch->deliver($this->emailLogId);
        } catch (PermanentEmailDeliveryException $exception) {
            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        EmailLog::query()
            ->whereKey($this->emailLogId)
            ->where('status', '!=', 'sent')
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Email delivery failed after 5 attempts.',
            ]);
    }
}
