<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AzureBlobStorageClient;
use App\Services\AzureSapDumpSyncService;
use App\Services\SapImportService;
use App\Support\AzureSapStorageCredentials;
use App\Support\RrSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SapImportController extends Controller
{
    public function __construct(
        private readonly SapImportService $imports,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
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

    public function dumpList(): JsonResponse
    {
        try {
            $resolved = AzureSapStorageCredentials::resolve(new RrSettingsBag);
            if ($resolved['account_name'] === '' || $resolved['account_key'] === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Azure SAP storage is not configured. Set it under Settings → Azure SAP storage.',
                    'data' => ['files' => [], 'configured' => false],
                ], 422);
            }
            $client = new AzureBlobStorageClient($resolved);
            $prefix = $resolved['prefix'] !== '' ? $resolved['prefix'] : null;
            $files = $client->listBlobs($prefix);
            // Prefer real files over zero-byte folder markers.
            $files = array_values(array_filter(
                $files,
                static fn (array $f) => ! ($f['is_directory'] ?? false),
            ));

            return response()->json([
                'success' => true,
                'data' => [
                    'configured' => true,
                    'account_name' => $resolved['account_name'],
                    'container' => $resolved['container'],
                    'prefix' => $resolved['prefix'],
                    'files' => $files,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Azure SAP dump list failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['files' => [], 'configured' => true],
            ], 502);
        }
    }

    public function dumpDownload(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:1024'],
        ]);
        $name = ltrim(str_replace('\\', '/', (string) $validated['name']), '/');

        try {
            $resolved = AzureSapStorageCredentials::resolve(new RrSettingsBag);
            $client = new AzureBlobStorageClient($resolved);
            $file = $client->download($name);
            $basename = basename($name) ?: 'sap-dump.bin';
            $contentType = $file['content_type'] ?: 'application/octet-stream';

            return response()->streamDownload(function () use ($file) {
                echo $file['body'];
            }, $basename, [
                'Content-Type' => $contentType,
                'Content-Length' => (string) $file['content_length'],
            ]);
        } catch (Throwable $e) {
            Log::warning('Azure SAP dump download failed', ['error' => $e->getMessage(), 'name' => $name]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function dumpPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:1024'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:200'],
        ]);
        $name = ltrim(str_replace('\\', '/', (string) $validated['name']), '/');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Preview is available for .csv dumps only.',
            ], 422);
        }
        $limit = (int) ($validated['limit'] ?? 50);

        try {
            $resolved = AzureSapStorageCredentials::resolve(new RrSettingsBag);
            $client = new AzureBlobStorageClient($resolved);
            $file = $client->download($name);
            $preview = $this->parseCsvPreview((string) $file['body'], $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $name,
                    'size' => (int) $file['content_length'],
                    'headers' => $preview['headers'],
                    'rows' => $preview['rows'],
                    'row_count' => $preview['row_count'],
                    'truncated' => $preview['truncated'],
                    'limit' => $limit,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Azure SAP dump preview failed', ['error' => $e->getMessage(), 'name' => $name]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, row_count: int, truncated: bool}
     */
    private function parseCsvPreview(string $body, int $limit): array
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
        $fh = fopen('php://temp', 'r+b');
        if ($fh === false) {
            throw new \RuntimeException('Could not open temp stream for CSV preview.');
        }
        fwrite($fh, $body);
        rewind($fh);

        $headers = [];
        $rows = [];
        $rowCount = 0;
        $line = 0;
        while (($cells = fgetcsv($fh)) !== false) {
            if ($cells === [null]) {
                continue;
            }
            $line++;
            $normalized = array_map(static fn ($c) => (string) $c, $cells);
            if ($line === 1) {
                $headers = $normalized;

                continue;
            }
            $rowCount++;
            if (count($rows) < $limit) {
                // Pad/trim to header width for a stable table.
                if ($headers !== []) {
                    $width = count($headers);
                    if (count($normalized) < $width) {
                        $normalized = array_pad($normalized, $width, '');
                    } elseif (count($normalized) > $width) {
                        $normalized = array_slice($normalized, 0, $width);
                    }
                }
                $rows[] = $normalized;
            }
        }
        fclose($fh);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => $rowCount,
            'truncated' => $rowCount > $limit,
        ];
    }

    public function dumpImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:1024'],
            'period_ym' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $name = ltrim(str_replace('\\', '/', (string) $validated['name']), '/');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only .xlsx or .csv SAP dumps can be imported.',
            ], 422);
        }

        try {
            $resolved = AzureSapStorageCredentials::resolve(new RrSettingsBag);
            $client = new AzureBlobStorageClient($resolved);
            $file = $client->download($name);
            $tmp = tempnam(sys_get_temp_dir(), 'sap_azure_');
            if ($tmp === false) {
                throw new \RuntimeException('Could not create temp file.');
            }
            $path = $tmp.'.'.$ext;
            rename($tmp, $path);
            file_put_contents($path, $file['body']);

            $userId = null;
            if ($request->attributes->has('risk_staff_id')) {
                $userId = (int) $request->attributes->get('risk_staff_id');
            }

            $result = $this->imports->import(
                $path,
                basename($name),
                $userId,
                $validated['period_ym'] ?? null,
            );
            @unlink($path);

            $deleted = false;
            if ((bool) config('azure_sap.delete_after_fetch', true)) {
                $client->delete($name);
                $deleted = true;
            }
            $result['remote_deleted'] = $deleted;

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::warning('Azure SAP dump import failed', ['error' => $e->getMessage(), 'name' => $name]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function dumpFetch(Request $request, AzureSapDumpSyncService $sync): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:1024'],
            'keep' => ['nullable', 'boolean'],
        ]);
        $delete = ! (bool) ($validated['keep'] ?? false);
        if ($delete) {
            $delete = (bool) config('azure_sap.delete_after_fetch', true);
        }
        $only = isset($validated['name']) ? ltrim(str_replace('\\', '/', (string) $validated['name']), '/') : null;
        $only = $only !== '' ? $only : null;

        $result = $sync->sync($delete, $only);

        return response()->json([
            'success' => $result['errors'] === [],
            'message' => $result['errors'] === []
                ? sprintf(
                    'Fetched %d file(s); deleted %d remote blob(s).',
                    count($result['imported']),
                    count($result['deleted']),
                )
                : 'Fetch completed with errors.',
            'data' => $result,
        ], $result['errors'] === [] ? 200 : 502);
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
