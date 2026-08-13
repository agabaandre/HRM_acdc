<?php

namespace Modules\Staff\Services;

use App\Support\StaffPhoto;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Staff\Shared\StaffStorage;

class SignatureManagerService
{
    /**
     * @param  array{staff_name?: string, scope?: string, signature_status?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, stats: array{total: int, valid: int, missing: int, broken: int}, total: int, approver_count: int, approver_cache: array<string, mixed>}
     */
    public function page(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $built = $this->buildRows($filters);
        $rows = $built['rows'];
        $stats = $built['stats'];
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return [
            'rows' => array_values(array_map(fn (array $row) => $this->presentRow($row), $slice)),
            'stats' => $stats,
            'total' => $total,
            'approver_count' => (int) ($built['approver_count'] ?? 0),
            'approver_cache' => $built['approver_cache'] ?? ['ok' => false, 'count' => 0],
        ];
    }

    /**
     * @param  array{staff_name?: string, scope?: string, signature_status?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters, int $limit = 5000): array
    {
        $built = $this->buildRows($filters);

        return array_slice(array_map(fn (array $row) => $this->presentRow($row), $built['rows']), 0, $limit);
    }

    /**
     * @return array{ok: bool, count: int, staff_ids: list<int>, updated_at: ?string, message?: string}
     */
    public function refreshApprovers(bool $force = true): array
    {
        $ids = $this->fetchApproverStaffIds($force);
        $meta = [
            'ok' => true,
            'count' => count($ids),
            'staff_ids' => $ids,
            'updated_at' => now()->toIso8601String(),
        ];
        Cache::put($this->approverCacheKey(), $meta, now()->addHours(12));

        return $meta;
    }

    /**
     * @param  list<array{staff_id: int, signature_data_url: string, allow_override?: bool}>  $items
     * @return array{saved: int, skipped: int, failed: int, results: list<array<string, mixed>>}
     */
    public function bulkSave(array $items): array
    {
        $saved = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($items as $item) {
            $staffId = (int) ($item['staff_id'] ?? 0);
            $dataUrl = (string) ($item['signature_data_url'] ?? '');
            $allowOverride = (bool) ($item['allow_override'] ?? false);
            if ($staffId < 1 || $dataUrl === '') {
                $failed++;
                $results[] = ['staff_id' => $staffId, 'ok' => false, 'message' => 'Invalid payload.'];
                continue;
            }

            $staff = DB::table('staff')->where('staff_id', $staffId)->first();
            if (! $staff) {
                $failed++;
                $results[] = ['staff_id' => $staffId, 'ok' => false, 'message' => 'Staff not found.'];
                continue;
            }

            $current = $this->resolveSignature($staffId, $staff->signature ?? null);
            if ($current['valid'] && ! $allowOverride) {
                $skipped++;
                $results[] = [
                    'staff_id' => $staffId,
                    'ok' => false,
                    'skipped' => true,
                    'message' => 'Valid signature exists. Enable replace to overwrite.',
                ];
                continue;
            }

            $filename = $this->saveDataUrl($dataUrl, $this->safeName($staff));
            if ($filename === null) {
                $failed++;
                $results[] = ['staff_id' => $staffId, 'ok' => false, 'message' => 'Could not save signature image.'];
                continue;
            }

            if ($current['valid'] && $current['filename'] !== '' && $current['filename'] !== $filename) {
                $this->deleteSignatureFile($current['filename']);
            }

            DB::table('staff')->where('staff_id', $staffId)->update(['signature' => $filename]);
            $saved++;
            $results[] = [
                'staff_id' => $staffId,
                'ok' => true,
                'filename' => $filename,
                'signature_url' => $this->signatureUrl($filename),
            ];
        }

        return compact('saved', 'skipped', 'failed', 'results');
    }

