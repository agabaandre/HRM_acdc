<?php

namespace App\Http\Controllers;

use App\Models\MemoTypeDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemoTypeDefinitionController extends Controller
{
    public function index(): View
    {
        return view('memo-type-definitions.index');
    }

    public function ajaxIndex(Request $request): JsonResponse
    {
        $query = MemoTypeDefinition::query()->orderBy('sort_order')->orderBy('name');

        if ($request->filled('q')) {
            $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')) . '%';
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', $q)
                    ->orWhere('slug', 'like', $q)
                    ->orWhere('ref_prefix', 'like', $q);
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $rows = $query->get()->map(fn (MemoTypeDefinition $m) => $m->toApiArray());

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function ajaxShow(MemoTypeDefinition $memoTypeDefinition): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $memoTypeDefinition->toApiArray(),
        ]);
    }

    public function ajaxStore(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, null);
        $validated['fields_schema'] = MemoTypeDefinition::normalizeFieldsSchemaRows($validated['fields_schema']);
        $validated['is_division_specific'] = filter_var($validated['is_division_specific'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $slugSource = trim((string) ($validated['slug'] ?? ''));
        $baseSlug = $slugSource !== '' ? $slugSource : Str::slug($validated['name']);
        if ($baseSlug === '') {
            $baseSlug = 'memo-type';
        }
        $validated['slug'] = $this->uniqueSlug($baseSlug);
        $validated['is_system'] = false;
        $validated['sort_order'] = $validated['sort_order'] ?? (((int) MemoTypeDefinition::query()->max('sort_order')) + 1);

        $memo = MemoTypeDefinition::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Memo type created.',
            'data' => $memo->toApiArray(),
        ], 201);
    }

    public function ajaxUpdate(Request $request, MemoTypeDefinition $memoTypeDefinition): JsonResponse
    {
        $validated = $this->validatePayload($request, $memoTypeDefinition->id);
        $validated['fields_schema'] = MemoTypeDefinition::normalizeFieldsSchemaRows($validated['fields_schema']);
        if (array_key_exists('is_division_specific', $validated)) {
            $validated['is_division_specific'] = filter_var($validated['is_division_specific'], FILTER_VALIDATE_BOOLEAN);
        }
        if ($memoTypeDefinition->is_system) {
            unset($validated['slug']);
            $validated['is_system'] = true;
        } elseif (($validated['slug'] ?? '') === '') {
            unset($validated['slug']);
        } elseif (isset($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($validated['slug'], $memoTypeDefinition->id);
        }

        $memoTypeDefinition->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Memo type updated.',
            'data' => $memoTypeDefinition->fresh()->toApiArray(),
        ]);
    }

    public function ajaxDestroy(MemoTypeDefinition $memoTypeDefinition): JsonResponse
    {
        if ($memoTypeDefinition->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System memo types cannot be deleted.',
            ], 403);
        }

        $memoTypeDefinition->delete();

        return response()->json(['success' => true, 'message' => 'Memo type deleted.']);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRules = [
            'nullable',
            'string',
            'max:191',
            Rule::unique('memo_type_definitions', 'slug')->ignore($ignoreId),
            'regex:/^$|^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        ];

        $fieldTypes = implode(',', array_keys(MemoTypeDefinition::FIELD_TYPES));
        $sigStyles = implode(',', array_keys(MemoTypeDefinition::SIGNATURE_STYLES));

        return $request->validate([
            'name' => 'required|string|max:500',
            'slug' => $slugRules,
            'description' => 'nullable|string|max:5000',
            'ref_prefix' => 'nullable|string|max:32',
            'is_division_specific' => 'sometimes|boolean',
            'signature_style' => 'required|string|in:' . $sigStyles,
            'fields_schema' => 'required|array|min:1',
            'fields_schema.*.field' => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/',
            'fields_schema.*.display' => 'required|string|max:255',
            'fields_schema.*.field_type' => 'required|string|in:' . $fieldTypes,
            'fields_schema.*.required' => 'sometimes|boolean',
            'fields_schema.*.enabled' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:999999',
            'is_active' => 'sometimes|boolean',
        ]);
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = trim($slug) !== '' ? trim($slug) : 'memo-type';
        $candidate = $base;
        $n = 1;
        while (MemoTypeDefinition::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base . '-' . $n++;
        }

        return $candidate;
    }
}
