<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Exports\ItAssetsExport;
use App\Http\Controllers\Concerns\DownloadsPdfReports;
use App\Http\Controllers\Controller;
use App\Models\HelpdeskItAsset;
use App\Models\HelpdeskItAssetBrand;
use App\Models\HelpdeskItAssetCategory;
use App\Services\HelpdeskPdfReportService;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ItAssetController extends Controller
{
    use AuthorizesHelpdeskTools;
    use DownloadsPdfReports;

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
                'deployed' => $assets->where('status', HelpdeskItAsset::STATUS_DEPLOYED)->count(),
                'in_stock' => $assets->where('status', HelpdeskItAsset::STATUS_IN_STOCK)->count(),
                'repair' => $assets->where('status', HelpdeskItAsset::STATUS_REPAIR)->count(),
                'retired' => $assets->where('status', HelpdeskItAsset::STATUS_RETIRED)->count(),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->ensureItAssetManager($request);

        $rows = HelpdeskItAsset::query()
            ->with(['category', 'brandRelation'])
            ->orderBy('asset_tag')
            ->limit(5000)
            ->get();

        return Excel::download(
            new ItAssetsExport($rows),
            'it-assets-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function exportPdf(Request $request, HelpdeskPdfReportService $pdf): Response
    {
        $this->ensureItAssetManager($request);

        $assets = HelpdeskItAsset::query()
            ->with(['category', 'brandRelation'])
            ->orderBy('asset_tag')
            ->limit(2000)
            ->get();

        $rows = $assets->map(fn (HelpdeskItAsset $a) => [
            $a->asset_tag,
            $a->name,
            $a->category?->name,
            $a->brandRelation?->name ?? $a->brand,
            $a->model,
            $a->status,
            $a->assigned_name,
            $a->location,
            optional($a->purchase_date)?->format('Y-m-d'),
            $a->purchase_cost,
            $a->valuation['current_value'] ?? null,
        ])->all();

        $summaryLines = [
            'Assets: '.$assets->count(),
            'Deployed: '.$assets->where('status', HelpdeskItAsset::STATUS_DEPLOYED)->count(),
            'In stock: '.$assets->where('status', HelpdeskItAsset::STATUS_IN_STOCK)->count(),
            'Repair: '.$assets->where('status', HelpdeskItAsset::STATUS_REPAIR)->count(),
            'Retired: '.$assets->where('status', HelpdeskItAsset::STATUS_RETIRED)->count(),
        ];

        return $this->pdfTableDownload(
            $request,
            $pdf,
            'IT assets inventory',
            ['Tag', 'Name', 'Category', 'Brand', 'Model', 'Status', 'Assigned', 'Location', 'Purchased', 'Cost', 'Current value'],
            $rows,
            'it-assets-'.now()->format('Y-m-d').'.pdf',
            $summaryLines,
        );
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

    public function updateCategory(Request $request, HelpdeskItAssetCategory $category): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('helpdesk_it_asset_categories', 'slug')->ignore($category->id)],
            'icon' => ['nullable', 'string', 'max:64'],
            'default_useful_life_years' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->fill($validated);
        $category->save();

        return response()->json(['data' => $category->fresh()->loadCount('assets')]);
    }

    public function destroyCategory(Request $request, HelpdeskItAssetCategory $category): JsonResponse
    {
        $this->ensureItAssetManager($request);

        if ($category->assets()->exists()) {
            abort(422, 'Cannot delete a category that still has assets. Move or retire them first.');
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function brands(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $rows = HelpdeskItAssetBrand::query()
            ->withCount('assets')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeBrand(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('helpdesk_it_asset_brands', 'slug')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if (HelpdeskItAssetBrand::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $row = HelpdeskItAssetBrand::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function updateBrand(Request $request, HelpdeskItAssetBrand $brand): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('helpdesk_it_asset_brands', 'slug')->ignore($brand->id)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->fill($validated);
        $brand->save();

        if (array_key_exists('name', $validated)) {
            HelpdeskItAsset::query()
                ->where('brand_id', $brand->id)
                ->update(['brand' => $brand->name, 'updated_at' => now()]);
        }

        return response()->json(['data' => $brand->fresh()->loadCount('assets')]);
    }

    public function destroyBrand(Request $request, HelpdeskItAssetBrand $brand): JsonResponse
    {
        $this->ensureItAssetManager($request);

        if ($brand->assets()->exists()) {
            abort(422, 'Cannot delete a brand that is still used by assets. Reassign them first.');
        }

        $brand->delete();

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $query = HelpdeskItAsset::query()->with([
            'category:id,name,slug,icon,default_useful_life_years',
            'brandRelation:id,name,slug',
        ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', (int) $request->input('brand_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('assigned_staff_id')) {
            $query->where('assigned_staff_id', (int) $request->input('assigned_staff_id'));
        }
        if ($request->boolean('unassigned')) {
            $query->where(function ($q) {
                $q->whereNull('assigned_staff_id')->orWhere('assigned_staff_id', 0);
            });
        }
        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('asset_tag', 'like', $q)
                    ->orWhere('name', 'like', $q)
                    ->orWhere('brand', 'like', $q)
                    ->orWhere('model', 'like', $q)
                    ->orWhere('serial_number', 'like', $q)
                    ->orWhere('assigned_name', 'like', $q)
                    ->orWhere('location', 'like', $q)
                    ->orWhere('notes', 'like', $q);
            });
        }

        $rows = $query->orderByDesc('updated_at')->paginate(min(100, max(10, (int) $request->input('per_page', 25))));

        return response()->json($rows);
    }

    public function store(Request $request, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $this->validatedAssetPayload($request);
        $validated = $this->applyBrandAndAssignee($validated, $directory);

        $row = HelpdeskItAsset::query()->create(array_merge($validated, [
            'created_by_user_id' => $request->user()?->id,
        ]));
        $row->load(['category:id,name,slug,icon,default_useful_life_years', 'brandRelation:id,name,slug']);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskItAsset $asset, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureItAssetManager($request);

        $validated = $this->validatedAssetPayload($request, $asset);
        $validated = $this->applyBrandAndAssignee($validated, $directory, $asset);

        $asset->fill($validated);
        $asset->save();
        $asset->load(['category:id,name,slug,icon,default_useful_life_years', 'brandRelation:id,name,slug']);

        return response()->json(['data' => $asset]);
    }

    public function destroy(Request $request, HelpdeskItAsset $asset): JsonResponse
    {
        $this->ensureItAssetManager($request);
        $asset->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAssetPayload(Request $request, ?HelpdeskItAsset $asset = null): array
    {
        $uniqueTag = Rule::unique('helpdesk_it_assets', 'asset_tag');
        if ($asset) {
            $uniqueTag = $uniqueTag->ignore($asset->id);
        }

        return $request->validate([
            'asset_tag' => [$asset ? 'sometimes' : 'required', 'string', 'max:64', $uniqueTag],
            'category_id' => [$asset ? 'sometimes' : 'required', 'integer', 'exists:helpdesk_it_asset_categories,id'],
            'name' => [$asset ? 'sometimes' : 'required', 'string', 'max:191'],
            'brand_id' => ['nullable', 'integer', 'exists:helpdesk_it_asset_brands,id'],
            'brand' => ['nullable', 'string', 'max:191'],
            'model' => ['nullable', 'string', 'max:191'],
            'serial_number' => ['nullable', 'string', 'max:191'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'assigned_staff_id' => ['nullable', 'integer', 'min:1'],
            'assigned_name' => ['nullable', 'string', 'max:191'],
            'status' => [$asset ? 'sometimes' : 'nullable', 'string', Rule::in(['in_stock', 'deployed', 'repair', 'retired'])],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyBrandAndAssignee(
        array $validated,
        StaffDirectoryLookupService $directory,
        ?HelpdeskItAsset $existing = null,
    ): array {
        if (array_key_exists('brand_id', $validated)) {
            $brandId = $validated['brand_id'] ? (int) $validated['brand_id'] : null;
            if ($brandId) {
                $brand = HelpdeskItAssetBrand::query()->find($brandId);
                $validated['brand'] = $brand?->name;
            } else {
                $validated['brand'] = null;
            }
        }

        if (array_key_exists('assigned_staff_id', $validated)) {
            $staffId = $validated['assigned_staff_id'] ? (int) $validated['assigned_staff_id'] : null;
            if ($staffId) {
                $resolved = $directory->resolveByStaffId($staffId);
                if ($resolved === null) {
                    abort(422, 'Assigned staff not found in the Staff directory. Run directory sync under Settings → Jobs.');
                }
                $validated['assigned_name'] = $resolved['name'];
                if (($validated['status'] ?? $existing?->status) === HelpdeskItAsset::STATUS_IN_STOCK
                    || (! array_key_exists('status', $validated) && $existing === null && $staffId)) {
                    $validated['status'] = HelpdeskItAsset::STATUS_DEPLOYED;
                }
            } else {
                $validated['assigned_name'] = null;
            }
        }

        return $validated;
    }
}
