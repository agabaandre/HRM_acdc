<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fund codes API. Responses include funder and partner only (no activities) for light consumption by external systems.
 */
class ApmFundCodeController extends Controller
{
    /**
     * List fund codes. Optional: ?is_active=1, ?year=2025, ?division_id=1, ?funder_id=1, ?partner_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = FundCode::with(['funder:id,name', 'partner:id,name'])
            ->select(['id', 'code', 'activity', 'year', 'funder_id', 'partner_id', 'fund_type_id', 'division_id', 'is_active', 'cost_centre', 'amert_code', 'fund', 'budget_balance', 'approved_budget', 'uploaded_budget', 'created_at', 'updated_at']);

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }
        if ($request->filled('year')) {
            $query->where('year', (int) $request->query('year'));
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->query('division_id'));
        }
        if ($request->filled('funder_id')) {
            $query->where('funder_id', (int) $request->query('funder_id'));
        }
        if ($request->filled('partner_id')) {
            $query->where('partner_id', (int) $request->query('partner_id'));
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $items = $query->orderBy('year', 'desc')->orderBy('code')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Single fund code with funder and partner only (no activities).
     */
    public function show(int $id): JsonResponse
    {
        $fundCode = FundCode::with(['funder:id,name', 'partner:id,name'])
            ->select(['id', 'code', 'activity', 'year', 'funder_id', 'partner_id', 'fund_type_id', 'division_id', 'is_active', 'cost_centre', 'amert_code', 'fund', 'budget_balance', 'approved_budget', 'uploaded_budget', 'created_at', 'updated_at'])
            ->find($id);

        if (!$fundCode) {
            return response()->json(['success' => false, 'message' => 'Fund code not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $fundCode]);
    }

    /**
     * Create a fund code.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:fund_codes,code',
            'activity' => 'nullable|string|max:65535',
            'year' => 'required|integer|min:2020|max:2100',
            'funder_id' => 'nullable|exists:funders,id',
            'partner_id' => 'nullable|exists:partners,id',
            'fund_type_id' => 'nullable|exists:fund_types,id',
            'division_id' => 'nullable|exists:divisions,id',
            'cost_centre' => 'nullable|string|max:255',
            'amert_code' => 'nullable|string|max:255',
            'fund' => 'nullable|string|max:255',
            'budget_balance' => 'nullable|string|max:255',
            'approved_budget' => 'nullable|string|max:255',
            'uploaded_budget' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $fundCode = FundCode::create($validated);
        $fundCode->load(['funder:id,name', 'partner:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Fund code created.',
            'data' => $fundCode,
        ], 201);
    }

    /**
     * Update a fund code.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $fundCode = FundCode::find($id);
        if (!$fundCode) {
            return response()->json(['success' => false, 'message' => 'Fund code not found.'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:255|unique:fund_codes,code,' . $id,
            'activity' => 'nullable|string|max:65535',
            'year' => 'sometimes|integer|min:2020|max:2100',
            'funder_id' => 'nullable|exists:funders,id',
            'partner_id' => 'nullable|exists:partners,id',
            'fund_type_id' => 'nullable|exists:fund_types,id',
            'division_id' => 'nullable|exists:divisions,id',
            'cost_centre' => 'nullable|string|max:255',
            'amert_code' => 'nullable|string|max:255',
            'fund' => 'nullable|string|max:255',
            'budget_balance' => 'nullable|string|max:255',
            'approved_budget' => 'nullable|string|max:255',
            'uploaded_budget' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $fundCode->update($validated);
        $fundCode->load(['funder:id,name', 'partner:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Fund code updated.',
            'data' => $fundCode,
        ]);
    }
}
