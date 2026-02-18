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
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'No APM document found for the given document number and year.']);
            }
            return redirect()
                ->route('signature-verify.index')
                ->with('error', 'No APM document found for the given document number and year.')
                ->withInput($request->only('document_number', 'year'));
        }

        $signatories = $this->buildSignatoriesWithHashes($document);
        $metadata = $this->getDocumentMetadata($document);

        $payload = $this->buildUnifiedPayload('lookup', $document, $metadata, $signatories, null, null, null, $year);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return view('signature-verify.index', [
            'result_for_display' => $payload,
            'lookup_result' => [
                'document'    => $document,
                'doc_type'    => $this->getDocumentTypeLabel($document),
                'signatories' => $signatories,
                'metadata'    => $metadata,
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
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'No APM document found for the given document number.']);
            }
            return redirect()
                ->route('signature-verify.index')
                ->with('verify_error', 'No APM document found for the given document number.')
                ->withInput($request->only('hash', 'document_number', 'year'));
        }

        foreach ($documents as $document) {
            $signatories = $this->buildSignatoriesWithHashes($document);
            foreach ($signatories as $s) {
                if (isset($s['hash']) && $s['hash'] === $hash) {
                    $metadata = $this->getDocumentMetadata($document);
                    $payload = $this->buildUnifiedPayload('verify', $document, $metadata, $signatories, $s, null, null, $year);
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json($payload);
                    }
                    return view('signature-verify.index', [
                        'result_for_display' => $payload,
                        'verify_result' => [
                            'document'    => $document,
                            'doc_type'    => $this->getDocumentTypeLabel($document),
                            'signatory'   => $s,
                            'hash_matched' => true,
                            'metadata'    => $metadata,
                        ],
                        'hash'           => $hash,
                        'document_number' => $documentNumber,
                        'year'           => $year,
                    ]);
                }
            }
        }

        // No match: show document and signatories so user can compare
        $document = $documents->first();
        $signatories = $this->buildSignatoriesWithHashes($document);
        $metadata = $this->getDocumentMetadata($document);
        $payload = $this->buildUnifiedPayload('verify', $document, $metadata, $signatories, null, 'The provided hash does not match any signatory for this document.', null, $year);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }
        return view('signature-verify.index', [
            'result_for_display' => $payload,
            'verify_error' => 'The provided hash does not match any signatory for this document. Compare with the hashes below.',
            'lookup_result' => [
                'document'    => $document,
                'doc_type'    => $this->getDocumentTypeLabel($document),
                'signatories' => $signatories,
                'metadata'    => $metadata,
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
            // Matrix/Activity documents can have hashes from both approval_trails (morph, budget section)
            // and activity_approval_trails (main signature section). Include both so all PDF hashes can be matched.
            $seenHashes = [];
            $addFromTrails = function ($trails, $isActivityTrail) use ($itemId, &$signatories, &$seenHashes) {
                foreach ($trails as $trail) {
                    if (!$this->isSigningAction($trail->action ?? '')) {
                        continue;
                    }
                    $staffId = !empty($trail->oic_staff_id) ? $trail->oic_staff_id : $trail->staff_id;
                    $hash = PrintHelper::generateVerificationHash($itemId, $staffId, $this->normalizeDateTime($trail->created_at));
                    if (isset($seenHashes[$hash])) {
                        continue;
                    }
                    $seenHashes[$hash] = true;
                    $signatories[] = $this->signatoryRow($trail, $hash, $isActivityTrail);
                }
            };

            $morphTrails = $document->approvalTrails()->with(['staff', 'oicStaff'])->orderBy('created_at')->get();
            $addFromTrails($morphTrails, false);

            $activityTrails = ActivityApprovalTrail::where('activity_id', $document->id)
                ->with(['staff', 'oicStaff'])
                ->orderBy('created_at')
                ->get();
            $addFromTrails($activityTrails, true);

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

    /**
     * Build a unified JSON payload for all verification result types (lookup, verify, upload_validation).
     */
    private function buildUnifiedPayload(
        string $resultType,
        $document,
        array $metadata,
        array $signatories,
        ?array $matchedSignatory,
        ?string $message,
        ?array $uploadExtras = null,
        ?int $year = null
    ): array {
        $docNumber = $document ? ($document->document_number ?? 'N/A') : null;
        $docType = $document ? $this->getDocumentTypeLabel($document) : null;

        $payload = [
            'success'     => true,
            'result_type' => $resultType,
            'document'    => [
                'document_number' => $docNumber,
                'doc_type'        => $docType,
                'metadata'        => $metadata,
            ],
            'signatories' => $signatories,
            'message'     => $message,
        ];

        if ($year !== null) {
            $payload['year'] = $year;
        }

        if ($matchedSignatory !== null) {
            $payload['matched_signatory'] = $matchedSignatory;
            $payload['hash_matched'] = true;
        } else {
            $payload['hash_matched'] = false;
        }

        if ($uploadExtras !== null) {
            $payload['valid']                      = $uploadExtras['valid'] ?? false;
            $payload['extracted_document_numbers'] = $uploadExtras['extracted_document_numbers'] ?? [];
            $payload['extracted_hashes']           = $uploadExtras['extracted_hashes'] ?? [];
            $payload['hash_validations']           = $uploadExtras['hash_validations'] ?? [];
            $payload['documents']                  = $uploadExtras['documents'] ?? [];
        }

        return $payload;
    }

    /**
     * Get document metadata (creator, division, date created) for display.
     */
    private function getDocumentMetadata($document): array
    {
        $creator = 'N/A';
        $division = 'N/A';
        $dateCreated = isset($document->created_at)
            ? (\Carbon\Carbon::parse($document->created_at)->format('j F Y H:i'))
            : 'N/A';

        if ($document instanceof Activity) {
            $staff = $document->staff ?? $document->staff()->first();
            if ($staff) {
                $creator = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            }
            $div = $document->division ?? $document->division()->first();
            if (!$div && $document->matrix_id) {
                $matrix = $document->matrix ?? $document->matrix()->first();
                $div = $matrix->division ?? ($matrix ? $matrix->division()->first() : null);
            }
            if ($div) {
                $division = $div->division_name ?? $div->division_short_name ?? 'N/A';
            }
        } else {
            $staff = $document->staff ?? (method_exists($document, 'staff') ? $document->staff()->first() : null);
            if ($staff) {
                $creator = trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? ''));
            }
            $div = $document->division ?? (method_exists($document, 'division') ? $document->division()->first() : null);
            if ($div) {
                $division = $div->division_name ?? $div->division_short_name ?? 'N/A';
            }
        }

        $activityTitle = null;
        if (isset($document->activity_title) && (string) $document->activity_title !== '') {
            $activityTitle = $document->activity_title;
        }

        return [
            'creator'        => $creator ?: 'N/A',
            'division'       => $division ?: 'N/A',
            'date_created'   => $dateCreated,
            'activity_title' => $activityTitle,
        ];
    }

    /**
     * Validate an uploaded APM document (PDF): extract document number and hashes, verify against system.
     * File is not stored on the server; only read in memory / from temp and discarded.
     */
    public function validateUpload(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240', // 10MB, PDF only
        ]);

        $file = $request->file('document');
        $path = $file->getRealPath();

        $text = $this->extractTextFromPdf($path);
        if ($text === null) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not read the PDF. Ensure it is a valid, unencrypted PDF. If the problem persists, run: composer require smalot/pdfparser']);
            }
            return redirect()
                ->route('signature-verify.index')
                ->with('upload_error', 'Could not read the PDF. Ensure it is a valid, unencrypted PDF. If the problem persists, run: composer require smalot/pdfparser');
        }

        $documentNumbers = $this->extractDocumentNumbersFromText($text);
        $hashes = $this->extractHashesFromText($text);

        $results = [
            'extracted_document_numbers' => array_values(array_unique($documentNumbers)),
            'extracted_hashes'           => array_values(array_unique($hashes)),
            'document'                   => null,
            'doc_type'                   => null,
            'signatories'                => [],
            'hash_validations'           => [], // [ ['hash' => '...', 'matched' => true|false, 'signatory' => ...|null ] ]
            'valid'                      => false, // final state: true if there is at least one matching hash
        ];

        $year = $this->extractYearFromText($text);
        $documents = collect();

        foreach ($results['extracted_document_numbers'] as $docNum) {
            $found = $this->findDocumentsByNumber($docNum, $year);
            if ($found->isNotEmpty()) {
                $documents = $documents->merge($found);
            }
            if ($year === null) {
                $foundAny = $this->findDocumentsByNumber($docNum, null);
                if ($foundAny->isNotEmpty()) {
                    $documents = $documents->merge($foundAny);
                }
            }
        }
        $documents = $documents->unique('id');

        if ($documents->isNotEmpty()) {
            // Support multiple document numbers (e.g. ARF + parent memo, service request + parent).
            // Build signatories from ALL found documents and validate hashes against any of them.
            $seenHashes = [];
            $allSignatories = [];
            $documentsList = [];

            foreach ($documents as $doc) {
                $docType = $this->getDocumentTypeLabel($doc);
                $docNumber = $doc->document_number ?? 'N/A';
                $documentsList[] = [
                    'document_number' => $docNumber,
                    'doc_type'        => $docType,
                    'metadata'        => $this->getDocumentMetadata($doc),
                ];

                foreach ($this->buildSignatoriesWithHashes($doc) as $s) {
                    $h = $s['hash'] ?? null;
                    if ($h && !isset($seenHashes[$h])) {
                        $seenHashes[$h] = true;
                        $s['document_number'] = $docNumber;
                        $s['doc_type']        = $docType;
                        $allSignatories[] = $s;
                    }
                }
            }

            $results['document']     = $documents->first();
            $results['doc_type']    = $this->getDocumentTypeLabel($documents->first());
            $results['metadata']    = $this->getDocumentMetadata($documents->first());
            $results['signatories'] = $allSignatories;
            $results['documents']   = $documentsList;

            foreach ($results['extracted_hashes'] as $hash) {
                $hashUpper = strtoupper(trim($hash));
                $matched = null;
                foreach ($results['signatories'] as $s) {
                    if (isset($s['hash']) && $s['hash'] === $hashUpper) {
                        $matched = $s;
                        break;
                    }
                }
                $results['hash_validations'][] = [
                    'hash'      => $hashUpper,
                    'matched'   => $matched !== null,
                    'signatory' => $matched,
                ];
            }
            $results['valid'] = collect($results['hash_validations'])->contains('matched', true);
        } else {
            $results['metadata']  = ['creator' => 'N/A', 'division' => 'N/A', 'date_created' => 'N/A'];
            $results['documents'] = [];
        }

        $document = $results['document'];
        $metadata = $results['metadata'] ?? ['creator' => 'N/A', 'division' => 'N/A', 'date_created' => 'N/A'];
        $uploadExtras = [
            'valid'                      => $results['valid'],
            'extracted_document_numbers' => $results['extracted_document_numbers'],
            'extracted_hashes'           => $results['extracted_hashes'],
            'hash_validations'           => $results['hash_validations'],
            'documents'                  => $results['documents'] ?? [],
        ];
        $yearForPayload = $year ?? ($document ? (int) (\Carbon\Carbon::parse($document->created_at)->format('Y')) : null);
        $payload = $this->buildUnifiedPayload(
            'upload_validation',
            $document,
            $metadata,
            $results['signatories'],
            null,
            $results['document'] ? null : 'No matching document found in the system for the extracted document number(s).',
            $uploadExtras,
            $yearForPayload
        );
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return view('signature-verify.index', [
            'result_for_display' => $payload,
            'upload_validation_result' => $results,
        ]);
    }

    /**
     * Generate a PDF of the verification result with optional "Verified" watermark when hash matches.
     * GET params: document_number (required), year (required), hash (optional).
     */
    public function printPdf(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|max:255',
            'year'            => 'required|integer|min:2000|max:2100',
            'hash'            => 'nullable|string|max:32',
        ]);

        $documentNumber = trim($request->input('document_number'));
        $year           = (int) $request->input('year');
        $hash           = $request->filled('hash') ? strtoupper(trim($request->input('hash'))) : null;

        $document = $this->findDocumentByNumberAndYear($documentNumber, $year);

        if (!$document) {
            abort(404, 'No APM document found for the given document number and year.');
        }

        $signatories = $this->buildSignatoriesWithHashes($document);
        $metadata = $this->getDocumentMetadata($document);
        $docType = $this->getDocumentTypeLabel($document);
        $matchedSignatory = null;
        $hashMatched = false;

        if ($hash !== null) {
            foreach ($signatories as $s) {
                if (isset($s['hash']) && $s['hash'] === $hash) {
                    $matchedSignatory = $s;
                    $hashMatched = true;
                    break;
                }
            }
        }

        $verificationAttempted = $hash !== null;
        $options = [];
        if ($hashMatched) {
            $options['watermark_text']  = 'Verified';
            $options['watermark_alpha'] = 0.2;
        } elseif ($verificationAttempted) {
            $options['watermark_text']  = 'Failed';
            $options['watermark_alpha'] = 0.2;
        }

        $data = [
            'document'                => $document,
            'doc_type'                => $docType,
            'document_number'         => $document->document_number ?? 'N/A',
            'metadata'                => $metadata,
            'signatories'             => $signatories,
            'matched_signatory'       => $matchedSignatory,
            'hash_matched'            => $hashMatched,
            'verification_attempted'  => $verificationAttempted,
            'watermark_text'          => $options['watermark_text'] ?? null,
            'watermark_alpha'         => $options['watermark_alpha'] ?? 0.2,
        ];

        $mpdf = generate_pdf('signature-verify.print-pdf', $data, $options);

        $filename = 'signature-verification-' . preg_replace('/[^a-zA-Z0-9\-]/', '-', $documentNumber) . '.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Extract text from PDF using temp path. Returns null if parser unavailable or parse fails.
     */
    private function extractTextFromPdf(string $path): ?string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return null;
        }
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            return $pdf->getText() ?: '';
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract APM document numbers from text (e.g. AU/CDC/SDI/IM/SM/011).
     */
    private function extractDocumentNumbersFromText(string $text): array
    {
        $matches = [];
        if (preg_match_all('/AU\/CDC\/[A-Za-z0-9]+\/IM\/(?:QM|NT|SPM|SM|CR|SR|ARF)\/\d{3}/', $text, $m)) {
            $matches = $m[0];
        }
        return array_map('trim', $matches);
    }

    /**
     * Extract 16-char verification hashes from text (e.g. "Verify Hash: XXXXX" or "Hash: XXXXX").
     */
    private function extractHashesFromText(string $text): array
    {
        $matches = [];
        if (preg_match_all('/(?:Verify\s+Hash\s*:\s*|Hash\s*:\s*)([A-Fa-f0-9]{16})/u', $text, $m)) {
            $matches = $m[1];
        }
        return array_values(array_unique(array_map('trim', $matches)));
    }

    /**
     * Try to extract a 4-digit year from text (e.g. 2026) for lookup.
     */
    private function extractYearFromText(string $text): ?int
    {
        if (preg_match('/\b(20[0-3][0-9])\b/', $text, $m)) {
            $y = (int) $m[1];
            if ($y >= 2000 && $y <= 2039) {
                return $y;
            }
        }
        return null;
    }
}
