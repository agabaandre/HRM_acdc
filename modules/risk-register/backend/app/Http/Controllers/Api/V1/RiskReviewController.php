<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RiskReviewController extends Controller
{
    public function store(Request $request, int $id, RiskReviewService $reviews): JsonResponse
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'quarter' => 'required|integer|min:1|max:4',
            'likelihood' => 'required|integer|min:1|max:5',
            'impact' => 'required|integer|min:1|max:5',
            'mitigation_strategy' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
        ]);

        try {
            $row = $reviews->create($id, $data, (int) $request->attributes->get('risk_staff_id'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $row], 201);
    }

    public function trends(int $id, RiskReviewService $reviews): JsonResponse
    {
        return response()->json($reviews->trends($id));
    }
}
