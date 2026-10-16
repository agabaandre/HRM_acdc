<?php

namespace App\Http\Controllers\Api\V1\Registry;

use App\Http\Controllers\Controller;
use App\Support\CsvDownload;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RegistryController extends Controller
{
    public function years(): JsonResponse
    {
        $years = DB::table('registry_documents')
            ->where('is_current', 0)
            ->distinct()
            ->orderByDesc('archive_year')
            ->pluck('archive_year')
            ->map(fn ($year) => (int) $year)
            ->values();

        return response()->json(['data' => $years]);
    }

    public function dashboard(): JsonResponse
    {
        $year = (int) now()->year;
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $base = DB::table('registry_documents')->where('is_current', 1)->where('archive_year', $year);

        $byStatus = (clone $base)
            ->leftJoin('status as s', 's.status_id', '=', 'registry_documents.status_id')
            ->selectRaw('COALESCE(s.status, "Unknown") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $byType = (clone $base)
            ->leftJoin('document_types as t', 't.document_type_id', '=', 'registry_documents.document_type_id')
            ->selectRaw('COALESCE(t.document_type, "Unknown") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $byDivision = (clone $base)
            ->leftJoin('divisions as d', 'd.division_id', '=', 'registry_documents.division_id')
            ->selectRaw('COALESCE(d.abbrev, d.division, "Unknown") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $recent = $this->documentQuery()
            ->where('registry_documents.is_current', 1)
            ->where('registry_documents.archive_year', $year)
            ->orderByDesc('registry_documents.date_recorded')
            ->orderByDesc('registry_documents.id')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => [
                'year' => $year,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'total' => (clone $base)->count(),
                'this_week' => (clone $base)->whereBetween('date_recorded', [$weekStart, $weekEnd])->count(),
                'by_status' => $byStatus,
                'by_type' => $byType,
                'by_division' => $byDivision,
                'recent' => $recent,
            ],
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $scope = (string) $request->query('scope', 'year');
        $year = (int) ($request->query('year') ?: now()->year);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = $this->pageSize($request);
        $query = $this->filteredDocuments($request);
        $total = (clone $query)->count();

        if ($perPage === 0) {
            $page = 1;
            $rows = $this->applySort($query, $request)->limit(20000)->get();
            $shown = $total;
        } else {
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $rows = $this->applySort($query, $request)
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();
            $shown = $perPage;
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $shown,
                'year' => $scope === 'archive' ? $year : (int) now()->year,
                'week_start' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'week_end' => now()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $format = (string) $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'excel', 'pdf'], true)) {
            $format = 'csv';
        }

        $rows = $this->applySort($this->filteredDocuments($request), $request)->limit(20000)->get();
        $headings = ['No.', 'Reference', 'Document date', 'Recorded', 'Type', 'Division', 'Addressed to', 'Description', 'Signed by', 'Status', 'Recorded by'];
        $table = [];
        foreach ($rows as $index => $row) {
            $table[] = [
                (string) ($index + 1),
                (string) $row->ref_no,
                substr((string) ($row->document_date ?? ''), 0, 10),
                substr((string) ($row->date_recorded ?? ''), 0, 10),
                (string) ($row->document_type ?? ''),
                (string) ($row->division_abbrev ?: $row->division),
                (string) ($row->addressed_to ?? ''),
                (string) ($row->description ?? ''),
                (string) ($row->signed_by ?? ''),
                (string) ($row->status ?? ''),
                (string) ($row->recorded_by ?? ''),
            ];
        }

        $scope = (string) $request->query('scope', 'year');
        $filename = 'registry-'.$scope.'-'.now()->format('Y-m-d');

        return $this->downloadTable($format, $filename, $headings, $table, 'Registry documents');
    }

    public function exportTable(Request $request): Response
    {
        $data = $request->validate([
            'format' => ['required', 'in:csv,excel,pdf'],
            'title' => ['required', 'string', 'max:80'],
            'headings' => ['required', 'array', 'min:1', 'max:16'],
            'headings.*' => ['string', 'max:80'],
            'rows' => ['required', 'array', 'max:500'],
            'rows.*' => ['array', 'max:16'],
        ]);

        $headings = array_map(fn ($heading) => (string) $heading, $data['headings']);
        $table = [];
        foreach ($data['rows'] as $row) {
            $cells = [];
            foreach (array_values($row) as $cell) {
                $cells[] = mb_substr((string) $cell, 0, 500);
            }
            $table[] = $cells;
        }

        $filename = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $data['title']), '-');
        if ($filename === '') {
            $filename = 'registry-table';
        }

        return $this->downloadTable($data['format'], $filename, $headings, $table, $data['title']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'division_id' => ['required', 'integer'],
            'document_type_id' => ['required', 'integer'],
            'status_id' => ['required', 'integer'],
            'addressed_to' => ['required', 'string', 'max:100'],
            'document_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'signed_by' => ['required', 'string', 'max:100'],
            'comments' => ['nullable', 'string'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ]);

        $division = DB::table('divisions')->where('division_id', $data['division_id'])->first();
        $type = DB::table('document_types')->where('document_type_id', $data['document_type_id'])->first();
        $status = DB::table('status')->where('status_id', $data['status_id'])->first();
        if ($division === null || $type === null || $status === null) {
            return response()->json(['message' => 'Choose a division, document type, and status.'], 422);
        }

        $today = now()->toDateString();
        $year = (int) now()->year;
        $abbrev = $this->refCode((string) $division->abbrev, 'NA');
        $abv = $this->refCode((string) $type->ABV, 'DOC');
        $recordedBy = $this->recordedByName($request);

        $id = DB::transaction(function () use ($data, $today, $year, $abbrev, $abv, $recordedBy) {
            $ref = $this->allocateReference($abbrev, $abv, $year);
            $now = now()->format('Y-m-d H:i:s');
            $legacyId = DB::table('documents')->insertGetId([
                'ref_no' => $ref,
                'division_id' => $data['division_id'],
                'document_type_id' => $data['document_type_id'],
                'addresed_to' => $data['addressed_to'],
                'document_date' => $data['document_date'],
                'date_recorded' => $today,
                'description' => $data['description'],
                'signed_by' => $data['signed_by'],
                'status_id' => $data['status_id'],
                'user_id' => 0,
                'comments' => $data['comments'] ?? '',
                'file_name' => $data['file_name'] ?? '',
                'date_time' => $now,
            ]);

            return DB::table('registry_documents')->insertGetId([
                'archive_year' => $year,
                'is_current' => 1,
                'legacy_id' => $legacyId,
                'source_table' => 'documents',
                'ref_no' => $ref,
                'division_id' => $data['division_id'],
                'document_type_id' => $data['document_type_id'],
                'addressed_to' => $data['addressed_to'],
                'document_date' => $data['document_date'],
                'date_recorded' => $today,
                'description' => $data['description'],
                'signed_by' => $data['signed_by'],
                'status_id' => $data['status_id'],
                'recorded_by' => $recordedBy,
                'comments' => $data['comments'] ?? '',
                'file_name' => $data['file_name'] ?? '',
                'recorded_at' => $now,
            ]);
        });

        $row = $this->documentQuery()->where('registry_documents.id', $id)->first();

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'division_id' => ['required', 'integer'],
            'document_type_id' => ['required', 'integer'],
            'status_id' => ['required', 'integer'],
            'addressed_to' => ['required', 'string', 'max:100'],
            'document_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'signed_by' => ['required', 'string', 'max:100'],
            'comments' => ['nullable', 'string'],
        ]);

        $existing = DB::table('registry_documents')->where('id', $id)->first();
        if ($existing === null) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $division = DB::table('divisions')->where('division_id', $data['division_id'])->first();
        $type = DB::table('document_types')->where('document_type_id', $data['document_type_id'])->first();
        $status = DB::table('status')->where('status_id', $data['status_id'])->first();
        if ($division === null || $type === null || $status === null) {
            return response()->json(['message' => 'Choose a division, document type, and status.'], 422);
        }

        $ref = $this->referenceKeepingNumber(
            (string) $existing->ref_no,
            $this->refCode((string) $division->abbrev, 'NA'),
            $this->refCode((string) $type->ABV, 'DOC'),
        );
        $taken = DB::table('registry_documents')->where('ref_no', $ref)->where('id', '!=', $id)->exists();
        if ($taken) {
            return response()->json(['message' => 'That reference is already used by another document.'], 422);
        }

        $payload = [
            'ref_no' => $ref,
            'division_id' => $data['division_id'],
            'document_type_id' => $data['document_type_id'],
            'addressed_to' => $data['addressed_to'],
            'document_date' => $data['document_date'],
            'description' => $data['description'],
            'signed_by' => $data['signed_by'],
            'status_id' => $data['status_id'],
            'comments' => $data['comments'] ?? '',
        ];

        DB::transaction(function () use ($existing, $payload) {
            DB::table('registry_documents')->where('id', $existing->id)->update($payload);
            $source = (string) $existing->source_table;
            $legacyId = (int) $existing->legacy_id;
            if ($legacyId > 0 && in_array($source, ['documents', 'archives', 'archives22', 'archives23', 'archives24', 'archives25'], true)) {
                DB::table($source)->where('document_id', $legacyId)->update([
                    'ref_no' => $payload['ref_no'],
                    'division_id' => $payload['division_id'],
                    'document_type_id' => $payload['document_type_id'],
                    'addresed_to' => $payload['addressed_to'],
                    'document_date' => $payload['document_date'],
                    'description' => $payload['description'],
                    'signed_by' => $payload['signed_by'],
                    'status_id' => $payload['status_id'],
                    'comments' => $payload['comments'],
                ]);
            }
        });

        $row = $this->documentQuery()->where('registry_documents.id', $id)->first();

        return response()->json(['data' => $row]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => [
                'document_types' => DB::table('document_types')->orderBy('document_type')->get(),
                'statuses' => DB::table('status')->orderBy('status')->get(),
                'divisions' => DB::table('divisions')->orderBy('division')->get(),
            ],
        ]);
    }

    public function storeLookup(Request $request, string $table): JsonResponse
    {
        $payload = $this->normalizeLookup($table, $this->validatedLookup($request, $table));
        $id = DB::table($this->lookupTable($table))->insertGetId($payload);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function updateLookup(Request $request, string $table, int $id): JsonResponse
    {
        $payload = $this->normalizeLookup($table, $this->validatedLookup($request, $table));
        $updated = DB::table($this->lookupTable($table))->where($this->lookupKey($table), $id)->update($payload);
        if ($updated === 0 && ! DB::table($this->lookupTable($table))->where($this->lookupKey($table), $id)->exists()) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function destroyLookup(string $table, int $id): JsonResponse
    {
        $column = match ($table) {
            'document-types' => 'document_type_id',
            'statuses' => 'status_id',
            'divisions' => 'division_id',
            default => abort(404),
        };
        $used = DB::table('registry_documents')->where($column, $id)->exists();
        if ($used) {
            return response()->json(['message' => 'This value is used by recorded documents.'], 422);
        }
        DB::table($this->lookupTable($table))->where($this->lookupKey($table), $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function allocateReference(string $abbrev, string $abv, int $year): string
    {
        $yy = substr((string) $year, -2);
        $locked = DB::table('last_ref')->lockForUpdate()->orderByDesc('last_ref')->first();
        $counter = (int) ($locked->last_ref ?? 0);
        $usedOnDocuments = (int) DB::table('documents')
            ->where('ref_no', 'like', '%.'.$yy)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(ref_no, '/', -1), '.', 1) AS UNSIGNED)), 0) as n")
            ->value('n');
        $next = max($counter, $usedOnDocuments) + 1;

        $ref = '';
        for ($guard = 0; $guard < 50; $guard++) {
            $ref = sprintf('AU/CDC/%s/%s/%d.%s', $abbrev, $abv, $next, $yy);
            $taken = DB::table('documents')->where('ref_no', $ref)->exists()
                || DB::table('registry_documents')->where('ref_no', $ref)->exists();
            if (! $taken) {
                break;
            }
            $next++;
        }

        if ($locked === null) {
            DB::table('last_ref')->insert(['last_ref' => $next]);
        } else {
            DB::table('last_ref')->where('last_ref', $locked->last_ref)->update(['last_ref' => $next]);
        }

        return $ref;
    }

    private function referenceKeepingNumber(string $current, string $abbrev, string $abv): string
    {
        if (preg_match('#/(\d+)\.(\d{2})$#', $current, $match) === 1) {
            return sprintf('AU/CDC/%s/%s/%d.%s', $abbrev, $abv, (int) $match[1], $match[2]);
        }

        return $current;
    }

    private function refCode(string $value, string $fallback): string
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return $code !== '' ? substr($code, 0, 10) : $fallback;
    }

    private function recordedByName(Request $request): string
    {
        $session = $request->session()->get('risk_register');
        $name = is_array($session) ? trim((string) ($session['name'] ?? '')) : '';
        if ($name !== '') {
            return $name;
        }

        $staffId = (int) $request->attributes->get('risk_staff_id');
        if ($staffId > 0) {
            $row = DB::connection('staff')->table('staff')->where('staff_id', $staffId)->first(['fname', 'lname']);
            $name = trim(trim((string) ($row->fname ?? '')).' '.trim((string) ($row->lname ?? '')));
        }

        return $name !== '' ? $name : 'Registry';
    }

    private function pageSize(Request $request): int
    {
        $raw = strtolower((string) $request->query('per_page', '50'));
        if ($raw === 'all') {
            return 0;
        }
        $size = (int) $raw;

        return in_array($size, [10, 20, 30, 50, 100], true) ? $size : 50;
    }

    private function filteredDocuments(Request $request): Builder
    {
        $scope = (string) $request->query('scope', 'year');
        $year = (int) ($request->query('year') ?: now()->year);
        $query = $this->documentQuery();

        if ($scope === 'week') {
            $query->where('registry_documents.is_current', 1)
                ->where('registry_documents.archive_year', (int) now()->year)
                ->whereBetween('registry_documents.date_recorded', [
                    now()->startOfWeek(Carbon::MONDAY)->toDateString(),
                    now()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                ]);
        } elseif ($scope === 'archive') {
            $query->where('registry_documents.is_current', 0)
                ->where('registry_documents.archive_year', $year);
        } else {
            $query->where('registry_documents.is_current', 1)
                ->where('registry_documents.archive_year', (int) now()->year);
        }

        foreach ([
            'division_id' => 'registry_documents.division_id',
            'document_type_id' => 'registry_documents.document_type_id',
            'status_id' => 'registry_documents.status_id',
        ] as $param => $column) {
            $id = (int) $request->query($param, 0);
            if ($id > 0) {
                $query->where($column, $id);
            }
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('registry_documents.ref_no', 'like', $like)
                    ->orWhere('registry_documents.description', 'like', $like)
                    ->orWhere('registry_documents.addressed_to', 'like', $like)
                    ->orWhere('registry_documents.signed_by', 'like', $like)
                    ->orWhere('registry_documents.recorded_by', 'like', $like);
            });
        }

        return $query;
    }

    private function applySort(Builder $query, Request $request): Builder
    {
        $columns = [
            'ref_no' => 'registry_documents.ref_no',
            'document_date' => 'registry_documents.document_date',
            'date_recorded' => 'registry_documents.date_recorded',
            'document_type' => 't.document_type',
            'division' => 'd.abbrev',
            'addressed_to' => 'registry_documents.addressed_to',
            'description' => 'registry_documents.description',
            'signed_by' => 'registry_documents.signed_by',
            'status' => 's.status',
            'recorded_by' => 'registry_documents.recorded_by',
        ];
        $sort = (string) $request->query('sort', 'date_recorded');
        $column = $columns[$sort] ?? $columns['date_recorded'];
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $dir)->orderBy('registry_documents.id', 'desc');
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    private function downloadTable(string $format, string $filename, array $headings, array $rows, string $title): Response
    {
        if ($format === 'csv') {
            return CsvDownload::make($filename.'.csv', $headings, $rows);
        }

        if ($format === 'excel') {
            $html = "\xEF\xBB\xBF".$this->tableHtml($headings, $rows);

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
            ]);
        }

        @mkdir(storage_path('app/mpdf_tmp'), 0775, true);
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'tempDir' => storage_path('app/mpdf_tmp'),
            'default_font_size' => 8,
            'margin_top' => 16,
            'margin_bottom' => 12,
        ]);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $mpdf->SetHTMLHeader('<div style="font-size:11px;color:#0d7a3a;border-bottom:1px solid #0d7a3a;padding-bottom:4px;">Africa CDC Registry · '.$safeTitle.'</div>');
        $mpdf->WriteHTML('<h2 style="font-size:13px;margin:0 0 8px;">'.$safeTitle.'</h2>');
        foreach (array_chunk($rows, 400) as $chunk) {
            $mpdf->WriteHTML($this->tableHtml($headings, $chunk));
        }

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    private function tableHtml(array $headings, array $rows): string
    {
        $html = '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:8px;width:100%;"><thead><tr>';
        foreach ($headings as $heading) {
            $html .= '<th style="background:#f1f5f9;">'.htmlspecialchars($heading, ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8').'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function documentQuery()
    {
        return DB::table('registry_documents')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'registry_documents.division_id')
            ->leftJoin('document_types as t', 't.document_type_id', '=', 'registry_documents.document_type_id')
            ->leftJoin('status as s', 's.status_id', '=', 'registry_documents.status_id')
            ->select([
                'registry_documents.id',
                'registry_documents.division_id',
                'registry_documents.document_type_id',
                'registry_documents.status_id',
                'registry_documents.ref_no',
                'registry_documents.addressed_to',
                'registry_documents.document_date',
                'registry_documents.date_recorded',
                'registry_documents.description',
                'registry_documents.signed_by',
                'registry_documents.recorded_by',
                'registry_documents.comments',
                'registry_documents.file_name',
                'registry_documents.archive_year',
                'd.division',
                'd.abbrev as division_abbrev',
                't.document_type',
                's.status',
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function validatedLookup(Request $request, string $table): array
    {
        return match ($table) {
            'document-types' => $request->validate([
                'document_type' => ['required', 'string', 'max:100'],
                'ABV' => ['nullable', 'string', 'max:5'],
            ]),
            'statuses' => $request->validate([
                'status' => ['required', 'string', 'max:50'],
            ]),
            'divisions' => $request->validate([
                'division' => ['required', 'string', 'max:200'],
                'abbrev' => ['nullable', 'string', 'max:10'],
            ]),
            default => abort(404),
        };
    }

    private function lookupTable(string $table): string
    {
        return match ($table) {
            'document-types' => 'document_types',
            'statuses' => 'status',
            'divisions' => 'divisions',
            default => abort(404),
        };
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function normalizeLookup(string $table, array $payload): array
    {
        if ($table === 'document-types') {
            $payload['ABV'] = $payload['ABV'] ?? '';
        }
        if ($table === 'divisions') {
            $payload['abbrev'] = $payload['abbrev'] ?? '';
        }

        return $payload;
    }

    private function lookupKey(string $table): string
    {
        return match ($table) {
            'document-types' => 'document_type_id',
            'statuses' => 'status_id',
            'divisions' => 'division_id',
            default => abort(404),
        };
    }
}
