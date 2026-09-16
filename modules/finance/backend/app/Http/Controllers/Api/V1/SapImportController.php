<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SapImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SapImportController extends Controller
{
    public function __construct(
        private readonly SapImportService $imports,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);

        $file = $validated['file'];
        $stored = $file->store('sap-imports');
        $absolute = Storage::path($stored);

        $userId = null;
        if ($request->attributes->has('risk_staff_id')) {
            $userId = (int) $request->attributes->get('risk_staff_id');
        }

        $result = $this->imports->import(
            $absolute,
            (string) $file->getClientOriginalName(),
            $userId,
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function latest(): JsonResponse
    {
        $year = (int) date('Y');
        $import = DB::table('fin_sap_imports')
            ->where('budget_year', $year)
            ->where('is_latest', true)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $import,
        ]);
    }

    public function retrySync(int $id): JsonResponse
    {
        $result = $this->imports->retrySync($id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
