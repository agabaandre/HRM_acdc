<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SapImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'period_ym' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
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
            $validated['period_ym'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function latest(): JsonResponse
    {
        $import = DB::table('fin_sap_imports')
            ->where('is_latest', true)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $import,
        ]);
    }

    public function history(): JsonResponse
    {
        $columns = [
            'id',
            'original_filename',
            'budget_year',
            'row_count',
            'total_row_count',
            'apm_updated_count',
            'sync_status',
            'is_latest',
            'created_at',
        ];
        if (Schema::hasColumn('fin_sap_imports', 'period_ym')) {
            $columns[] = 'period_ym';
        }

        $query = DB::table('fin_sap_imports')->select($columns);
        if (Schema::hasColumn('fin_sap_imports', 'period_ym')) {
            $query->orderByDesc('period_ym')->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(36)->get(),
        ]);
    }

    public function fundCenterExpenses(Request $request, string $fundCenter): JsonResponse
    {
        $importId = (int) $request->query('import_id', 0);
        $expenses = $this->imports->fundCenterExpenses(
            $fundCenter,
            $importId > 0 ? $importId : null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'fund_center' => $fundCenter,
                'import_id' => $importId > 0 ? $importId : null,
                'expenses' => $expenses,
                'count' => count($expenses),
            ],
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
