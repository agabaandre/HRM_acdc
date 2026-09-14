<?php

namespace Modules\Staff\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Services\CsvExportService;
use Modules\Core\Services\PdfService;
use Modules\Staff\Services\SignatureManagerService;
use Modules\Staff\Support\StaffAccess;

class SignatureManagerApiController extends Controller
{
    public function index(Request $request, SignatureManagerService $service): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));
        $result = $service->page($this->filters($request), $page, $perPage);

        return response()->json([
            'data' => $result['rows'],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
                'stats' => $result['stats'],
                'approver_count' => $result['approver_count'],
                'approver_cache' => $result['approver_cache'],
                'filters' => $this->filters($request),
            ],
        ]);
    }

    public function refreshApprovers(SignatureManagerService $service): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $meta = $service->refreshApprovers(true);

        return response()->json([
            'data' => [
                'approver_cache' => $meta,
                'approver_count' => (int) ($meta['count'] ?? 0),
            ],
            'message' => ((int) ($meta['count'] ?? 0)) > 0
                ? 'Approver list refreshed.'
                : 'No approver IDs returned from APM. Check APM_BASE_URL or try All active staff.',
        ]);
    }

    public function bulkSave(Request $request, SignatureManagerService $service): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'signatures' => ['required', 'array', 'max:200'],
            'signatures.*.staff_id' => ['required', 'integer', 'min:1'],
            'signatures.*.signature_data_url' => ['required', 'string'],
            'signatures.*.allow_override' => ['sometimes', 'boolean'],
        ]);

        $result = $service->bulkSave($validated['signatures']);

        return response()->json([
            'data' => $result,
            'message' => sprintf(
                'Saved %d, skipped %d, failed %d.',
                $result['saved'],
                $result['skipped'],
                $result['failed']
            ),
        ]);
    }

    public function upload(Request $request, SignatureManagerService $service): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'file', 'max:2048'],
            'allow_override' => ['sometimes', 'boolean'],
        ]);

        $saved = $service->uploadManual(
            (int) $validated['staff_id'],
            $validated['signature'],
            (bool) ($validated['allow_override'] ?? false)
        );

        return response()->json([
            'data' => $saved,
            'message' => 'Signature uploaded.',
        ]);
    }

    public function exportCsv(Request $request, SignatureManagerService $service, CsvExportService $csv): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! StaffAccess::canManageStaff()) {
            abort(403);
        }

        $exported = $service->exportRows($this->filters($request));
        $rows = [['#', 'Staff ID', 'SAPNO', 'Name', 'Signature status', 'Signature text']];
        $n = 0;
        foreach ($exported as $r) {
            $n++;
            $rows[] = [
                $n,
                $r['staff_id'] ?? '',
                $r['SAPNO'] ?? '',
                $r['full_name'] ?? '',
                $r['signature_status_label'] ?? '',
                $r['signature_text'] ?? '',
            ];
        }

        return $csv->stream('staff-signature-manager.csv', $rows);
    }

    public function exportPdf(Request $request, SignatureManagerService $service, PdfService $pdf): Response
    {
        if (! StaffAccess::canManageStaff()) {
            abort(403);
        }

        $exported = $service->exportRows($this->filters($request), 2000);
        $body = '';
        $n = 0;
        foreach ($exported as $r) {
            $n++;
            $body .= '<tr>'
                .'<td>'.$n.'</td>'
                .'<td>'.e((string) ($r['SAPNO'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['full_name'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['signature_status_label'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['signature_text'] ?? '')).'</td>'
                .'</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="5" align="center">No staff found for the selected filters.</td></tr>';
        }

        $html = '<h2 style="margin:0 0 8px;color:#2c3e50;">Signature Manager</h2>
            <p style="margin:0 0 12px;color:#768B9E;font-size:11px;">'.$n.' record(s)</p>
            <table width="100%" cellpadding="3" cellspacing="0" border="1" style="border-collapse:collapse;font-size:9px;">
              <thead>
                <tr style="background:#f8fafc;">
                  <th>#</th><th>SAPNO</th><th>Name</th><th>Status</th><th>Signature text</th>
                </tr>
              </thead>
              <tbody>'.$body.'</tbody>
            </table>';

        return $pdf->inline($html, 'staff-signature-manager.pdf', [
            'title' => 'Signature Manager',
            'document_url' => url('/staff/staff-portal/staff/signatures'),
            'landscape' => true,
        ]);
    }

    /**
     * @return array{staff_name: string, scope: string, signature_status: string}
     */
    protected function filters(Request $request): array
    {
        $scope = trim((string) $request->query('scope', 'approvers'));
        if (! in_array($scope, ['approvers', 'current'], true)) {
            $scope = 'approvers';
        }
        $status = trim((string) $request->query('signature_status', 'all'));
        if (! in_array($status, ['all', 'valid', 'missing', 'broken'], true)) {
            $status = 'all';
        }

        return [
            'staff_name' => trim((string) $request->query('staff_name', '')),
            'scope' => $scope,
            'signature_status' => $status,
        ];
    }
}
