<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use App\Services\BusinessUnitMailboxIntakeService;
use App\Services\CategoryBusinessUnitRemapService;
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
            'allows_information_system_link_on_resolve' => ['sometimes', 'boolean'],
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
            'allows_information_system_link_on_resolve' => $validated['allows_information_system_link_on_resolve'] ?? false,
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
            'allows_information_system_link_on_resolve' => ['sometimes', 'boolean'],
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

        abort(422, 'Business units cannot be deleted directly. Use Remap to merge into another business unit.');
    }

    public function remap(
        Request $request,
        HelpdeskBusinessUnit $businessUnit,
        CategoryBusinessUnitRemapService $remap,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'target_business_unit_id' => [
                'required',
                'integer',
                Rule::exists('helpdesk_business_units', 'id'),
            ],
        ]);

        $target = HelpdeskBusinessUnit::query()->findOrFail((int) $validated['target_business_unit_id']);
        if ($target->id === $businessUnit->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'target_business_unit_id' => 'Choose a different business unit to remap into.',
            ]);
        }

        $sourceName = $businessUnit->name;
        $targetName = $target->name;
        $sourceId = $businessUnit->id;
        $counts = $remap->remapBusinessUnit($businessUnit, $target);

        return response()->json([
            'message' => "Remapped “{$sourceName}” into “{$targetName}”.",
            'data' => [
                'source_id' => $sourceId,
                'target_id' => $target->id,
                'moved' => $counts,
            ],
        ]);
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

    /**
     * Create tickets from unread mailbox messages (same Graph path as Test read).
     */
    public function processEmailIntake(
        Request $request,
        HelpdeskBusinessUnit $businessUnit,
        BusinessUnitMailboxIntakeService $intake,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        if (! HelpdeskSetting::emailTicketIntakeEnabled()) {
            return response()->json([
                'message' => 'Turn on “Allow email submission of tickets” under Settings → General before logging mailbox mail.',
                'reason' => 'master_disabled',
            ], 422);
        }

        if (! $businessUnit->email_intake_enabled) {
            return response()->json([
                'message' => 'Enable email intake on this business unit first.',
                'reason' => 'unit_disabled',
            ], 422);
        }

        try {
            $result = $intake->pollUnit($businessUnit);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Could not log mailbox mail: '.$e->getMessage(),
            ], 502);
        }

        $created = (int) ($result['created'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $errors = (int) ($result['errors'] ?? 0);
        $reason = $result['reason'] ?? null;

        $message = match (true) {
            $created > 0 => "Logged {$created} ticket".($created === 1 ? '' : 's')." from the mailbox.",
            $errors > 0 => "No tickets created ({$errors} message".($errors === 1 ? '' : 's').' failed). Check helpdesk logs.',
            $skipped > 0 => "No new tickets. {$skipped} message".($skipped === 1 ? '' : 's').' already imported or skipped.',
            $reason === 'no_mailbox' => 'This business unit has no valid support mailbox.',
            default => 'No unread mailbox messages to log.',
        };

        return response()->json([
            'message' => $message,
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
                'reason' => $reason,
            ],
        ]);
    }
}
