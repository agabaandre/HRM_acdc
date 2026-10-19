<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\MigrationExportService;
use App\Services\MigrationImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    public function export(
        Request $request,
        MigrationExportService $exporter,
        AuditLogService $audit,
    ): JsonResponse {
        $export = $exporter->buildEncryptedExport();
        $package = $export['package'];

        $audit->log('Migration package exported', [
            'event_type' => 'migration_exported',
            'user' => $request->user(),
            'http_method' => 'GET',
            'request_uri' => $request->path(),
            'new_values' => [
                'encrypted' => true,
                'cipher' => $package['meta']['cipher'] ?? null,
                'filename' => $export['filename'],
            ],
        ]);

        // encryption_key is returned once in this response — never stored server-side.
        return response()->json([
            'encryption_key' => $export['encryption_key'],
            'filename' => $export['filename'],
            'package' => $package,
            'message' => 'Copy and store the encryption key now. It is not saved on the server and cannot be recovered later.',
        ]);
    }

    public function import(
        Request $request,
        MigrationImportService $importer,
        AuditLogService $audit,
    ): JsonResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/json,text/plain,text/json'],
            'encryption_key' => ['required', 'string', 'min:40', 'max:128'],
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath() ?: '');
        $envelope = json_decode(is_string($raw) ? $raw : '', true);
        if (! is_array($envelope)) {
            return response()->json(['message' => 'Invalid migration JSON.'], 422);
        }

        try {
            $summary = $importer->import($envelope, (string) $request->input('encryption_key'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $audit->log('Migration package imported', [
            'event_type' => 'migration_imported',
            'user' => $request->user(),
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'new_values' => collect($summary)->except('warnings')->all(),
        ]);

        return response()->json([
            'message' => 'Migration imported.',
            'data' => $summary,
        ]);
    }
}