    /**
     * @return array{filename: string, signature_url: string}
     */
    public function uploadManual(int $staffId, UploadedFile $file, bool $allowOverride = false): array
    {
        $staff = DB::table('staff')->where('staff_id', $staffId)->first();
        if (! $staff) {
            throw ValidationException::withMessages(['staff_id' => ['Staff not found.']]);
        }

        $current = $this->resolveSignature($staffId, $staff->signature ?? null);
        if ($current['valid'] && ! $allowOverride) {
            throw ValidationException::withMessages([
                'signature' => ['Valid signature exists. Enable replace to overwrite.'],
            ]);
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! str_starts_with($mime, 'image/') || $file->getSize() > 2048 * 1024) {
            throw ValidationException::withMessages([
                'signature' => ['Upload a JPG/PNG/GIF/WebP image under 2MB.'],
            ]);
        }

        $dir = StaffStorage::ciPath('staff/signature');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = $this->safeName($staff).'_sig_'.time().'.png';
        $target = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$filename;
        $bin = @file_get_contents($file->getRealPath() ?: '');
        if ($bin === false || $bin === '') {
            throw ValidationException::withMessages(['signature' => ['Could not read uploaded file.']]);
        }
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                imagesavealpha($img, true);
                imagealphablending($img, false);
                imagepng($img, $target, 9);
                imagedestroy($img);
            } else {
                file_put_contents($target, $bin);
            }
        } else {
            file_put_contents($target, $bin);
        }

        if ($current['valid'] && $current['filename'] !== '' && $current['filename'] !== $filename) {
            $this->deleteSignatureFile($current['filename']);
        }

        DB::table('staff')->where('staff_id', $staffId)->update(['signature' => $filename]);

        return [
            'filename' => $filename,
            'signature_url' => (string) $this->signatureUrl($filename),
        ];
    }

    /**
     * @param  array{staff_name?: string, scope?: string, signature_status?: string}  $filters
     * @return array{rows: list<array<string, mixed>>, stats: array{total: int, valid: int, missing: int, broken: int}, approver_count: int, approver_cache: array<string, mixed>}
     */
    protected function buildRows(array $filters): array
    {
        $scope = trim((string) ($filters['scope'] ?? 'approvers'));
        $statusFilter = trim((string) ($filters['signature_status'] ?? 'all'));
        $name = trim((string) ($filters['staff_name'] ?? ''));
        $approverMeta = ['ok' => false, 'count' => 0];
        $approverCount = 0;

        $q = DB::table('staff as s')->select([
            's.staff_id',
            's.SAPNO',
            's.title',
            's.fname',
            's.lname',
            's.oname',
            's.photo',
            's.signature',
        ]);

        if ($scope === 'approvers') {
            $approverMeta = $this->approverCacheMeta();
            $ids = $approverMeta['staff_ids'] ?? [];
            if ($ids === []) {
                $approverMeta = $this->refreshApprovers(true);
                $ids = $approverMeta['staff_ids'] ?? [];
            }
            $approverCount = count($ids);
            if ($ids === []) {
                return [
                    'rows' => [],
                    'stats' => ['total' => 0, 'valid' => 0, 'missing' => 0, 'broken' => 0],
                    'approver_count' => 0,
                    'approver_cache' => $approverMeta,
                ];
            }
            $q->whereIn('s.staff_id', $ids);
        } else {
            $latest = DB::table('staff_contracts')
                ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
                ->groupBy('staff_id');
            $q->joinSub($latest, 'lc', 'lc.staff_id', '=', 's.staff_id')
                ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
                ->whereIn('sc.status_id', [1, 2, 7]);
        }

        if ($name !== '') {
            $q->where(function ($w) use ($name): void {
                $w->where('s.fname', 'like', '%'.$name.'%')
                    ->orWhere('s.lname', 'like', '%'.$name.'%')
                    ->orWhere('s.oname', 'like', '%'.$name.'%');
            });
        }

        $staffRows = $q->orderBy('s.fname')->orderBy('s.lname')->get();
        $rows = [];
        $stats = ['total' => 0, 'valid' => 0, 'missing' => 0, 'broken' => 0];

        foreach ($staffRows as $r) {
            $resolved = $this->resolveSignature((int) $r->staff_id, $r->signature ?? null);
            $dbVal = trim((string) ($r->signature ?? ''));
            if ($resolved['valid']) {
                $status = 'valid';
                $label = 'Valid';
                $filename = $resolved['filename'];
            } elseif ($dbVal !== '') {
                $status = 'broken';
                $label = 'File missing';
                $filename = $dbVal;
            } else {
                $status = 'missing';
                $label = 'Missing';
                $filename = '';
            }

            if ($statusFilter !== '' && $statusFilter !== 'all' && $status !== $statusFilter) {
                continue;
            }

            $fullName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
                trim((string) ($r->title ?? '')),
                trim((string) ($r->fname ?? '')),
                trim((string) ($r->lname ?? '')),
                trim((string) ($r->oname ?? '')),
            ]))) ?? '');

            $rows[] = [
                'staff_id' => (int) $r->staff_id,
                'SAPNO' => $r->SAPNO,
                'title' => $r->title,
                'fname' => $r->fname,
                'lname' => $r->lname,
                'oname' => $r->oname,
                'photo' => $r->photo,
                'signature' => $filename,
                'signature_status' => $status,
                'signature_status_label' => $label,
                'full_name' => $fullName,
                'signature_text' => $fullName !== '' ? $fullName : ('Staff #'.$r->staff_id),
            ];

            $stats['total']++;
            $stats[$status]++;
        }

        return [
            'rows' => $rows,
            'stats' => $stats,
            'approver_count' => $approverCount,
            'approver_cache' => $approverMeta,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function presentRow(array $row): array
    {
        $photo = trim((string) ($row['photo'] ?? ''));
        $sig = trim((string) ($row['signature'] ?? ''));

        return [
            ...$row,
            'photo_url' => StaffPhoto::url($photo !== '' ? $photo : null),
            'signature_url' => $row['signature_status'] === 'valid' ? $this->signatureUrl($sig) : null,
            'can_replace_without_override' => ($row['signature_status'] ?? '') !== 'valid',
        ];
    }

    /**
     * @return array{valid: bool, filename: string, path: string}
     */
    protected function resolveSignature(int $staffId, mixed $signatureValue): array
    {
        $basename = basename(str_replace('\\', '/', trim((string) $signatureValue)));
        if ($basename !== '') {
            $path = StaffStorage::ciPath('staff/signature/'.$basename);
            if (is_file($path) && @getimagesize($path) !== false) {
                return ['valid' => true, 'filename' => $basename, 'path' => $path];
            }
        }

        if ($staffId > 0) {
            $userSig = DB::table('user')
                ->where('auth_staff_id', $staffId)
                ->whereNotNull('signature')
                ->where('signature', '!=', '')
                ->value('signature');
            $userBase = basename(str_replace('\\', '/', trim((string) $userSig)));
            if ($userBase !== '') {
                $path = StaffStorage::ciPath('staff/signature/'.$userBase);
                if (is_file($path) && @getimagesize($path) !== false) {
                    return ['valid' => true, 'filename' => $userBase, 'path' => $path];
                }
            }
        }

        return ['valid' => false, 'filename' => $basename, 'path' => ''];
    }

    protected function signatureUrl(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }
        $safe = basename(str_replace('\\', '/', $filename));
        $path = StaffStorage::ciPath('staff/signature/'.$safe);
        if (! is_file($path)) {
            return null;
        }

        return route('staff.media.signature', ['filename' => $safe]);
    }

    protected function saveDataUrl(string $dataUrl, string $safeName): ?string
    {
        $dataUrl = trim($dataUrl);
        $bin = null;
        if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $dataUrl)) {
            $comma = strpos($dataUrl, ',');
            if ($comma === false) {
                return null;
            }
            $bin = base64_decode(substr($dataUrl, $comma + 1), true);
        }
        if ($bin === false || $bin === null || strlen($bin) < 16 || strlen($bin) > 1024 * 1024) {
            return null;
        }

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                imagesavealpha($img, true);
                imagealphablending($img, false);
                ob_start();
                imagepng($img, null, 9);
                $png = ob_get_clean();
                imagedestroy($img);
                if (is_string($png) && $png !== '') {
                    $bin = $png;
                }
            }
        }

        $dir = StaffStorage::ciPath('staff/signature');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = ($safeName !== '' ? $safeName : 'staff').'_sig_'.time().'.png';
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$filename;
        if (@file_put_contents($path, $bin) === false) {
            return null;
        }

        return $filename;
    }

    protected function deleteSignatureFile(string $filename): void
    {
        $path = StaffStorage::ciPath('staff/signature/'.basename($filename));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected function safeName(object $staff): string
    {
        $raw = trim(($staff->fname ?? '').'_'.($staff->lname ?? ''));
        $safe = preg_replace('/[^a-zA-Z0-9_\-.]/', '', str_replace(' ', '_', $raw)) ?: 'staff';

        return substr((string) $safe, 0, 40);
    }

    protected function approverCacheKey(): string
    {
        return 'staff-portal.signature-manager.approver-ids';
    }

    /**
     * @return array<string, mixed>
     */
    protected function approverCacheMeta(): array
    {
        $cached = Cache::get($this->approverCacheKey());
        if (! is_array($cached)) {
            return ['ok' => false, 'count' => 0, 'staff_ids' => []];
        }

        return $cached;
    }

    /**
     * @return list<int>
     */
    protected function fetchApproverStaffIds(bool $force = false): array
    {
        if (! $force) {
            $cached = $this->approverCacheMeta();
            if (! empty($cached['staff_ids']) && is_array($cached['staff_ids'])) {
                return array_values(array_map('intval', $cached['staff_ids']));
            }
        }

        $fromApi = $this->fetchApproverStaffIdsFromApi();
        if ($fromApi !== []) {
            return $fromApi;
        }

        return $this->fetchApproverStaffIdsFromLegacyCache();
    }

    /**
     * @return list<int>
     */
    protected function fetchApproverStaffIdsFromApi(): array
    {
        $base = rtrim((string) config('staff-portal.apm_base_url', ''), '/');
        if ($base === '') {
            return [];
        }

        try {
            /** @var PendingRequest $http */
            $http = Http::acceptJson()->timeout(8)->connectTimeout(3);
            $cookie = (string) request()->header('Cookie', '');
            if ($cookie !== '') {
                $http = $http->withHeaders(['Cookie' => $cookie]);
            }
            $response = $http->get($base.'/api/approver-dashboard/approver-staff-ids');
            if (! $response->successful()) {
                return [];
            }
            $json = $response->json();
            $ids = $json['staff_ids'] ?? $json['data'] ?? null;
            if (! is_array($ids)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Read CI3 Signature Manager cache written by jobs/cron.
     *
     * @return list<int>
     */
    protected function fetchApproverStaffIdsFromLegacyCache(): array
    {
        $path = dirname(base_path(), 2).'/application/cache/apm_approver_staff_ids.json';
        if (! is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $json = json_decode($raw, true);
        $ids = $json['staff_ids'] ?? null;
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
