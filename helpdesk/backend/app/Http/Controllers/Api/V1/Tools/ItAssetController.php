<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskItAsset;
use App\Models\HelpdeskItAssetCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ItAssetController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function summary(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $assets = HelpdeskItAsset::query()->with('category:id,name,slug,icon')->get();
        $totalPurchase = round($assets->sum('purchase_cost'), 2);
        $totalCurrent = round($assets->sum(fn ($a) => $a->valuation['current_value'] ?? 0), 2);

        $byCategory = $assets->groupBy('category_id')->map(function ($group) {
            $first = $group->first();

            return [
                'category_id' => (int) $first->category_id,
                'category_name' => $first->category?->name ?? 'Unknown',
                'count' => $group->count(),
                'purchase_total' => round($group->sum('purchase_cost'), 2),
                'current_value_total' => round($group->sum(fn ($a) => $a->valuation['current_value'] ?? 0), 2),
            ];
        })->values();

        return response()->json([
            'data' => [
                'asset_count' => $assets->count(),
                'total_purchase_cost' => $totalPurchase,
                'total_current_value' => $totalCurrent,
                'total_depreciation' => round(max(0, $totalPurchase - $totalCurrent), 2),
                'by_category' => $byCategory,
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $rows = HelpdeskItAssetCategory::query()
            ->withCount('assets')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('helpdesk_it_asset_categories', 'slug')],
            'icon' => ['nullable', 'string', 'max:64'],
            'default_useful_life_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $row = HelpdeskItAssetCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? 'bx-package',
            'default_useful_life_years' => $validated['default_useful_life_years'] ?? 3,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $query = HelpdeskItAsset::query()->with('category:id,name,slug,icon,default_useful_life_years');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('asset_tag', 'like', $q)
                    ->orWhere('name', 'like', $q)
                    ->orWhere('serial_number', 'like', $q)
                    ->orWhere('assigned_name', 'like', $q);
            });
        }

        $rows = $query->orderByDesc('updated_at')->paginate(min(100, max(10, (int) $request->input('per_page', 25))));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'asset_tag' => ['required', 'string', 'max:64', Rule::unique('helpdesk_it_assets', 'asset_tag')],
            'category_id' => ['required', 'integer', 'exists:helpdesk_it_asset_categories,id'],
            'name' => ['required', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'model' => ['nullable', 'string', 'max:191'],
            'serial_number' => ['nullable', 'string', 'max:191'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'assigned_staff_id' => ['nullable', 'integer', 'min:1'],
            'assigned_name' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', Rule::in(['in_stock', 'deployed', 'repair', 'retired'])],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        $row = HelpdeskItAsset::query()->create(array_merge($validated, [
            'created_by_user_id' => $request->user()?->id,
        ]));
        $row->load('category:id,name,slug,icon,default_useful_life_years');

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskItAsset $asset): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'asset_tag' => ['sometimes', 'string', 'max:64', Rule::unique('helpdesk_it_assets', 'asset_tag')->ignore($asset->id)],
            'category_id' => ['sometimes', 'integer', 'exists:helpdesk_it_asset_categories,id'],
            'name' => ['sometimes', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'model' => ['nullable', 'string', 'max:191'],
            'serial_number' => ['nullable', 'string', 'max:191'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'assigned_staff_id' => ['nullable', 'integer', 'min:1'],
            'assigned_name' => ['nullable', 'string', 'max:191'],
            'status' => ['sometimes', 'string', Rule::in(['in_stock', 'deployed', 'repair', 'retired'])],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset->fill($validated);
        $asset->save();
        $asset->load('category:id,name,slug,icon,default_useful_life_years');

        return response()->json(['data' => $asset]);
    }

    public function destroy(Request $request, HelpdeskItAsset $asset): JsonResponse
    {
        $this->ensureItAssetManager($request);
        $asset->delete();

        return response()->json(['ok' => true]);
    }
}
