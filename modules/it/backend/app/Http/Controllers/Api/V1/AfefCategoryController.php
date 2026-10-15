<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceAfefCategoryService;
use App\Support\FinancePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AfefCategoryController extends Controller
{
    public function __construct(private readonly FinanceAfefCategoryService $categories = new FinanceAfefCategoryService) {}

    public function show(Request $request): JsonResponse
    {
        $this->categories->ensureDefaults();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $this->categories->all(),
                'can_edit' => FinancePermissions::canManageFundingPortfolio(
                    $request->attributes->get('risk_permissions', [])
                ),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.key' => ['nullable', 'string', 'max:64'],
            'categories.*.label' => ['required', 'string', 'max:191'],
            'categories.*.sort_order' => ['nullable', 'integer'],
            'categories.*.is_active' => ['nullable', 'boolean'],
        ]);
        $categories = $this->categories->save($validated['categories']);

        return response()->json([
            'success' => true,
            'message' => 'AfEF categories saved.',
            'data' => ['categories' => $categories],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        if (! FinancePermissions::canManageFundingPortfolio($request->attributes->get('risk_permissions', []))) {
            abort(response()->json([
                'success' => false,
                'message' => 'Managing AfEF categories requires the manage_funding_portfolio permission.',
            ], 403));
        }
    }
}
