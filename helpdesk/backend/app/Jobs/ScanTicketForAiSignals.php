<?php

namespace App\Jobs;

use App\Models\HelpdeskTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder for AI routing / categorization / duplicate detection (URS §10).
 */
class ScanTicketForAiSignals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ticketId)
    {
        $this->onQueue('helpdesk-ai');
    }

    public function handle(): void
    {
        HelpdeskTicket::query()->whereKey($this->ticketId)->first();
        // Wire OpenAI/Gemini providers via helpdesk_ai_* tables in a later iteration.
    }
}
