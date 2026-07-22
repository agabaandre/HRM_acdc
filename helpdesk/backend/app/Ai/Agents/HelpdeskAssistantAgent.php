<?php

namespace App\Ai\Agents;

use App\Ai\OpenAiCompatibleClient;
use Illuminate\Support\Str;

/**
 * Ask Helpdesk agent — structured troubleshooting guidance for staff.
 * Designed to align with Laravel AI SDK agent classes (instructions + prompt).
 */
class HelpdeskAssistantAgent
{
    public function __construct(
        private readonly OpenAiCompatibleClient $client,
    ) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
You are Ask Helpdesk, the Africa CDC Service Desk virtual assistant.

Your role:
- Understand the user's service request or issue in plain language (IT, Protocol, HR, Finance, and other business units).
- Search the provided knowledge-base excerpts first; prefer answers grounded in those articles.
- Give clear, numbered troubleshooting steps the user can try before opening a ticket.
- Be professional, concise, and calm. Use Africa CDC / corporate tone (no slang).
- If the issue needs hands-on support, say so and recommend logging a ticket via "New request".
- Never invent policy, credentials, or URLs not present in the knowledge base.
- Never ask for passwords or MFA codes.

Reply with JSON only, matching this schema:
{
  "summary": "one or two sentences answering the user",
  "steps": ["step 1", "step 2"],
  "related_kb_article_ids": [1, 2],
  "suggest_ticket": false,
  "confidence": "high|medium|low"
}
PROMPT;
    }

    /**
     * @param  list<array{id: int, question: string, answer_plain: string}>  $kbArticles
     * @return array{summary: string, steps: list<string>, related_kb_article_ids: list<int>, suggest_ticket: bool, confidence: string}|null
     */
    public function respond(string $question, array $kbArticles): ?array
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        $kbBlock = $this->formatKbContext($kbArticles);
        $userPayload = "User question:\n".trim($question)."\n\nKnowledge base excerpts:\n".$kbBlock;

        $raw = $this->client->chat([
            ['role' => 'system', 'content' => $this->instructions()],
            ['role' => 'user', 'content' => $userPayload],
        ], maxTokens: 900);

        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->parseStructuredResponse($raw);
    }

    /**
     * @param  list<array{id: int, question: string, answer_plain: string}>  $kbArticles
     */
    private function formatKbContext(array $kbArticles): string
    {
        if ($kbArticles === []) {
            return '(No matching knowledge-base articles.)';
        }

        $lines = [];
        foreach ($kbArticles as $article) {
            $lines[] = sprintf(
                "[ID %d] Q: %s\nA: %s",
                $article['id'],
                $article['question'],
                Str::limit($article['answer_plain'], 1200, '…')
            );
        }

        return implode("\n\n---\n\n", $lines);
    }

    /**
     * @return array{summary: string, steps: list<string>, related_kb_article_ids: list<int>, suggest_ticket: bool, confidence: string}|null
     */
    private function parseStructuredResponse(string $raw): ?array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        if ($summary === '') {
            return null;
        }

        $steps = [];
        foreach ((array) ($decoded['steps'] ?? []) as $step) {
            if (is_string($step) && trim($step) !== '') {
                $steps[] = trim($step);
            }
        }

        $ids = [];
        foreach ((array) ($decoded['related_kb_article_ids'] ?? []) as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        $confidence = strtolower(trim((string) ($decoded['confidence'] ?? 'medium')));
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'medium';
        }

        return [
            'summary' => $summary,
            'steps' => $steps,
            'related_kb_article_ids' => array_values(array_unique($ids)),
            'suggest_ticket' => filter_var($decoded['suggest_ticket'] ?? false, FILTER_VALIDATE_BOOL),
            'confidence' => $confidence,
        ];
    }
}
