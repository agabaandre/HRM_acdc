<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSlaRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHelpdeskSlaRuleController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $rows = HelpdeskSlaRule::query()
            ->with('category:id,name,slug')
            ->orderBy('name')
            ->get()
            ->map(fn (HelpdeskSlaRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'category_id' => $r->category_id,
                'category' => $r->category ? [
                    'id' => $r->category->id,
                    'name' => $r->category->name,
                    'slug' => $r->category->slug,
                ] : null,
                'response_minutes' => $r->response_minutes,
                'resolution_minutes' => $r->resolution_minutes,
                'is_active' => $r->is_active,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],
            'response_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = HelpdeskSlaRule::query()->create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'] ?? null,
            'response_minutes' => $validated['response_minutes'],
            'resolution_minutes' => $validated['resolution_minutes'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $row->load('category:id,name,slug');

        return response()->json(['data' => $this->serializeRule($row)], 201);
    }

    public function update(Request $request, HelpdeskSlaRule $slaRule): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],
            'response_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slaRule->fill($validated);
        $slaRule->save();
        $slaRule->load('category:id,name,slug');

        return response()->json(['data' => $this->serializeRule($slaRule)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(HelpdeskSlaRule $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'category_id' => $r->category_id,
            'category' => $r->category ? [
                'id' => $r->category->id,
                'name' => $r->category->name,
                'slug' => $r->category->slug,
            ] : null,
            'response_minutes' => $r->response_minutes,
            'resolution_minutes' => $r->resolution_minutes,
            'is_active' => $r->is_active,
        ];
    }
}
