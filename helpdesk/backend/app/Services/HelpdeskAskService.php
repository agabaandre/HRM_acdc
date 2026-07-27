<?php

namespace App\Services;

use App\Ai\Agents\HelpdeskAssistantAgent;
use App\Models\HelpdeskKbArticle;
use App\Models\User;
use Illuminate\Support\Collection;
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
     * @param  Collection<int, HelpdeskKbArticle>  $kbArticles
     * @return array{
     *   answer: string,
     *   steps: list<string>,
     *   related_articles: list<array{id: int, question: string}>,
     *   suggest_ticket: bool,
     *   confidence: string,
     *   source: string
     * }
     */
    private function formatAiResponse(array $ai, Collection $kbArticles): array
    {
        $byId = $kbArticles->keyBy('id');
        $related = collect($ai['related_kb_article_ids'])
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->map(fn (HelpdeskKbArticle $a) => ['id' => $a->id, 'question' => $a->question])
            ->values()
            ->all();

        // Only auto-attach top KB hits when the model is reasonably confident and cited none.
        if ($related === [] && $kbArticles->isNotEmpty() && in_array($ai['confidence'], ['high', 'medium'], true)) {
            $related = $kbArticles->take(2)->map(fn (HelpdeskKbArticle $a) => [
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
     * @param  Collection<int, HelpdeskKbArticle>  $kbArticles
     * @return array{
     *   answer: string,
     *   steps: list<string>,
     *   related_articles: list<array{id: int, question: string}>,
     *   suggest_ticket: bool,
     *   confidence: string,
     *   source: string
     * }
     */
    private function heuristicResponse(string $question, Collection $kbArticles): array
    {
        if ($kbArticles->isNotEmpty()) {
            $top = $kbArticles->first();
            $steps = $this->stepsFromAnswer($top->answer);
            $plain = $this->plainAnswer($top->answer);

            return [
                'answer' => $plain !== ''
                    ? Str::limit($plain, 500, '…')
                    : 'Based on our knowledge base, this may help: '.$top->question,
                'steps' => $steps !== [] ? array_slice($steps, 0, 6) : [
                    'Review the related FAQ for detailed guidance.',
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
            'answer' => 'I could not find a matching FAQ for your question. A Service Desk agent can investigate further.',
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

    /**
     * Rank active KB articles by term overlap (question/keywords weighted higher than body).
     *
     * @return Collection<int, HelpdeskKbArticle>
     */
    private function findRelevantArticles(string $question): Collection
    {
        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return collect();
        }

        $candidates = HelpdeskKbArticle::query()
            ->where('is_active', true)
            ->with(['category:id,name'])
            ->where(function ($w) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $w->orWhere('question', 'like', $like)
                        ->orWhere('answer', 'like', $like)
                        ->orWhere('search_keywords', 'like', $like);
                }
            })
            ->limit(60)
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $questionLower = strtolower($question);
        $minScore = count($terms) >= 3 ? 8 : (count($terms) === 2 ? 6 : 5);

        $scored = $candidates->map(function (HelpdeskKbArticle $article) use ($terms, $questionLower) {
            $q = strtolower((string) $article->question);
            $kw = strtolower((string) ($article->search_keywords ?? ''));
            $ans = strtolower($this->plainAnswer((string) $article->answer));
            $score = 0;
            $hits = 0;

            foreach ($terms as $term) {
                $termHit = false;
                if (str_contains($q, $term)) {
                    $score += 5;
                    $termHit = true;
                }
                if ($kw !== '' && str_contains($kw, $term)) {
                    $score += 3;
                    $termHit = true;
                }
                if (str_contains($ans, $term)) {
                    $score += 1;
                    $termHit = true;
                }
                if ($termHit) {
                    $hits++;
                }
            }

            // Bonus when the FAQ title closely resembles the user question.
            similar_text($questionLower, $q, $percent);
            if ($percent >= 45) {
                $score += 4;
            }

            // Prefer articles that hit multiple query terms.
            if ($hits >= 2) {
                $score += $hits * 2;
            }

            return [
                'article' => $article,
                'score' => $score,
                'hits' => $hits,
            ];
        })
            ->filter(fn (array $row) => $row['score'] >= $minScore && $row['hits'] >= 1)
            ->sortByDesc(fn (array $row) => [$row['score'], $row['hits']])
            ->values();

        // Soften threshold once if nothing passed (still require a real title/keyword hit).
        if ($scored->isEmpty()) {
            $scored = $candidates->map(function (HelpdeskKbArticle $article) use ($terms) {
                $q = strtolower((string) $article->question);
                $kw = strtolower((string) ($article->search_keywords ?? ''));
                $score = 0;
                $hits = 0;
                foreach ($terms as $term) {
                    if (str_contains($q, $term) || ($kw !== '' && str_contains($kw, $term))) {
                        $score += str_contains($q, $term) ? 5 : 3;
                        $hits++;
                    }
                }

                return ['article' => $article, 'score' => $score, 'hits' => $hits];
            })
                ->filter(fn (array $row) => $row['hits'] >= 1 && $row['score'] >= 5)
                ->sortByDesc(fn (array $row) => $row['score'])
                ->values();
        }

        return $scored->take(5)->map(fn (array $row) => $row['article'])->values();
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $question): array
    {
        $stop = [
            'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'help', 'please',
            'cant', "can't", 'does', 'what', 'when', 'where', 'which', 'who', 'how',
            'are', 'is', 'was', 'were', 'been', 'can', 'could', 'would', 'should',
            'into', 'about', 'your', 'our', 'not', 'get', 'got', 'need', 'want',
        ];
        $words = preg_split('/\s+/', strtolower($question)) ?: [];
        $terms = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word) ?? '';
            if (strlen($word) >= 3 && ! in_array($word, $stop, true)) {
                $terms[] = $word;
            }
        }

        return array_values(array_unique(array_slice($terms, 0, 8)));
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
