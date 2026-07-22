<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
    /**
     * Active business units that have at least one active issue category (request form).
     */
    public function index(Request $request): JsonResponse
    {
        $units = HelpdeskBusinessUnit::query()
            ->where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->where('is_active', true))
            ->with(['categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
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
            ],
        ]);
    }
}
