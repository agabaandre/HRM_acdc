<?php

namespace App\Http\Controllers;

use App\Models\FundCode;
use App\Services\IntramuralSapBudgetExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntramuralSapBudgetExecutionController extends Controller
{
    public function __construct(
        private readonly IntramuralSapBudgetExecutionService $service,
    ) {}

    public function index(): View
    {
        $currentYear = (int) date('Y');
        $years = FundCode::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->filter(fn ($y) => $y > 0)
            ->values()
            ->all();
        if (! in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }

        return view('intramural-sap-budget-execution.index', [
            'pageConfig' => [
                'currentYear' => $currentYear,
                'years' => $years,
                'routes' => [
                    'data' => route('intramural-sap-budget-execution.data'),
                    'documents' => url('/intramural-sap-budget-execution/__ID__/documents'),
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $year = $request->filled('year') ? (int) $request->input('year') : (int) date('Y');
        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;
        $search = $request->input('search');

        $payload = $this->service->list($year, $divisionId, is_string($search) ? $search : null);

        return response()->json([
            'success' => true,
            'data' => $payload['items'],
            'meta' => $payload['meta'],
        ]);
    }

    public function documents(int $fundCode): JsonResponse
    {
        $exists = FundCode::query()->whereKey($fundCode)->exists();
        if (! $exists) {
            return response()->json(['success' => false, 'message' => 'Fund code not found.'], 404);
        }

        $docs = $this->service->documents($fundCode);

        return response()->json([
            'success' => true,
            'data' => $docs,
        ]);
    }
}
