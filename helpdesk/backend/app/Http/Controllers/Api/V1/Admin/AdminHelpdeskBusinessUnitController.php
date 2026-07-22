<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskBusinessUnit;
use App\Services\BusinessUnitMailboxIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class AdminHelpdeskBusinessUnitController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $rows = HelpdeskBusinessUnit::query()
            ->withCount([
                'categories',
                'categories as active_categories_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->with(['categories' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
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
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('helpdesk_business_units', 'slug')],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'allows_anonymous' => ['sometimes', 'boolean'],
            'allows_asset_link_on_resolve' => ['sometimes', 'boolean'],
            'support_mailbox' => ['nullable', 'email', 'max:191'],
            'email_intake_enabled' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if (HelpdeskBusinessUnit::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $row = HelpdeskBusinessUnit::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'allows_anonymous' => $validated['allows_anonymous'] ?? false,
            'allows_asset_link_on_resolve' => $validated['allows_asset_link_on_resolve'] ?? false,
            'support_mailbox' => $validated['support_mailbox'] ?? null,
            'email_intake_enabled' => $validated['email_intake_enabled'] ?? false,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskBusinessUnit $businessUnit): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('helpdesk_business_units', 'slug')->ignore($businessUnit->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['sometimes', 'boolean'],
            'allows_anonymous' => ['sometimes', 'boolean'],
            'allows_asset_link_on_resolve' => ['sometimes', 'boolean'],
            'support_mailbox' => ['sometimes', 'nullable', 'email', 'max:191'],
            'email_intake_enabled' => ['sometimes', 'boolean'],
        ]);

        $businessUnit->fill($validated);
        $businessUnit->save();

        return response()->json(['data' => $businessUnit->fresh()->loadCount('categories')]);
    }

    public function destroy(Request $request, HelpdeskBusinessUnit $businessUnit): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        if ($businessUnit->categories()->exists()) {
            abort(422, 'Cannot delete a business unit that has categories. Move or deactivate them first.');
        }

        $businessUnit->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Test-read unread messages from the unit's support mailbox (no tickets created).
     */
    public function testEmailRead(
        Request $request,
        HelpdeskBusinessUnit $businessUnit,
        BusinessUnitMailboxIntakeService $intake,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        $top = max(1, min(25, (int) $request->input('top', 10)));

        try {
            $preview = $intake->previewUnread($businessUnit, $top);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Could not read mailbox: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Mailbox read OK — listing unread messages (dry run; no tickets created).',
            'data' => $preview,
        ]);
    }
}
