<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExcelRiskImportService;
use App\Services\RiskSettingsService;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiskImportController extends Controller
{
    public function __construct(
        private readonly RiskSettingsService $settings,
        private readonly ExcelRiskImportService $importer,
    ) {}

    public function status(): JsonResponse
    {
        $enabled = $this->settings->importEnabled();
        $batch = DB::table('rr_import_batches')->orderByDesc('id')->first();
        $pending = [];
        if ($batch && in_array($batch->status, ['matched_committed', 'preview'], true)) {
            $pending = DB::table('rr_import_staged_rows')
                ->where('batch_id', $batch->id)
                ->where('status', 'pending')
                ->select('business_unit', DB::raw('count(*) as c'))
                ->groupBy('business_unit')
                ->orderByDesc('c')
                ->get()
                ->map(fn ($r) => ['business_unit' => $r->business_unit, 'count' => (int) $r->c])
                ->all();
        }

        return response()->json([
            'import_enabled' => $enabled,
            'latest_batch' => $batch,
            'pending_unmatched' => $pending,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->assertImportAllowed($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $file = $request->file('file');
        $tmp = $file->getRealPath();
        if ($tmp === false) {
            return response()->json(['message' => 'Upload failed.'], 422);
        }

        try {
            $result = $this->importer->previewUpload(
                $tmp,
                $file->getClientOriginalName(),
                (int) $request->attributes->get('risk_staff_id')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function commitMatched(Request $request, int $batchId): JsonResponse
    {
        $this->assertImportAllowed($request);
        try {
            $result = $this->importer->commitMatched($batchId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function applyMappings(Request $request, int $batchId): JsonResponse
    {
        $this->assertImportAllowed($request);
        $data = $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.business_unit' => 'required|string|max:255',
            'mappings.*.division_id' => 'required|integer|min:1',
            'mappings.*.directorate_id' => 'nullable|integer|min:1',
        ]);

        try {
            $result = $this->importer->applyMappings($batchId, $data['mappings']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    private function assertImportAllowed(Request $request): void
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        if (! RiskPermissions::canManage($permissions)) {
            abort(response()->json(['message' => 'Forbidden.'], 403));
        }
        if (! $this->settings->importEnabled()) {
            abort(response()->json(['message' => 'Excel import is disabled.'], 403));
        }
    }
}
