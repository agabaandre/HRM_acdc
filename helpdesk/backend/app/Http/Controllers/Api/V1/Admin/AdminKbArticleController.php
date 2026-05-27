<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskKbArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminKbArticleController extends Controller
{
    use AuthorizesKbManager;

    public function index(Request $request): JsonResponse
    {
        $this->ensureKbManager($request);

        $rows = HelpdeskKbArticle::query()
            ->with(['category:id,name,slug', 'createdBy:id,name', 'updatedBy:id,name'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();

        return response()->json(['data' => $rows->map(fn (HelpdeskKbArticle $a) => $this->format($a))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureKbManager($request);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:helpdesk_categories,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = HelpdeskKbArticle::query()->create([
            'category_id' => $validated['category_id'],
            'question' => trim($validated['question']),
            'answer' => $validated['answer'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $row->load(['category:id,name,slug', 'createdBy:id,name', 'updatedBy:id,name']);

        return response()->json(['data' => $this->format($row)], 201);
    }

    public function update(Request $request, HelpdeskKbArticle $article): JsonResponse
    {
        $this->ensureKbManager($request);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:helpdesk_categories,id'],
            'question' => ['sometimes', 'string', 'max:255'],
            'answer' => ['sometimes', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['question'])) {
            $validated['question'] = trim($validated['question']);
        }
        $validated['updated_by_user_id'] = $request->user()?->id;

        $article->fill($validated);
        $article->save();
        $article->load(['category:id,name,slug', 'createdBy:id,name', 'updatedBy:id,name']);

        return response()->json(['data' => $this->format($article)]);
    }

    public function destroy(Request $request, HelpdeskKbArticle $article): JsonResponse
    {
        $this->ensureKbManager($request);

        $article->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(HelpdeskKbArticle $a): array
    {
        return [
            'id' => $a->id,
            'category' => $a->category ? [
                'id' => $a->category->id,
                'name' => $a->category->name,
                'slug' => $a->category->slug,
            ] : null,
            'category_id' => $a->category_id,
            'question' => $a->question,
            'answer' => $a->answer,
            'sort_order' => $a->sort_order,
            'is_active' => $a->is_active,
            'created_by' => $a->createdBy ? ['id' => $a->createdBy->id, 'name' => $a->createdBy->name] : null,
            'updated_by' => $a->updatedBy ? ['id' => $a->updatedBy->id, 'name' => $a->updatedBy->name] : null,
            'created_at' => optional($a->created_at)->toIso8601String(),
            'updated_at' => optional($a->updated_at)->toIso8601String(),
        ];
    }
}
