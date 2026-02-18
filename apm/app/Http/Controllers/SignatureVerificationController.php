<?php

namespace App\Http\Controllers;

use App\Helpers\PrintHelper;
use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ChangeRequest;
use App\Models\NonTravelMemo;
use App\Models\RequestARF;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignatureVerificationController extends Controller
{
    private const NON_SIGNING_ACTIONS = ['submitted', 'resubmitted', 'cancelled', 'rejected'];

    /**
     * Show the signature verification page (lookup by document number + year, verify by hash).
     */
    public function index()
    {
        return view('signature-verify.index');
    }

    /**
     * Lookup document by document_number and year; return document info and all signatories with hashes.
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|max:255',
            'year'            => 'required|integer|min:2000|max:2100',
        ]);

        $documentNumber = trim($request->input('document_number'));
        $year           = (int) $request->input('year');

        $document = $this->findDocumentByNumberAndYear($documentNumber, $year);

        if (!$document) {
            return redirect()
                ->route('signature-verify.index')
                ->with('error', 'No APM document found for the given document number and year.')
                ->withInput($request->only('document_number', 'year'));
        }

        $signatories = $this->buildSignatoriesWithHashes($document);

        return view('signature-verify.index', [
            'lookup_result' => [
                'document'    => $document,
                'doc_type'    => $this->getDocumentTypeLabel($document),
                'signatories' => $signatories,
            ],
            'document_number' => $documentNumber,
            'year'            => $year,
        ]);
    }

    /**
     * Verify a hash: find document by document_number (and optionally year), find which signatory matches the hash.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'hash'            => 'required|string|max:32',
            'document_number' => 'required|string|max:255',
            'year'            => 'nullable|integer|min:2000|max:2100',
        ]);

        $hash            = strtoupper(trim($request->input('hash')));
        $documentNumber  = trim($request->input('document_number'));
        $year            = $request->filled('year') ? (int) $request->input('year') : null;

        $documents = $this->findDocumentsByNumber($documentNumber, $year);

        if ($documents->isEmpty()) {
            return redirect()
                ->route('signature-verify.index')
                ->with('verify_error', 'No APM document found for the given document number.')
                ->withInput($request->only('hash', 'document_number', 'year'));
        }

        foreach ($documents as $document) {
            $signatories = $this->buildSignatoriesWithHashes($document);
            foreach ($signatories as $s) {
                if (isset($s['hash']) && $s['hash'] === $hash) {
                    return view('signature-verify.index', [
                        'verify_result' => [
                            'document'    => $document,
                            'doc_type'    => $this->getDocumentTypeLabel($document),
                            'signatory'   => $s,
                            'hash_matched' => true,
                        ],
                        'hash'           => $hash,
                        'document_number' => $documentNumber,
                        'year'           => $year,
                    ]);
                }
            }
        }

        // No match: show error and the document's signatories so user can compare
        $document = $documents->first();
        $signatories = $this->buildSignatoriesWithHashes($document);
        return view('signature-verify.index', [
            'verify_error' => 'The provided hash does not match any signatory for this document. Compare with the hashes below.',
            'lookup_result' => [
                'document'    => $document,
                'doc_type'    => $this->getDocumentTypeLabel($document),
                'signatories' => $signatories,
            ],
            'document_number' => $documentNumber,
            'year'            => $year,
            'hash'           => $hash,
        ])->withInput($request->only('hash', 'document_number', 'year'));
    }

    /**
     * Find a single document by document_number and year (first match across supported types).
     */
    private function findDocumentByNumberAndYear(string $documentNumber, int $year)
    {
        $documents = $this->findDocumentsByNumber($documentNumber, $year);
        return $documents->first();
    }

    /**
     * Find documents by document_number, optionally filtered by year.
     * Uses document number format (AU/CDC/{division}/IM/{type}/{counter}) to identify
     * the correct base table when possible; otherwise searches all supported tables.
     */
    private function findDocumentsByNumber(string $documentNumber, ?int $year = null)
    {
        $documents = collect();
        $target = $this->resolveTableAndModelFromDocumentNumber($documentNumber);

        if ($target) {
            $documents = $this->queryDocumentFromTable($target['table'], $target['model'], $documentNumber, $year);
        } else {
            foreach ($this->getAllTableAndModelMap() as $table => $class) {
                $documents = $documents->merge(
                    $this->queryDocumentFromTable($table, $class, $documentNumber, $year)
                );
            }
        }

        return $documents;
    }

    /**
     * Resolve table and model from document number using AU/CDC/.../IM/{type}/{counter} format.
     */
    private function resolveTableAndModelFromDocumentNumber(string $documentNumber): ?array
    {
        $parsed = DocumentNumberService::parseDocumentNumber($documentNumber);
        if (empty($parsed) || empty($parsed['document_type'])) {
            return null;
        }
        return DocumentNumberService::getTableAndModelForDocumentType($parsed['document_type']);
    }

    /**
     * Query a single table for a document by document_number and optional year.
     */
    private function queryDocumentFromTable(string $table, string $modelClass, string $documentNumber, ?int $year)
    {
        $documents = collect();
        $query = DB::table($table)->where('document_number', $documentNumber);
        if ($year) {
            $query->whereRaw('YEAR(created_at) = ?', [$year]);
        }
        $ids = $query->pluck('id');
        foreach ($ids as $id) {
            $doc = $modelClass::find($id);
            if ($doc) {
                $documents->push($doc);
            }
        }
        return $documents;
    }

    /**
     * All supported tables and models for fallback search (when document number is not in standard format).
     */
    private function getAllTableAndModelMap(): array
    {
        return [
            'special_memos'    => SpecialMemo::class,
            'non_travel_memos' => NonTravelMemo::class,
            'change_request'  => ChangeRequest::class,
            'request_arfs'     => RequestARF::class,
            'activities'       => Activity::class,
            'service_requests' => ServiceRequest::class,
        ];
    }

    /**
     * Build list of signatories with verification hashes for the given document.
     */
    private function buildSignatoriesWithHashes($document): array
    {
        $signatories = [];
        $itemId = $document->id;

        if ($document instanceof Activity) {
            // Activity can have trails in activity_approval_trails OR in approval_trails (morph).
            // Prefer approvalTrails() (morph) so we include budget-section signatories; fall back to ActivityApprovalTrail.
            $trails = $document->approvalTrails()->with(['staff', 'oicStaff'])->orderBy('created_at')->get();
            if ($trails->isEmpty()) {
                $trails = ActivityApprovalTrail::where('activity_id', $document->id)
                    ->with(['staff', 'oicStaff'])
                    ->orderBy('created_at')
                    ->get();
            }
            foreach ($trails as $trail) {
                if ($this->isSigningAction($trail->action ?? '')) {
                    $staffId = !empty($trail->oic_staff_id) ? $trail->oic_staff_id : $trail->staff_id;
                    $hash = PrintHelper::generateVerificationHash($itemId, $staffId, $this->normalizeDateTime($trail->created_at));
                    $signatories[] = $this->signatoryRow($trail, $hash, $trail instanceof ActivityApprovalTrail);
                }
            }
            return $signatories;
        }

        $trails = $document->approvalTrails()->with(['staff', 'oicStaff'])->orderBy('created_at')->get();

        foreach ($trails as $trail) {
            if ($this->isSigningAction($trail->action ?? '')) {
                $staffId = !empty($trail->oic_staff_id) ? $trail->oic_staff_id : $trail->staff_id;
                $hash = PrintHelper::generateVerificationHash($itemId, $staffId, $this->normalizeDateTime($trail->created_at));
                $signatories[] = $this->signatoryRow($trail, $hash, false);
            }
        }

        return $signatories;
    }

    private function isSigningAction(?string $action): bool
    {
        if ($action === null || $action === '') {
            return false;
        }
        return !in_array(strtolower($action), self::NON_SIGNING_ACTIONS, true);
    }

    private function normalizeDateTime($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string) $value;
    }

    private function signatoryRow($trail, string $hash, bool $isActivityTrail): array
    {
        $staff = null;
        if ($isActivityTrail) {
            $staff = $trail->oic_staff_id ? $trail->oicStaff : $trail->staff;
        } else {
            $staff = !empty($trail->oic_staff_id) ? $trail->oicStaff : $trail->staff;
        }
        $name = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : 'N/A';
        if ($staff && !empty($trail->oic_staff_id)) {
            $name .= ' (OIC)';
        }
        $date = $trail->created_at instanceof \DateTimeInterface
            ? $trail->created_at->format('j F Y H:i')
            : date('j F Y H:i', strtotime($trail->created_at));

        return [
            'role'   => $trail->approver_role_name ?? 'N/A',
            'name'   => $name,
            'date'   => $date,
            'action' => $trail->action ?? 'N/A',
            'hash'   => $hash,
        ];
    }

    private function getDocumentTypeLabel($document): string
    {
        if ($document instanceof SpecialMemo) {
            return 'Special Travel Memo';
        }
        if ($document instanceof NonTravelMemo) {
            return 'Non-Travel Memo';
        }
        if ($document instanceof ChangeRequest) {
            return 'Change Request / Addendum';
        }
        if ($document instanceof RequestARF) {
            return 'Request for ARF';
        }
        if ($document instanceof Activity) {
            return 'Matrix Memo / Activity';
        }
        if ($document instanceof ServiceRequest) {
            return 'Request DSA, Imprest and Ticket';
        }
        return 'APM Document';
    }
}
