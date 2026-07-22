<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskCategory;
use App\Services\CategoryBusinessUnitRemapService;
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
            ->with('businessUnit')
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
            'default_priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'business_unit_id' => ['required', 'integer', 'exists:helpdesk_business_units,id'],
            'ai_description' => ['nullable', 'string', 'max:5000'],
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
            'default_priority' => $validated['default_priority'] ?? 'medium',
            'business_unit_id' => (int) $validated['business_unit_id'],
            'ai_description' => $validated['ai_description'] ?? null,
        ]);

        return response()->json(['data' => $row->load('businessUnit')], 201);
    }

    public function update(Request $request, HelpdeskCategory $category): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('helpdesk_categories', 'slug')->ignore($category->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'default_priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'business_unit_id' => ['sometimes', 'integer', 'exists:helpdesk_business_units,id'],
            'ai_description' => ['nullable', 'string', 'max:5000'],
        ]);

        $category->fill($validated);
        $category->save();

        return response()->json(['data' => $category->fresh()->load('businessUnit')]);
    }

    public function destroy(Request $request, HelpdeskCategory $category): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        abort(422, 'Categories cannot be deleted directly. Use Remap to merge into another category.');
    }

    public function remap(
        Request $request,
        HelpdeskCategory $category,
        CategoryBusinessUnitRemapService $remap,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'target_category_id' => [
                'required',
                'integer',
                Rule::exists('helpdesk_categories', 'id'),
            ],
        ]);

        $target = HelpdeskCategory::query()->findOrFail((int) $validated['target_category_id']);
        if ($target->id === $category->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'target_category_id' => 'Choose a different category to remap into.',
            ]);
        }

        $sourceName = $category->name;
        $targetName = $target->name;
        $sourceId = $category->id;
        $counts = $remap->remapCategory($category, $target);

        return response()->json([
            'message' => "Remapped “{$sourceName}” into “{$targetName}”.",
            'data' => [
                'source_id' => $sourceId,
                'target_id' => $target->id,
                'moved' => $counts,
            ],
        ]);
    }
}
