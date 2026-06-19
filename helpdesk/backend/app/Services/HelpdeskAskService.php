<?php

namespace App\Services;

use App\Ai\Agents\HelpdeskAssistantAgent;
use App\Models\HelpdeskKbArticle;
use App\Models\User;
use Illuminate\Support\Str;

class HelpdeskAskService
{
    public function __construct(
        private readonly HelpdeskAssistantAgent $agent,
    ) {}

    /**
     * @return array{
     *   answer: string,
     *   steps: list<string>,
     *   related_articles: list<array{id: int, question: string}>,
     *   suggest_ticket: bool,
     *   confidence: string,
     *   source: string
     * }
     */
    public function ask(User $user, string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            abort(422, 'Please describe your IT issue.');
        }

        $kbArticles = $this->findRelevantArticles($question);
        $kbPayload = $kbArticles->map(fn (HelpdeskKbArticle $a) => [
            'id' => $a->id,
            'question' => $a->question,
            'answer_plain' => $this->plainAnswer($a->answer),
        ])->values()->all();

        $ai = $this->agent->respond($question, $kbPayload);
        if ($ai !== null) {
            return $this->formatAiResponse($ai, $kbArticles);
        }

        return $this->heuristicResponse($question, $kbArticles);
    }

    /**
     * @param  array{summary: string, steps: list<string>, related_kb_article_ids: list<int>, suggest_ticket: bool, confidence: string}  $ai
     * @param  \Illuminate\Support\Collection<int, HelpdeskKbArticle>  $kbArticles
     * @return array{
     *   answer: string,
     *   steps: list<string>,
     *   related_articles: list<array{id: int, question: string}>,
     *   suggest_ticket: bool,
     *   confidence: string,
     *   source: string
     * }
     */
    private function formatAiResponse(array $ai, $kbArticles): array
    {
        $related = $kbArticles
            ->filter(fn (HelpdeskKbArticle $a) => in_array($a->id, $ai['related_kb_article_ids'], true))
            ->map(fn (HelpdeskKbArticle $a) => ['id' => $a->id, 'question' => $a->question])
            ->values()
            ->all();

        if ($related === [] && $kbArticles->isNotEmpty()) {
            $related = $kbArticles->take(3)->map(fn (HelpdeskKbArticle $a) => [
                'id' => $a->id,
                'question' => $a->question,
            ])->values()->all();
        }

        return [
            'answer' => $ai['summary'],
            'steps' => $ai['steps'],
            'related_articles' => $related,
            'suggest_ticket' => $ai['suggest_ticket'],
            'confidence' => $ai['confidence'],
            'source' => 'ai',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HelpdeskKbArticle>  $kbArticles
     * @return array{
     *   answer: string,
     *   steps: list<string>,
     *   related_articles: list<array{id: int, question: string}>,
     *   suggest_ticket: bool,
     *   confidence: string,
     *   source: string
     * }
     */
    private function heuristicResponse(string $question, $kbArticles): array
    {
        if ($kbArticles->isNotEmpty()) {
            $top = $kbArticles->first();
            $steps = $this->stepsFromAnswer($top->answer);

            return [
                'answer' => 'Based on our knowledge base, this may help: '.$top->question,
                'steps' => $steps !== [] ? $steps : [
                    'Review the FAQ answer below for detailed guidance.',
                    'If the issue persists, log a new request so an agent can assist.',
                ],
                'related_articles' => $kbArticles->take(3)->map(fn (HelpdeskKbArticle $a) => [
                    'id' => $a->id,
                    'question' => $a->question,
                ])->values()->all(),
                'suggest_ticket' => $steps === [],
                'confidence' => 'medium',
                'source' => 'knowledge_base',
            ];
        }

        return [
            'answer' => 'I could not find a matching FAQ for your question. An IT agent can investigate further.',
            'steps' => [
                'Note any error messages, screenshots, or when the problem started.',
                'Try signing out and back in to the Staff portal if the issue affects access.',
                'Log a new request with a clear description so we can assign it to the right team.',
            ],
            'related_articles' => [],
            'suggest_ticket' => true,
            'confidence' => 'low',
            'source' => 'fallback',
        ];
    }

    /** @return \Illuminate\Support\Collection<int, HelpdeskKbArticle> */
    private function findRelevantArticles(string $question): \Illuminate\Support\Collection
    {
        $terms = $this->searchTerms($question);
        $query = HelpdeskKbArticle::query()
            ->where('is_active', true)
            ->with(['category:id,name']);

        if ($terms !== []) {
            $query->where(function ($w) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $w->orWhere('question', 'like', $like)
                        ->orWhere('answer', 'like', $like);
                }
            });
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('question')
            ->limit(8)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $question): array
    {
        $stop = ['the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'help', 'please', 'cant', "can't"];
        $words = preg_split('/\s+/', strtolower($question)) ?: [];
        $terms = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word) ?? '';
            if (strlen($word) >= 3 && ! in_array($word, $stop, true)) {
                $terms[] = $word;
            }
        }

        return array_values(array_unique(array_slice($terms, 0, 6)));
    }

    private function plainAnswer(string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return Str::limit($text, 2000, '…');
    }

    /**
     * @return list<string>
     */
    private function stepsFromAnswer(string $html): array
    {
        $plain = $this->plainAnswer($html);
        if ($plain === '') {
            return [];
        }

        if (preg_match_all('/(?:^|\n)\s*(?:\d+[\).\]]|[-•])\s*(.+)/u', $plain, $matches)) {
            $steps = array_map('trim', $matches[1]);

            return array_values(array_filter($steps, fn (string $s) => $s !== ''));
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $plain) ?: [];

        return array_values(array_filter(array_map('trim', array_slice($sentences, 0, 4)), fn (string $s) => $s !== ''));
    }
}
