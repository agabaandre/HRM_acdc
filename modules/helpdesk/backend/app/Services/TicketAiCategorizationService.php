<?php

namespace App\Services;

use App\Ai\OpenAiCompatibleClient;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use Illuminate\Support\Facades\Log;

class TicketAiCategorizationService
{
    public function __construct(
        private readonly OpenAiCompatibleClient $ai,
    ) {}

    /**
     * Pick a category under the ticket's business unit using AI (and descriptions).
     * Returns null when AI is unavailable or confidence is too low.
     */
    public function categorize(HelpdeskTicket $ticket): ?HelpdeskCategory
    {
        $businessUnitId = (int) ($ticket->business_unit_id ?? 0);
        if ($businessUnitId < 1) {
            return null;
        }

        $categories = HelpdeskCategory::query()
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'ai_description']);

        if ($categories->isEmpty()) {
            return null;
        }

        if ($categories->count() === 1) {
            return $categories->first();
        }

        if (! $this->ai->isConfigured()) {
            return $this->heuristicPick($ticket, $categories);
        }

        $catalog = $categories->map(fn (HelpdeskCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'description' => trim((string) ($c->ai_description ?? '')),
        ])->values()->all();

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $ticket->description)) ?? '');
        $subject = trim((string) $ticket->subject);

        $prompt = json_encode([
            'task' => 'Pick the single best helpdesk issue category id for this ticket.',
            'categories' => $catalog,
            'ticket' => [
                'subject' => $subject,
                'description' => mb_substr($plain, 0, 4000),
            ],
            'response_schema' => [
                'category_id' => 'integer id from categories list',
                'confidence' => 'number 0-1',
                'reason' => 'short string',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $raw = $this->ai->chat([
            [
                'role' => 'system',
                'content' => 'You classify helpdesk tickets into issue categories. Reply with JSON only.',
            ],
            [
                'role' => 'user',
                'content' => (string) $prompt,
            ],
        ], 400, 0.1);

        if ($raw === null || $raw === '') {
            Log::info('helpdesk.ai_categorize.empty_response', ['ticket_id' => $ticket->id]);

            return $this->heuristicPick($ticket, $categories);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $this->heuristicPick($ticket, $categories);
        }

        $categoryId = (int) ($decoded['category_id'] ?? 0);
        $confidence = (float) ($decoded['confidence'] ?? 0);
        $match = $categories->firstWhere('id', $categoryId);
        if (! $match || $confidence < 0.45) {
            return $this->heuristicPick($ticket, $categories);
        }

        return $match;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HelpdeskCategory>  $categories
     */
    private function heuristicPick(HelpdeskTicket $ticket, $categories): ?HelpdeskCategory
    {
        $hay = strtolower(trim(strip_tags((string) $ticket->description)).' '.(string) $ticket->subject);
        $best = null;
        $bestScore = 0;

        foreach ($categories as $category) {
            $score = 0;
            $blob = strtolower($category->name.' '.($category->ai_description ?? '').' '.$category->slug);
            foreach (preg_split('/[^a-z0-9]+/', $blob) ?: [] as $token) {
                if (strlen($token) < 3) {
                    continue;
                }
                if (str_contains($hay, $token)) {
                    $score += 1;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $category;
            }
        }

        return $bestScore > 0 ? $best : null;
    }
}
