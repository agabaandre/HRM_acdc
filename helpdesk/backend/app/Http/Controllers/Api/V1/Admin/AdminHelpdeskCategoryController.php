<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminHelpdeskCategoryController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $rows = HelpdeskCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('helpdesk_categories', 'slug')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if (HelpdeskCategory::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $row = HelpdeskCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskCategory $category): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('helpdesk_categories', 'slug')->ignore($category->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->fill($validated);
        $category->save();

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(Request $request, HelpdeskCategory $category): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        if ($category->tickets()->exists()) {
            abort(422, 'Cannot delete a category that has tickets. Deactivate it instead.');
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }
}
