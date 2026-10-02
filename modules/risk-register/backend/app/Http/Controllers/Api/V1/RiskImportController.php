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

        // Pending staged rows across all batches (do not hide when a newer preview batch exists).
        $pendingRows = DB::table('rr_import_staged_rows')
            ->where('status', 'pending')
            ->select('batch_id', 'business_unit', DB::raw('count(*) as c'))
            ->groupBy('batch_id', 'business_unit')
            ->orderByDesc('c')
            ->get();

        $pendingByBatch = [];
        $pendingFlat = [];
        foreach ($pendingRows as $row) {
            $bu = (string) $row->business_unit;
            $count = (int) $row->c;
            $batchId = (int) $row->batch_id;
            $pendingByBatch[$batchId] ??= [];
            $pendingByBatch[$batchId][] = ['business_unit' => $bu, 'count' => $count];
            $pendingFlat[$bu] = ($pendingFlat[$bu] ?? 0) + $count;
        }

        $pending = [];
        foreach ($pendingFlat as $bu => $count) {
            $pending[] = ['business_unit' => $bu, 'count' => $count];
        }
        usort($pending, static fn ($a, $b) => $b['count'] <=> $a['count']);

        $mappingBatchId = null;
        if ($pendingByBatch !== []) {
            $mappingBatchId = max(array_keys($pendingByBatch));
        } elseif ($batch && $batch->status === 'matched_committed') {
            $mappingBatchId = (int) $batch->id;
        }

        $unmappedRisks = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risks', 'unmapped_business_unit')) {
            $unmappedRisks = DB::table('rr_risks')
                ->whereNotNull('unmapped_business_unit')
                ->where('unmapped_business_unit', '!=', '')
                ->select('unmapped_business_unit as business_unit', DB::raw('count(*) as c'))
                ->groupBy('unmapped_business_unit')
                ->orderByDesc('c')
                ->get()
                ->map(fn ($r) => [
                    'business_unit' => (string) $r->business_unit,
                    'count' => (int) $r->c,
                ])
                ->all();
        }

        return response()->json([
            'import_enabled' => $enabled,
            'latest_batch' => $batch,
            'mapping_batch_id' => $mappingBatchId,
            'pending_unmatched' => $pending,
            'pending_by_batch' => $pendingByBatch,
            'unmapped_risks' => $unmappedRisks,
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

    /**
     * Remap risks already imported with unmapped_business_unit (CLI / legacy).
     */
    public function remapUnmappedRisks(Request $request): JsonResponse
    {
        $this->assertImportAllowed($request);
        $data = $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.business_unit' => 'required|string|max:255',
            'mappings.*.division_id' => 'required|integer|min:1',
            'mappings.*.directorate_id' => 'nullable|integer|min:1',
        ]);

        try {
            $result = $this->importer->remapUnmappedRisks($data['mappings']);
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
