<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Exports\LicensesExport;
use App\Http\Controllers\Concerns\DownloadsPdfReports;
use App\Http\Controllers\Controller;
use App\Models\HelpdeskLicense;
use App\Services\HelpdeskPdfReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class LicenseController extends Controller
{
    use AuthorizesHelpdeskTools;
    use DownloadsPdfReports;

    public function summary(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $licenses = HelpdeskLicense::query()->get();
        $expiringSoon = $licenses->filter(fn ($l) => ($l->expiry['is_expiring_soon'] ?? false))->count();
        $expired = $licenses->filter(fn ($l) => ($l->expiry['is_expired'] ?? false))->count();

        return response()->json([
            'data' => [
                'license_count' => $licenses->count(),
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
                'total_seats' => (int) $licenses->sum('seats_total'),
                'seats_used' => (int) $licenses->sum('seats_used'),
                'annual_cost' => round($licenses->sum('cost'), 2),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->ensureLicenseManager($request);

        $rows = $this->filteredQuery($request)->orderBy('name')->limit(5000)->get();

        return Excel::download(
            new LicensesExport($rows),
            'licenses-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function exportPdf(Request $request, HelpdeskPdfReportService $pdf): Response
    {
        $this->ensureLicenseManager($request);

        $licenses = HelpdeskLicense::query()->orderBy('expiry_date')->orderBy('name')->limit(2000)->get();
        $rows = $licenses->map(fn (HelpdeskLicense $l) => [
            $l->name,
            $l->vendor,
            $l->seats_used.'/'.$l->seats_total,
            optional($l->expiry_date)?->format('Y-m-d'),
            $l->expiry['days_remaining'] ?? null,
            ! empty($l->expiry['is_expired']) ? 'Expired' : (! empty($l->expiry['is_expiring_soon']) ? 'Expiring soon' : 'OK'),
            $l->cost,
            $l->status,
            $l->responsible_person['name'] ?? null,
        ])->all();

        $summaryLines = [
            'Licenses: '.$licenses->count(),
            'Expiring soon: '.$licenses->filter(fn ($l) => $l->expiry['is_expiring_soon'] ?? false)->count(),
            'Expired: '.$licenses->filter(fn ($l) => $l->expiry['is_expired'] ?? false)->count(),
            'Annual cost: '.round($licenses->sum('cost'), 2),
        ];

        return $this->pdfTableDownload(
            $request,
            $pdf,
            'Software licenses',
            ['Name', 'Vendor', 'Seats', 'Expiry', 'Days left', 'Health', 'Cost', 'Status', 'Responsible'],
            $rows,
            'licenses-'.now()->format('Y-m-d').'.pdf',
            $summaryLines,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $rows = $this->filteredQuery($request)
            ->orderBy('expiry_date')
            ->orderBy('name')
            ->paginate(min(100, max(10, (int) $request->input('per_page', 25))));

        return response()->json($rows);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\HelpdeskLicense>
     */
    private function filteredQuery(Request $request)
    {
        $query = HelpdeskLicense::query();

        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString());
        }
        if ($request->boolean('expired')) {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('vendor', 'like', $q)
                    ->orWhere('license_key', 'like', $q);
            });
        }
        if ($request->filled('vendor')) {
            $query->where('vendor', (string) $request->input('vendor'));
        }
        if ($request->filled('responsible_staff_id')) {
            $rid = (int) $request->input('responsible_staff_id');
            if ($rid === -1) {
                $query->where(function ($sub) {
                    $sub->whereNull('responsible_staff_id')->orWhere('responsible_staff_id', 0);
                });
            } elseif ($rid > 0) {
                $query->where('responsible_staff_id', $rid);
            }
        }

        return $query;
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'vendor' => ['nullable', 'string', 'max:191'],
            'license_key' => ['nullable', 'string'],
            'seats_total' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'seats_used' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'purchase_date' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'warning_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->assertEndOnOrAfterStart($validated);
        $this->syncDurationFromDates($validated);

        $row = HelpdeskLicense::query()->create(array_merge($validated, [
            'created_by_user_id' => $request->user()?->id,
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, HelpdeskLicense $license): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'vendor' => ['nullable', 'string', 'max:191'],
            'license_key' => ['nullable', 'string'],
            'seats_total' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'seats_used' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'purchase_date' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'expiry_date' => ['nullable', 'date'],
            'warning_days_before' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'min:1'],
        ]);

        // End date is user-entered; duration_months is derived from start/end when both exist.
        $this->assertEndOnOrAfterStart($validated, $license);
        $this->syncDurationFromDates($validated, $license);

        $license->fill($validated);
        $license->save();

        return response()->json(['data' => $license->fresh()]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertEndOnOrAfterStart(array $validated, ?HelpdeskLicense $existing = null): void
    {
        $purchase = $validated['purchase_date']
            ?? $existing?->purchase_date?->format('Y-m-d');
        $expiry = $validated['expiry_date']
            ?? $existing?->expiry_date?->format('Y-m-d');
        if (empty($purchase) || empty($expiry)) {
            return;
        }
        if (Carbon::parse($expiry)->lt(Carbon::parse($purchase))) {
            abort(response()->json([
                'message' => 'The end date must be on or after the start date.',
                'errors' => ['expiry_date' => ['The end date must be on or after the start date.']],
            ], 422));
        }
    }

    /**
     * When start + end dates are present, store duration_months from the day span.
     * Legacy fallback: if end is missing but months are set, derive end from start + months.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncDurationFromDates(array &$validated, ?HelpdeskLicense $existing = null): void
    {
        $purchase = $validated['purchase_date']
            ?? $existing?->purchase_date?->format('Y-m-d');
        $expiry = $validated['expiry_date']
            ?? $existing?->expiry_date?->format('Y-m-d');
        $months = $validated['duration_months']
            ?? $existing?->duration_months;

        if (! empty($purchase) && ! empty($expiry)) {
            $days = Carbon::parse($purchase)->diffInDays(Carbon::parse($expiry), false);
            if ($days < 0) {
                return;
            }
            $validated['duration_months'] = max(1, (int) round($days / 30.4375));

            return;
        }

        if (empty($expiry) && ! empty($purchase) && ! empty($months) && (int) $months >= 1) {
            $validated['expiry_date'] = Carbon::parse($purchase)
                ->addMonths((int) $months)
                ->toDateString();
        }
    }

    public function destroy(Request $request, HelpdeskLicense $license): JsonResponse
    {
        $this->ensureLicenseManager($request);
        $license->delete();

        return response()->json(['ok' => true]);
    }
}
