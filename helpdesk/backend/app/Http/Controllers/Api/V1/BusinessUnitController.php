<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use App\Services\AgentCategoryRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
    /**
     * Active business units that have at least one active issue category covered by
     * an eligible agent (request form). Units without agent coverage are omitted.
     */
    public function index(Request $request, AgentCategoryRoutingService $routing): JsonResponse
    {
        $coveredCategoryIds = $routing->categoryIdsCoveredByEligibleAgents();
        $catchAll = $routing->hasEligibleCatchAllAgent();

        $units = HelpdeskBusinessUnit::query()
            ->where('is_active', true)
            ->whereHas('categories', function ($q) use ($coveredCategoryIds, $catchAll) {
                $q->where('is_active', true);
                if (! $catchAll) {
                    if ($coveredCategoryIds === []) {
                        $q->whereRaw('0 = 1');
                    } else {
                        $q->whereIn('id', $coveredCategoryIds);
                    }
                }
            })
            ->with(['categories' => function ($q) use ($coveredCategoryIds, $catchAll) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
                if (! $catchAll) {
                    if ($coveredCategoryIds === []) {
                        $q->whereRaw('0 = 1');
                    } else {
                        $q->whereIn('id', $coveredCategoryIds);
                    }
                }
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HelpdeskBusinessUnit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'description' => $u->description,
                'sort_order' => $u->sort_order,
                'allows_anonymous' => (bool) $u->allows_anonymous,
                'allows_asset_link_on_resolve' => (bool) $u->allows_asset_link_on_resolve,
                'categories' => $u->categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'default_priority' => $c->default_priority ?? 'medium',
                ])->values(),
            ])
            ->values();

        return response()->json([
            'data' => $units,
            'meta' => [
                'show_issue_category_on_request_form' => HelpdeskSetting::showIssueCategoryOnRequestForm(),
                'agent_coverage_enforced' => true,
            ],
        ]);
    }
}
