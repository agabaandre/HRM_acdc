<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskKbArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public-to-authed-staff read API for the helpdesk knowledge base.
 * Anyone who is signed in can browse FAQs. Edits live under /admin/kb/articles.
 */
class KbArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category_id', 0);

        $query = HelpdeskKbArticle::query()
            ->with(['category:id,name,slug,sort_order'])
            ->where('is_active', true);

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('question', 'like', $like)
                    ->orWhere('answer', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (HelpdeskKbArticle $a) => $this->formatRow($a))->values(),
            'meta' => [
                'count' => $rows->count(),
                'query' => $q,
                'category_id' => $categoryId > 0 ? $categoryId : null,
            ],
        ]);
    }

    public function show(HelpdeskKbArticle $article): JsonResponse
    {
        abort_unless($article->is_active, 404);
        $article->loadMissing(['category:id,name,slug']);

        return response()->json(['data' => $this->formatRow($article)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(HelpdeskKbArticle $a): array
    {
        return [
            'id' => $a->id,
            'category' => $a->category ? [
                'id' => $a->category->id,
                'name' => $a->category->name,
                'slug' => $a->category->slug,
            ] : null,
            'question' => $a->question,
            'answer' => $a->answer,
            'sort_order' => $a->sort_order,
            'is_active' => $a->is_active,
            'updated_at' => optional($a->updated_at)->toIso8601String(),
        ];
    }
}
