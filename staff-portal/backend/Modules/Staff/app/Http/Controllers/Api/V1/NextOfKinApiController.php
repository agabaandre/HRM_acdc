<?php

namespace Modules\Staff\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Services\CsvExportService;
use Modules\Core\Services\PdfService;
use Modules\Staff\Services\NextOfKinReportService;
use Modules\Staff\Support\StaffAccess;

class NextOfKinApiController extends Controller
{
    public function index(Request $request, NextOfKinReportService $service): JsonResponse
    {
        if (! StaffAccess::canManageStaff() && ! StaffAccess::canViewDirectory()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));
        $result = $service->page($this->filterInput($request), $page, $perPage);

        return response()->json([
            'data' => $result['rows'],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / max(1, $perPage))),
                'kin_relationships' => $result['kin_relationships'],
            ],
        ]);
    }

    public function exportCsv(Request $request, NextOfKinReportService $service, CsvExportService $csv): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! StaffAccess::canManageStaff() && ! StaffAccess::canViewDirectory()) {
            abort(403);
        }

        $exported = $service->exportRows($this->filterInput($request));
        $rows = [[
            'SAPNO', 'Full name', 'Contract status', 'Job', 'Division', 'Duty station', 'Grade',
            'Work email', 'Tel 1', 'Tel 2', 'WhatsApp', 'Private email', 'Physical location',
            'Residential address', 'Dependants',
            'NOK 1 name', 'NOK 1 relationship', 'NOK 1 phone', 'NOK 1 email',
            'NOK 2 name', 'NOK 2 relationship', 'NOK 2 phone', 'NOK 2 email',
            'Additional NOK',
        ]];

        foreach ($exported as $r) {
            $nok = $r['next_of_kin'] ?? [];
            $n1 = $nok[0] ?? null;
            $n2 = $nok[1] ?? null;
            $extra = [];
            for ($i = 2; $i < count($nok); $i++) {
                $k = $nok[$i];
                $extra[] = trim(
                    ($k['name'] ?? '')
                    .(! empty($k['relationship_name']) ? ' ('.$k['relationship_name'].')' : '')
                    .': '.($k['phone'] ?? '').' / '.($k['email'] ?? '')
                );
            }
            $rows[] = [
                $r['SAPNO'] ?? '',
                $r['full_name'] ?? '',
                $r['contract_status_label'] ?? '',
                $r['job_name'] ?? '',
                $r['division_name'] ?? '',
                $r['duty_station_name'] ?? '',
                $r['grade'] ?? '',
                $r['work_email'] ?? '',
                $r['tel_1'] ?? '',
                $r['tel_2'] ?? '',
                $r['whatsapp'] ?? '',
                $r['private_email'] ?? '',
                $this->oneLine((string) ($r['physical_location'] ?? '')),
                $this->oneLine((string) ($r['residential_address_duty_station'] ?? '')),
                $r['number_of_dependants'] ?? '',
                $n1['name'] ?? '',
                $n1['relationship_name'] ?? '',
                $n1['phone'] ?? '',
                $n1['email'] ?? '',
                $n2['name'] ?? '',
                $n2['relationship_name'] ?? '',
                $n2['phone'] ?? '',
                $n2['email'] ?? '',
                implode(' | ', array_filter($extra)),
            ];
        }

        return $csv->stream('staff-next-of-kin.csv', $rows);
    }

    public function exportPdf(Request $request, NextOfKinReportService $service, PdfService $pdf): Response
    {
        if (! StaffAccess::canManageStaff() && ! StaffAccess::canViewDirectory()) {
            abort(403);
        }

        $exported = $service->exportRows($this->filterInput($request), 2000);
        $body = '';
        $n = 0;
        foreach ($exported as $r) {
            $n++;
            $nokBits = [];
            foreach ($r['next_of_kin'] ?? [] as $k) {
                $nokBits[] = trim(
                    ($k['name'] ?? '')
                    .(! empty($k['relationship_name']) ? ' ('.$k['relationship_name'].')' : '')
                    .' — '.trim(($k['phone'] ?? '').' / '.($k['email'] ?? ''), ' /')
                );
            }
            $body .= '<tr>'
                .'<td>'.$n.'</td>'
                .'<td>'.e((string) ($r['SAPNO'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['full_name'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['division_name'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['duty_station_name'] ?? '')).'</td>'
                .'<td>'.e((string) ($r['job_name'] ?? '')).'</td>'
                .'<td>'.e(implode('; ', array_filter($nokBits))).'</td>'
                .'</tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="7" align="center">No staff found for the selected filters.</td></tr>';
        }

        $html = '<h2 style="margin:0 0 8px;color:#2c3e50;">Staff Next of Kin</h2>
            <p style="margin:0 0 12px;color:#768B9E;font-size:11px;">Active / Due / Under renewal · '.$n.' record(s)</p>
            <table width="100%" cellpadding="3" cellspacing="0" border="1" style="border-collapse:collapse;font-size:8px;">
              <thead>
                <tr style="background:#f8fafc;">
                  <th>#</th><th>SAPNO</th><th>Name</th><th>Division</th><th>Duty station</th><th>Job</th><th>Next of kin</th>
                </tr>
              </thead>
              <tbody>'.$body.'</tbody>
            </table>';

        return $pdf->inline($html, 'staff-next-of-kin.pdf', [
            'title' => 'Staff Next of Kin',
            'document_url' => url('/staff/staff-portal/staff/next-of-kin'),
            'landscape' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterInput(Request $request): array
    {
        return [
            'name' => $request->query('name', $request->query('lname')),
            'sapno' => $request->query('sapno', $request->query('SAPNO')),
            'gender' => $request->query('gender'),
            'region_id' => $request->query('region_id'),
            'nationality_id' => $request->query('nationality_id'),
            'division_id' => $request->query('division_id'),
            'duty_station_id' => $request->query('duty_station_id'),
            'funder_id' => $request->query('funder_id'),
            'job_id' => $request->query('job_id'),
            'grade_id' => $request->query('grade_id'),
        ];
    }

    protected function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(["\r\n", "\n", "\r"], ' ', $value)) ?? '');
    }
}
