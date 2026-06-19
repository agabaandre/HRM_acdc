<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSupportGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminHelpdeskSupportGroupController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $groups = HelpdeskSupportGroup::query()
            ->with([
                'categories:id,name,slug',
                'members:id,name,email',
            ])
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $groups->map(fn (HelpdeskSupportGroup $g) => $this->serializeGroup($g)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $this->validatePayload($request);
        $slug = $this->uniqueSlug($validated['name']);

        $group = HelpdeskSupportGroup::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_system' => false,
        ]);

        $this->syncRelations($group, $validated);

        return response()->json([
            'data' => $this->serializeGroup($group->fresh()->load(['categories:id,name,slug', 'members:id,name,email'])->loadCount('members')),
        ], 201);
    }

    public function update(Request $request, HelpdeskSupportGroup $group): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $this->validatePayload($request, $group->id);

        $group->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? $group->sort_order),
            'is_active' => (bool) ($validated['is_active'] ?? $group->is_active),
        ]);
        $group->save();

        $this->syncRelations($group, $validated);

        return response()->json([
            'data' => $this->serializeGroup($group->fresh()->load(['categories:id,name,slug', 'members:id,name,email'])->loadCount('members')),
        ]);
    }

    public function destroy(Request $request, HelpdeskSupportGroup $group): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        if ($group->is_system) {
            abort(422, 'System support groups cannot be deleted. Deactivate the group instead.');
        }

        $group->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreGroupId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:helpdesk_categories,id'],
            'member_user_ids' => ['present', 'array'],
            'member_user_ids.*' => ['integer', 'exists:users,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRelations(HelpdeskSupportGroup $group, array $validated): void
    {
        $group->categories()->sync($validated['category_ids'] ?? []);
        $group->members()->sync($validated['member_user_ids'] ?? []);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $n = 1;
        while (HelpdeskSupportGroup::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGroup(HelpdeskSupportGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'sort_order' => $group->sort_order,
            'is_active' => (bool) $group->is_active,
            'is_system' => (bool) $group->is_system,
            'members_count' => (int) ($group->members_count ?? $group->members->count()),
            'categories' => $group->categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug ?? null,
            ])->values(),
            'members' => $group->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
            ])->values(),
        ];
    }
}
