<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinGrant;
use App\Services\FinanceApmOrgService;
use App\Support\FinancePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GrantController extends Controller
{
    public function __construct(private readonly FinanceApmOrgService $apmOrg = new FinanceApmOrgService) {}

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('fin_grants')) {
            return response()->json(['success' => true, 'data' => ['grants' => [], 'funders' => []]]);
        }

        $q = FinGrant::query()->orderBy('name');
        if ($request->boolean('active_only', false)) {
            $q->where('is_active', true);
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('short_name', 'like', $like)
                    ->orWhere('funder_name', 'like', $like)
                    ->orWhere('budget_code', 'like', $like);
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'grants' => $q->get(),
                'funders' => $this->apmOrg->apmFunders(false),
                'can_edit' => FinancePermissions::canManageFundingPortfolio(
                    $request->attributes->get('risk_permissions', [])
                ),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $grant = FinGrant::query()->create($this->validated($request));

        return response()->json(['success' => true, 'message' => 'Grant created.', 'data' => ['grant' => $grant]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        $grant = FinGrant::query()->findOrFail($id);
        $grant->fill($this->validated($request, $id))->save();

        return response()->json(['success' => true, 'message' => 'Grant updated.', 'data' => ['grant' => $grant->fresh()]]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        FinGrant::query()->whereKey($id)->delete();

        return response()->json(['success' => true, 'message' => 'Grant deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'short_name' => ['nullable', 'string', 'max:191'],
            'funder_id' => ['nullable', 'integer'],
            'funder_name' => ['nullable', 'string', 'max:255'],
            'end_date' => ['nullable', 'date'],
            'total_budget' => ['nullable', 'numeric'],
            'direct_cost_usd' => ['nullable', 'numeric'],
            'indirect_cost' => ['nullable', 'numeric'],
            'indirect_pct' => ['nullable', 'numeric'],
            'budget_code' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $name = trim((string) $validated['name']);
        $dup = FinGrant::query()->where('name', $name);
        if ($ignoreId) {
            $dup->where('id', '!=', $ignoreId);
        }
        if ($dup->exists()) {
            abort(response()->json(['success' => false, 'message' => 'A grant with this name already exists.'], 422));
        }

        if (! empty($validated['funder_id']) && empty($validated['funder_name'])) {
            foreach ($this->apmOrg->apmFunders(false) as $f) {
                if ($f['id'] === (int) $validated['funder_id']) {
                    $validated['funder_name'] = $f['name'];
                    break;
                }
            }
        }

        $validated['name'] = $name;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['is_active'] = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : true;

        return $validated;
    }

    private function authorizeManage(Request $request): void
    {
        if (! FinancePermissions::canManageFundingPortfolio($request->attributes->get('risk_permissions', []))) {
            abort(response()->json([
                'success' => false,
                'message' => 'Managing grants requires the manage_funding_portfolio permission.',
            ], 403));
        }
    }
}
