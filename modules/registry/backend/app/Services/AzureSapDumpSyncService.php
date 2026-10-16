<?php

namespace App\Services;

use App\Support\AzureSapStorageCredentials;
use App\Support\RrSettingsBag;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fetch SAP dumps from Azure Blob, import into Finance, then delete remote blobs on success.
 */
final class AzureSapDumpSyncService
{
    public const SETTING_LAST_SYNC_AT = 'azure_sap_last_sync_at';

    public const SETTING_LAST_SYNC_JSON = 'azure_sap_last_sync_json';

    private const IMPORTABLE = ['csv', 'txt', 'xlsx'];

    public function __construct(
        private readonly SapImportService $imports = new SapImportService,
        private readonly ?AzureBlobStorageClient $client = null,
    ) {}

    /**
     * @return array{
     *   configured: bool,
     *   checked_at: string,
     *   files_seen: int,
     *   imported: list<array{name: string, import_id: int, period_ym: string, rows: int, deleted: bool}>,
     *   deleted: list<string>,
     *   skipped: list<array{name: string, reason: string}>,
     *   errors: list<array{name: string, message: string}>
     * }
     */
    public function sync(bool $deleteAfterSuccess = true, ?string $onlyName = null): array
    {
        $checkedAt = now()->toIso8601String();
        $result = [
            'configured' => false,
            'checked_at' => $checkedAt,
            'files_seen' => 0,
            'imported' => [],
            'deleted' => [],
            'skipped' => [],
            'errors' => [],
        ];

        try {
            $resolved = AzureSapStorageCredentials::resolve(new RrSettingsBag);
            if ($resolved['account_name'] === '' || $resolved['account_key'] === '') {
                $result['errors'][] = [
                    'name' => '',
                    'message' => 'Azure SAP storage is not configured.',
                ];
                $this->persistLastSync($result);

                return $result;
            }
            $result['configured'] = true;
            $client = $this->client ?? new AzureBlobStorageClient($resolved);
            $prefix = $resolved['prefix'] !== '' ? $resolved['prefix'] : null;
            $files = $client->listBlobs($prefix);
            $files = array_values(array_filter(
                $files,
                static fn (array $f) => ! ($f['is_directory'] ?? false),
            ));
            // Oldest first so newer dumps win when replacing the same period.
            usort($files, static fn ($a, $b) => strcmp($a['last_modified'] ?? '', $b['last_modified'] ?? ''));

            if ($onlyName !== null && $onlyName !== '') {
                $onlyName = ltrim(str_replace('\\', '/', $onlyName), '/');
                $files = array_values(array_filter(
                    $files,
                    static fn (array $f) => ($f['name'] ?? '') === $onlyName,
                ));
                if ($files === []) {
                    $result['errors'][] = [
                        'name' => $onlyName,
                        'message' => 'Blob not found in container.',
                    ];
                    $this->persistLastSync($result);

                    return $result;
                }
            }

            $result['files_seen'] = count($files);

            foreach ($files as $file) {
                $name = (string) ($file['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($ext, self::IMPORTABLE, true)) {
                    $result['skipped'][] = ['name' => $name, 'reason' => 'unsupported extension'];

                    continue;
                }
                if ((int) ($file['size'] ?? 0) <= 0) {
                    $result['skipped'][] = ['name' => $name, 'reason' => 'empty file'];

                    continue;
                }

                try {
                    $imported = $this->importBlob($client, $name, $ext, (string) ($file['last_modified'] ?? ''));
                    $deleted = false;
                    if ($deleteAfterSuccess) {
                        $client->delete($name);
                        $deleted = true;
                        $result['deleted'][] = $name;
                    }
                    $result['imported'][] = [
                        'name' => $name,
                        'import_id' => $imported['import_id'],
                        'period_ym' => $imported['period_ym'],
                        'rows' => $imported['rows'],
                        'deleted' => $deleted,
                    ];
                } catch (Throwable $e) {
                    Log::warning('Azure SAP dump sync failed for blob', [
                        'name' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    $result['errors'][] = [
                        'name' => $name,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::warning('Azure SAP dump sync aborted', ['error' => $e->getMessage()]);
            $result['errors'][] = [
                'name' => '',
                'message' => $e->getMessage(),
            ];
        }

        $this->persistLastSync($result);

        return $result;
    }

    /**
     * @return array{import_id: int, period_ym: string, rows: int}
     */
    private function importBlob(
        AzureBlobStorageClient $client,
        string $name,
        string $ext,
        string $lastModified,
    ): array {
        $downloaded = $client->download($name);
        if ($downloaded['body'] === '') {
            throw new RuntimeException('Downloaded blob is empty.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sap_azure_sync_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file.');
        }
        $path = $tmp.'.'.$ext;
        rename($tmp, $path);
        try {
            file_put_contents($path, $downloaded['body']);
            $periodYm = $this->periodFromLastModified($lastModified);
            $imported = $this->imports->import($path, basename($name), null, $periodYm);

            return [
                'import_id' => (int) $imported['import_id'],
                'period_ym' => (string) $imported['period_ym'],
                'rows' => (int) $imported['rows'],
            ];
        } finally {
            @unlink($path);
        }
    }

    private function periodFromLastModified(string $lastModified): string
    {
        if ($lastModified !== '') {
            $ts = strtotime($lastModified);
            if ($ts !== false) {
                return date('Y-m', $ts);
            }
        }

        return date('Y-m');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function persistLastSync(array $result): void
    {
        try {
            $bag = new RrSettingsBag;
            $bag->set(self::SETTING_LAST_SYNC_AT, (string) ($result['checked_at'] ?? now()->toIso8601String()));
            $bag->set(self::SETTING_LAST_SYNC_JSON, json_encode([
                'configured' => (bool) ($result['configured'] ?? false),
                'files_seen' => (int) ($result['files_seen'] ?? 0),
                'imported_count' => count($result['imported'] ?? []),
                'deleted_count' => count($result['deleted'] ?? []),
                'skipped_count' => count($result['skipped'] ?? []),
                'error_count' => count($result['errors'] ?? []),
                'imported' => array_map(
                    static fn (array $r) => $r['name'] ?? '',
                    $result['imported'] ?? [],
                ),
                'errors' => $result['errors'] ?? [],
            ], JSON_UNESCAPED_SLASHES) ?: '{}');
        } catch (Throwable $e) {
            Log::debug('Could not persist Azure SAP sync status', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{last_sync_at: string|null, summary: array<string, mixed>|null}
     */
    public function lastSyncStatus(): array
    {
        $bag = new RrSettingsBag;
        $at = $bag->get(self::SETTING_LAST_SYNC_AT);
        $raw = $bag->get(self::SETTING_LAST_SYNC_JSON);
        $summary = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }

        return [
            'last_sync_at' => is_string($at) && $at !== '' ? $at : null,
            'summary' => $summary,
            'poll_hours' => (int) config('azure_sap.poll_hours', 4),
            'delete_after_fetch' => (bool) config('azure_sap.delete_after_fetch', true),
        ];
    }
}
