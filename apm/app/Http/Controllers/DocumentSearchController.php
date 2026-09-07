<?php

namespace App\Http\Controllers;

use App\Services\DocumentNumberSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSearchController extends Controller
{
    public function __construct(private DocumentNumberSearchService $search)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $staffId = (int) user_session('staff_id');
        $divisionId = (int) user_session('division_id');

        $results = $this->search->search(
            $validated['q'],
            (int) $validated['year'],
            $staffId > 0 ? $staffId : null,
            $divisionId > 0 ? $divisionId : null,
            user_session('permissions', []) ?: [],
        );

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function years(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'years' => $this->search->availableYears(),
                'default_year' => (int) date('Y'),
            ],
        ]);
    }
}
