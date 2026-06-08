<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\MemoTypeDefinition;
use App\Models\OtherMemo;
use App\Jobs\RecordOtherMemoApproverDocumentTimingJob;
use App\Models\OtherMemoApprovalTrail;
use App\Models\Staff;
use App\Models\WorkflowDefinition;
use App\Services\OtherMemoApproverNotifier;
use App\Support\ApprovedMemoReferenceResolver;
use App\Support\OtherMemoCc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Jobs\SendDocumentPdfEmailJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OtherMemoController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $currentStaffId = $this->staffId();

        $staff = Cache::remember('other_memos_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('other_memos_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $year = $this->resolveOtherMemoYearString($request);

        if (\App\Support\ApmListFragment::wants($request)) {
            try {
                $tab = $request->get('tab', '');
                $yearApplied = $this->resolveOtherMemoYearString($request);

                [$mySubmittedMemos, $myDivisionMemos, $allMemos] = $this->paginateOtherMemoTabs($request, $currentStaffId);

                $countMySubmitted = $mySubmittedMemos->total();
                $countMyDivision = $myDivisionMemos->total();
                $countAllMemos = $allMemos instanceof LengthAwarePaginator ? $allMemos->total() : $allMemos->count();

                $html = match ($tab) {
                    'mySubmitted' => view('other-memos.partials.my-submitted-tab', compact('mySubmittedMemos'))->render(),
                    'myDivision' => view('other-memos.partials.my-division-tab', compact('myDivisionMemos'))->render(),
                    'allMemos' => view('other-memos.partials.all-memos-tab', compact('allMemos'))->render(),
                    default => '',
                };

                return \App\Support\ApmListFragment::json([
                    'html' => $html,
                    'year_applied' => $yearApplied,
                    'count_my_submitted' => $countMySubmitted,
                    'count_my_division' => $countMyDivision,
                    'count_all_memos' => $countAllMemos,
                ]);
            } catch (\Throwable $e) {
                Log::error('Other memos index fragment failed', [
                    'tab' => $request->get('tab'),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return \App\Support\ApmListFragment::json([
                    'error' => config('app.debug')
                        ? $e->getMessage()
                        : 'Error loading data. Please try again.',
                    'html' => '',
                    'count_my_submitted' => 0,
                    'count_my_division' => 0,
                    'count_all_memos' => 0,
                ]);
            }
        }

        [$mySubmittedMemos, $myDivisionMemos, $allMemos] = $this->paginateOtherMemoTabs($request, $currentStaffId);

        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $yearRange = range($currentYear, $minYear);
        $years = ['all' => 'All years'] + array_combine($yearRange, $yearRange);

        return view('other-memos.index', compact(
            'mySubmittedMemos',
            'myDivisionMemos',
            'allMemos',
            'staff',
            'divisions',
            'year',
            'years',
            'currentStaffId',
        ));
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: LengthAwarePaginator, 2: LengthAwarePaginator|\Illuminate\Support\Collection}
     */
    private function paginateOtherMemoTabs(Request $request, int $currentStaffId): array
    {
        $mySubmittedMemos = $this->buildOtherMemoMySubmittedQuery($request, $currentStaffId)
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $myDivisionMemos = $this->buildOtherMemoMyDivisionQuery($request)
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $allMemos = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMemos = $this->buildOtherMemoAllQuery($request)
                ->orderByDesc('updated_at')
                ->paginate(20)
                ->withQueryString();
        }

        return [$mySubmittedMemos, $myDivisionMemos, $allMemos];
    }

    private function buildOtherMemoMySubmittedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = OtherMemo::query()
            ->with(['creator', 'division', 'staff', 'currentApprover'])
            ->where('staff_id', $currentStaffId);
        $this->applyOtherMemoIndexFilters($q, $request);

        return $q;
    }

    private function buildOtherMemoAllQuery(Request $request): Builder
    {
        $q = OtherMemo::query()->with(['creator', 'division', 'staff', 'currentApprover']);
        $this->applyOtherMemoIndexFilters($q, $request);

        return $q;
    }

    private function buildOtherMemoMyDivisionQuery(Request $request): Builder
    {
        $q = OtherMemo::query()->with(['creator', 'division', 'staff', 'currentApprover']);
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId);
        } else {
            $q->whereRaw('1=0');
        }
        $q->where('overall_status', '!=', 'archived');
        $this->applyOtherMemoIndexFilters($q, $request);

        return $q;
    }

    private function applyOtherMemoIndexFilters(Builder $query, Request $request): void
    {
        $year = $this->resolveOtherMemoYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }
        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%'.$request->document_number.'%');
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', (int) $request->staff_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->division_id);
        }
        if ($request->filled('status')) {
            $status = (string) $request->status;
            if ($status === 'rejected') {
                $query->where('overall_status', OtherMemo::STATUS_CANCELLED);
            } else {
                $query->where('overall_status', $status);
            }
        }
        if ($request->filled('search')) {
            $this->applyOtherMemoSearchFilter($query, $request->string('search')->toString());
        }
    }

    private function applyOtherMemoSearchFilter(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(function (Builder $q) use ($like) {
            $q->where('memo_type_name_snapshot', 'like', $like)
                ->orWhere('document_number', 'like', $like)
                ->orWhere('payload->title', 'like', $like);
        });
    }

    private function resolveOtherMemoYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '') {
            $year = (string) $currentYear;
        }
        $year = (string) $year;
        if ($year !== 'all' && is_numeric($year) && (int) $year === 0) {
            $year = (string) $currentYear;
        }

        return $year;
    }

    public function staffLookup(Request $request): JsonResponse
    {
        $id = (int) $request->query('staff_id', 0);
        if ($id <= 0) {
            return response()->json(['success' => false, 'name' => null]);
        }
        $staff = Staff::query()->where('staff_id', $id)->where('active', 1)->first();
        if (! $staff) {
            return response()->json(['success' => false, 'name' => null]);
        }
        $name = trim(($staff->title ? $staff->title.' ' : '').$staff->fname.' '.$staff->lname);

        return response()->json(['success' => true, 'name' => $name, 'email' => $staff->work_email]);
    }

    public function create(): View
    {
        return view('other-memos.create', $this->approverFormSharedData(null));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->respondOtherMemoForm($request, function () use ($request) {
            $definition = $this->resolveDefinition($request);
            $this->assertUploadMemoPdfRulesOnStore($request, $definition);
            $this->assertNoAttachmentsWhenDisabled($request, (bool) $definition->attachments_enabled);
            if ($definition->attachments_enabled) {
                $this->validateOtherMemoAttachmentMultipartRules($request);
            }
            $payload = $this->validatePayloadForSchema($request, $definition->fields_schema ?? []);
            $approvers = $this->normalizeApprovers($request);

            $attachmentRows = $this->collectUploadedOtherMemoAttachmentsOnCreate($request, $definition);
            $this->assertAttachmentsPresentWhenRequired($definition, $attachmentRows);
            $this->assertUploadMemoStoredAttachmentsAreSinglePdf($attachmentRows, (string) $definition->slug);

            return OtherMemo::create(array_merge([
                'memo_type_slug' => $definition->slug,
                'memo_type_name_snapshot' => $definition->name,
                'ref_prefix_snapshot' => $definition->ref_prefix,
                'signature_style_snapshot' => $definition->signature_style,
                'fields_schema_snapshot' => MemoTypeDefinition::normalizeFieldsSchemaRows($definition->fields_schema ?? []),
                'attachments_enabled_snapshot' => (bool) $definition->attachments_enabled,
                'attachment' => $attachmentRows,
                'payload' => $payload,
                'approvers_config' => $approvers,
                'staff_id' => $this->staffId(),
                'division_id' => user_session('division_id'),
                'overall_status' => OtherMemo::STATUS_DRAFT,
            ], $this->ccAttributesFromRequest($request, $definition), $this->referencedMemosAttributesFromRequest($request, $definition)));
        }, 'Draft saved.', 'other-memos.show');
    }

    public function show(OtherMemo $other_memo): View
    {
        $this->authorizeView($other_memo);
        $other_memo->load(['approvalTrails.staff', 'approvalTrails.otherMemo', 'creator', 'currentApprover', 'division', 'memoTypeDefinition']);

        $emailPdfChoices = staff_pdf_mail_recipient_choice_list();

        return view('other-memos.show', [
            'memo' => $other_memo,
            'memoAttachments' => $other_memo->attachmentsList(),
            'canEdit' => $this->canEdit($other_memo),
            'canSubmit' => $this->canSubmit($other_memo),
            'canApproveOrReturn' => $this->canApproveOrReturn($other_memo),
            'canPrint' => $this->canPrint($other_memo),
            'emailPdfRecipientChoices' => $emailPdfChoices,
            'canEmailPdf' => $this->canPrint($other_memo) && count($emailPdfChoices) > 0,
        ]);
    }

    public function attachmentPreview(OtherMemo $other_memo, int $index): Response
    {
        return $this->streamOtherMemoAttachment($other_memo, $index, false);
    }

    public function attachmentDownload(OtherMemo $other_memo, int $index): Response
    {
        return $this->streamOtherMemoAttachment($other_memo, $index, true);
    }

    private function streamOtherMemoAttachment(OtherMemo $other_memo, int $index, bool $download): Response
    {
        $this->authorizeView($other_memo);
        $attachment = $this->memoAttachmentAtIndex($other_memo, $index);
        $storedPath = (string) ($attachment['path'] ?? $attachment['file_path'] ?? '');
        $absolute = \App\Support\OtherMemoAttachments::resolveFilePath($storedPath);
        if ($absolute === null) {
            abort(404, 'Attachment file not found.');
        }

        $name = (string) ($attachment['original_name'] ?? $attachment['filename'] ?? basename($storedPath));
        $mime = (string) ($attachment['mime_type'] ?? 'application/octet-stream');
        if ($mime === 'application/octet-stream') {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => 'application/octet-stream',
            };
        }

        return response()->streamDownload(function () use ($absolute) {
            $stream = fopen($absolute, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, basename($name), [
            'Content-Type' => $mime,
        ], $download ? 'attachment' : 'inline');
    }

    public function edit(OtherMemo $other_memo): View|RedirectResponse
    {
        if (! $this->canEdit($other_memo)) {
            abort(403);
        }
        $other_memo->load(['creator', 'memoTypeDefinition']);

        return view('other-memos.edit', array_merge(
            ['memo' => $other_memo],
            $this->approverFormSharedData($other_memo)
        ));
    }

    public function update(Request $request, OtherMemo $other_memo): RedirectResponse|JsonResponse
    {
        if (! $this->canEdit($other_memo)) {
            abort(403);
        }

        return $this->respondOtherMemoForm($request, function () use ($request, $other_memo) {
            $schema = $other_memo->fields_schema_snapshot ?? [];
            $payload = $this->validatePayloadForSchema($request, $schema);
            $approvers = $this->normalizeApprovers($request);

            $def = MemoTypeDefinition::query()->where('slug', $other_memo->memo_type_slug)->first();
            $attachEnabledLive = (bool) ($def->attachments_enabled ?? false);

            $attachmentForDb = $other_memo->attachment ?? [];
            if ($this->otherMemoAttachmentsFormRelevant($other_memo)) {
                $this->validateOtherMemoAttachmentMultipartRules($request);
                $attachmentForDb = $this->mergeOtherMemoAttachmentsFromRequest($request, $other_memo);
            }
            $this->assertUploadMemoStoredAttachmentsAreSinglePdf($attachmentForDb, (string) $other_memo->memo_type_slug);

            $definitionForRefs = $def ?? MemoTypeDefinition::query()->where('slug', $other_memo->memo_type_slug)->first();
            if (! $definitionForRefs) {
                $definitionForRefs = new MemoTypeDefinition([
                    'slug' => $other_memo->memo_type_slug,
                    'referenced_memos_max' => (int) ($other_memo->referenced_memos_max_snapshot ?? 0),
                ]);
            }

            $other_memo->update(array_merge([
                'payload' => $payload,
                'approvers_config' => $approvers,
                'attachments_enabled_snapshot' => $attachEnabledLive,
                'attachment' => $attachmentForDb,
            ], $this->ccAttributesFromRequest($request, $def ?? $this->definitionForNumbering($other_memo)),
                $this->referencedMemosAttributesFromRequest($request, $definitionForRefs, $other_memo)));

            return $other_memo;
        }, 'Memo updated.', 'other-memos.show');
    }

    public function destroy(OtherMemo $other_memo): RedirectResponse
    {
        if ($other_memo->staff_id !== $this->staffId() || $other_memo->overall_status !== OtherMemo::STATUS_DRAFT) {
            abort(403);
        }
        $other_memo->approvalTrails()->delete();
        $other_memo->delete();

        return redirect()->route('other-memos.index')
            ->with('msg', 'Draft deleted.')
            ->with('type', 'success');
    }

    public function submit(Request $request, OtherMemo $other_memo): RedirectResponse
    {
        if (! $this->canSubmit($other_memo)) {
            abort(403);
        }

        $schema = $other_memo->fields_schema_snapshot ?? [];
        if ($request->boolean('use_stored_memo_content')) {
            $payload = $other_memo->payload ?? [];
            $approvers = $other_memo->approvers_config ?? [];
            $this->assertUploadMemoStoredAttachmentsAreSinglePdf((array) ($other_memo->attachment ?? []), (string) $other_memo->memo_type_slug);
        } else {
            $payload = $this->validatePayloadForSchema($request, $schema);
            $approvers = $this->normalizeApprovers($request);
            if ($this->otherMemoAttachmentsFormRelevant($other_memo)) {
                $this->validateOtherMemoAttachmentMultipartRules($request);
                $other_memo->attachment = $this->mergeOtherMemoAttachmentsFromRequest($request, $other_memo);
            }
            $this->assertUploadMemoStoredAttachmentsAreSinglePdf((array) ($other_memo->attachment ?? []), (string) $other_memo->memo_type_slug);
            $defSnap = MemoTypeDefinition::query()->where('slug', $other_memo->memo_type_slug)->first();
            $other_memo->attachments_enabled_snapshot = (bool) ($defSnap->attachments_enabled ?? false);
            if ($defSnap) {
                foreach ($this->ccAttributesFromRequest($request, $defSnap) as $key => $value) {
                    $other_memo->{$key} = $value;
                }
            }
        }

        if (count($approvers) < 1) {
            return back()->withInput()->with('msg', 'Add at least one approver in sequence.')->with('type', 'danger');
        }

        $other_memo->payload = $payload;
        $other_memo->approvers_config = $approvers;

        if (! $request->boolean('use_stored_memo_content')) {
            $defForRefs = MemoTypeDefinition::query()->where('slug', $other_memo->memo_type_slug)->first();
            if ($defForRefs) {
                foreach ($this->referencedMemosAttributesFromRequest($request, $defForRefs, $other_memo) as $key => $value) {
                    $other_memo->{$key} = $value;
                }
            }
        }

        $isResubmit = $other_memo->overall_status === OtherMemo::STATUS_RETURNED
            && $other_memo->returned_at_sequence !== null;

        if (! $other_memo->document_number) {
            $def = $this->definitionForNumbering($other_memo);
            if ($def->is_division_specific) {
                if (! $other_memo->division_id) {
                    $other_memo->division_id = user_session('division_id');
                }
                $divCode = $this->resolveDivisionCodeForMemo($other_memo);
                if ($divCode === null || $divCode === '') {
                    return back()->withInput()
                        ->with('msg', 'This memo type is division-specific. Your profile must have a division with a short name configured.')
                        ->with('type', 'danger');
                }
            }
            $other_memo->is_division_specific_snapshot = (bool) $def->is_division_specific;
            $other_memo->division_code_snapshot = $def->is_division_specific
                ? $this->resolveDivisionCodeForMemo($other_memo)
                : null;
            $other_memo->document_number = $this->nextDocumentNumber($other_memo, $def);
        }

        if ($isResubmit) {
            $seq = (int) $other_memo->returned_at_sequence;
            $other_memo->overall_status = OtherMemo::STATUS_PENDING;
            $other_memo->active_sequence = $seq;
            $row = $other_memo->approverAtSequence($seq);
            $other_memo->current_approver_staff_id = $row['staff_id'] ?? null;
            $other_memo->returned_at_sequence = null;
            $other_memo->submitted_at = now();

            OtherMemoApprovalTrail::create([
                'other_memo_id' => $other_memo->id,
                'approval_order' => 0,
                'staff_id' => $this->staffId(),
                'action' => 'resubmitted',
                'remarks' => $request->input('submission_remarks'),
            ]);
        } else {
            $other_memo->overall_status = OtherMemo::STATUS_PENDING;
            $other_memo->active_sequence = 1;
            $row = $other_memo->approverAtSequence(1);
            $other_memo->current_approver_staff_id = $row['staff_id'] ?? null;
            $other_memo->submitted_at = now();

            OtherMemoApprovalTrail::create([
                'other_memo_id' => $other_memo->id,
                'approval_order' => 0,
                'staff_id' => $this->staffId(),
                'action' => 'submitted',
                'remarks' => $request->input('submission_remarks'),
            ]);
        }

        $other_memo->save();

        OtherMemoApproverNotifier::notifyCurrentApprover($other_memo->current_approver_staff_id, $other_memo->fresh());

        return redirect()->route('other-memos.show', $other_memo)
            ->with('msg', $isResubmit ? 'Resubmitted for approval.' : 'Submitted for approval.')
            ->with('type', 'success');
    }

    public function approve(Request $request, OtherMemo $other_memo): RedirectResponse
    {
        if (! $this->canApproveOrReturn($other_memo)) {
            abort(403);
        }

        $request->validate(['remarks' => 'nullable|string|max:5000']);

        $seq = (int) $other_memo->active_sequence;
        $trail = OtherMemoApprovalTrail::create([
            'other_memo_id' => $other_memo->id,
            'approval_order' => $seq,
            'staff_id' => $this->staffId(),
            'action' => 'approved',
            'remarks' => $request->input('remarks'),
        ]);

        RecordOtherMemoApproverDocumentTimingJob::dispatch($trail->id)->afterCommit();

        $total = $other_memo->approversCount();
        if ($seq >= $total) {
            $other_memo->overall_status = OtherMemo::STATUS_APPROVED;
            $other_memo->active_sequence = null;
            $other_memo->current_approver_staff_id = null;
            $other_memo->approved_at = now();
        } else {
            $other_memo->active_sequence = $seq + 1;
            $next = $other_memo->approverAtSequence($other_memo->active_sequence);
            $other_memo->current_approver_staff_id = $next['staff_id'] ?? null;
        }

        $other_memo->save();

        if ($other_memo->overall_status === OtherMemo::STATUS_PENDING && $other_memo->current_approver_staff_id) {
            OtherMemoApproverNotifier::notifyCurrentApprover(
                (int) $other_memo->current_approver_staff_id,
                $other_memo->fresh()
            );
        }

        return redirect()->route('other-memos.show', $other_memo)
            ->with('msg', 'Approval recorded.')
            ->with('type', 'success');
    }

    public function returnMemo(Request $request, OtherMemo $other_memo): RedirectResponse
    {
        if (! $this->canApproveOrReturn($other_memo)) {
            abort(403);
        }

        $request->validate(['remarks' => 'required|string|max:5000']);

        $seq = (int) $other_memo->active_sequence;
        OtherMemoApprovalTrail::create([
            'other_memo_id' => $other_memo->id,
            'approval_order' => $seq,
            'staff_id' => $this->staffId(),
            'action' => 'returned',
            'remarks' => $request->input('remarks'),
        ]);

        $other_memo->overall_status = OtherMemo::STATUS_RETURNED;
        $other_memo->returned_at_sequence = $seq;
        $other_memo->current_approver_staff_id = $other_memo->staff_id;
        $other_memo->active_sequence = null;
        $other_memo->save();

        return redirect()->route('other-memos.show', $other_memo)
            ->with('msg', 'Memo returned to the creator for revision.')
            ->with('type', 'warning');
    }

    public function print(OtherMemo $other_memo): Response
    {
        if (! $this->canPrint($other_memo)) {
            abort(403);
        }

        [$pdf, $filename] = $this->buildOtherMemoPdfForOutput($other_memo);

        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Email the other memo PDF to the logged-in user's own address with an optional HTML message.
     */
    public function emailPdf(Request $request, OtherMemo $other_memo): RedirectResponse
    {
        if (! $this->canPrint($other_memo)) {
            abort(403);
        }

        $allowed = staff_pdf_mail_allowed_recipient_emails_normalized();
        if ($allowed === []) {
            return redirect()->route('other-memos.show', $other_memo)
                ->with('msg', 'No valid email is on file for your account. Update your work email in staff records, then try again.')
                ->with('type', 'warning');
        }

        $validated = $request->validate([
            'recipient_email' => 'required|email|max:255',
        ]);

        $norm = strtolower(trim($validated['recipient_email']));
        if (! in_array($norm, $allowed, true)) {
            throw ValidationException::withMessages([
                'recipient_email' => 'You can only send this PDF to your own email address.',
            ]);
        }

        try {
            [$pdf, $filename] = $this->buildOtherMemoPdfForOutput($other_memo);
            $binary = $pdf->Output($filename, 'S');
            if (! is_string($binary) || $binary === '') {
                throw new \RuntimeException('PDF generation returned empty output.');
            }

            $prefix = env('MAIL_SUBJECT_PREFIX', 'APM');
            $doc = $other_memo->document_number ?? ('Other-memo-'.$other_memo->id);
            $subject = "{$prefix}: {$doc} (PDF)";

            $relative = 'tmp/email-pdf/'.Str::uuid()->toString().'.pdf';
            Storage::disk('local')->makeDirectory('tmp/email-pdf');
            if (! Storage::disk('local')->put($relative, $binary)) {
                throw new \RuntimeException('Could not store the PDF for emailing.');
            }
            $absolutePath = Storage::disk('local')->path($relative);

            try {
                SendDocumentPdfEmailJob::dispatch(
                    $validated['recipient_email'],
                    $subject,
                    $filename,
                    $absolutePath
                )->afterResponse();
            } catch (\Throwable $dispatchError) {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
                throw $dispatchError;
            }
        } catch (\Throwable $e) {
            Log::error('Other memo email PDF queue failed', [
                'other_memo_id' => $other_memo->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('other-memos.show', $other_memo)
                ->with('msg', 'Could not queue the email: '.$e->getMessage())
                ->with('type', 'danger');
        }

        return redirect()->route('other-memos.show', $other_memo)
            ->with('msg', 'Your PDF email has been queued. It should arrive shortly; if not, confirm email settings and that a queue worker is processing jobs.')
            ->with('type', 'success');
    }

    /**
     * @return array{0: object, 1: string}
     */
    private function buildOtherMemoPdfForOutput(OtherMemo $other_memo): array
    {
        $other_memo->load(['approvalTrails.staff', 'creator', 'division']);

        $attachments = is_array($other_memo->attachment) ? $other_memo->attachment : [];

        $pdf = mpdf_print('other-memos.pdf', [
            'memo' => $other_memo,
        ], [
            'preview_html' => false,
            'document_url' => route('other-memos.print', $other_memo, true),
            'attachments_appendix' => $attachments,
        ]);

        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $other_memo->document_number ?? 'memo').'.pdf';

        return [$pdf, $safe];
    }

    private function staffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    private function resolveDefinition(Request $request): MemoTypeDefinition
    {
        $slug = $request->string('memo_type_slug')->trim();
        if ($slug === '') {
            throw ValidationException::withMessages([
                'memo_type_slug' => 'Choose a memo type.',
            ]);
        }
        $def = MemoTypeDefinition::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $def) {
            throw ValidationException::withMessages([
                'memo_type_slug' => 'Invalid or inactive memo type.',
            ]);
        }

        return $def;
    }

    private function validatePayloadForSchema(Request $request, array $schema): array
    {
        $schema = MemoTypeDefinition::normalizeFieldsSchemaRows($schema);
        $out = [];
        foreach ($schema as $field) {
            $key = $field['field'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $enabled = ! array_key_exists('enabled', $field) || filter_var($field['enabled'], FILTER_VALIDATE_BOOLEAN);
            if (! $enabled) {
                continue;
            }
            $type = $field['field_type'] ?? 'text';
            $required = ! empty($field['required']);
            $raw = $request->input('payload.'.$key);

            if ($required && ($raw === null || $raw === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payload.'.$key => 'The '.($field['display'] ?? $key).' field is required.',
                ]);
            }

            if ($raw === null) {
                $out[$key] = null;

                continue;
            }

            $out[$key] = match ($type) {
                'number' => $raw === '' ? null : (float) $raw,
                'email' => $raw === '' ? null : (string) $raw,
                'date' => $raw === '' ? null : (string) $raw,
                'text_summernote', 'textarea' => (string) $raw,
                default => is_array($raw) ? $raw : (string) $raw,
            };
        }

        return $out;
    }

    /**
     * @return array{cc_on_approval_enabled_snapshot: bool, cc_config: array<string, mixed>|null}
     */
    private function ccAttributesFromRequest(Request $request, MemoTypeDefinition $definition): array
    {
        return OtherMemoCc::attributesFromRequest($request, $definition);
    }

    private function normalizeApprovers(Request $request): array
    {
        $raw = $request->input('approvers', []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        $seq = 1;
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sid = (int) ($row['staff_id'] ?? 0);
            $role = trim((string) ($row['role_label'] ?? ''));
            if ($sid > 0) {
                $section = strtolower(trim((string) ($row['memo_section'] ?? 'through')));
                if (! in_array($section, ['to', 'through', 'from'], true)) {
                    $section = 'through';
                }
                $out[] = [
                    'sequence' => $seq++,
                    'staff_id' => $sid,
                    'role_label' => $role !== '' ? $role : 'Approver',
                    'memo_section' => $section,
                ];
            }
        }

        return \App\Helpers\PrintHelper::applyOtherMemoDefaultSections($out);
    }

    private function isUploadMemoSlug(string $slug): bool
    {
        return strtolower(trim($slug)) === 'upload';
    }

    private function assertUploadMemoPdfRulesOnStore(Request $request, MemoTypeDefinition $definition): void
    {
        if (! $this->isUploadMemoSlug((string) $definition->slug)) {
            return;
        }

        $request->validate([
            'attachments' => 'required|array|size:1',
            'attachments.0.file' => 'required|file|mimes:pdf|max:10240',
            'approvers' => 'required|array|min:1',
        ], [
            'attachments.required' => 'Upload memo requires one PDF document.',
            'attachments.size' => 'Upload memo allows exactly one PDF document.',
            'attachments.0.file.mimes' => 'Upload memo allows PDF files only.',
            'attachments.0.file.required' => 'Upload memo requires one PDF document.',
        ]);
    }

    /**
     * @param array<int, array<string,mixed>> $attachments
     */
    private function assertUploadMemoStoredAttachmentsAreSinglePdf(array $attachments, string $memoTypeSlug): void
    {
        if (! $this->isUploadMemoSlug($memoTypeSlug)) {
            return;
        }

        if (count($attachments) !== 1) {
            throw ValidationException::withMessages([
                'attachments' => 'Upload memo must keep exactly one PDF document.',
            ]);
        }

        $row = $attachments[0] ?? [];
        $name = (string) ($row['original_name'] ?? $row['filename'] ?? '');
        $path = (string) ($row['path'] ?? '');
        $ext = strtolower(pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw ValidationException::withMessages([
                'attachments' => 'Upload memo supports PDF files only.',
            ]);
        }
    }

    private function definitionForNumbering(OtherMemo $memo): MemoTypeDefinition
    {
        $def = MemoTypeDefinition::query()->where('slug', $memo->memo_type_slug)->first();
        if ($def) {
            return $def;
        }

        $fallback = new MemoTypeDefinition;
        $fallback->ref_prefix = $memo->ref_prefix_snapshot;
        $fallback->is_division_specific = (bool) ($memo->is_division_specific_snapshot ?? false);

        return $fallback;
    }

    private function sanitizeRefToken(?string $ref): string
    {
        $t = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $ref));

        return $t !== '' ? $t : 'MEMO';
    }

    private function resolveDivisionCodeForMemo(OtherMemo $memo): ?string
    {
        $id = (int) ($memo->division_id ?? 0);
        if ($id <= 0) {
            return null;
        }
        $div = Division::query()->find($id);
        if (! $div) {
            return null;
        }
        $code = trim((string) ($div->division_short_name ?? ''));
        if ($code === '') {
            return null;
        }
        $sanitized = strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '', $code));

        return $sanitized !== '' ? $sanitized : null;
    }

    private function nextDocumentNumber(OtherMemo $memo, MemoTypeDefinition $def): string
    {
        $org = trim((string) config('memo.ref_org_path', 'AU/CDC'), '/');
        $im = trim((string) config('memo.internal_memo_segment', 'IM'), '/');
        $refToken = $this->sanitizeRefToken($def->ref_prefix ?? $memo->ref_prefix_snapshot);
        $yy = now()->format('y');

        if ($def->is_division_specific) {
            $divCode = $this->resolveDivisionCodeForMemo($memo);
            if ($divCode === null || $divCode === '') {
                throw ValidationException::withMessages([
                    'submission_remarks' => 'Division short name could not be resolved for this memo reference.',
                ]);
            }
            $pathPrefix = $org.'/'.$divCode.'/'.$im.'/'.$refToken.'/'.$yy.'/';
        } else {
            $pathPrefix = $org.'/'.$im.'/'.$refToken.'/'.$yy.'/';
        }

        $last = OtherMemo::query()
            ->where('memo_type_slug', $memo->memo_type_slug)
            ->whereNotNull('document_number')
            ->where('document_number', 'like', $pathPrefix.'%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $next = 1;
        if ($last) {
            $trim = rtrim((string) $last, '/');
            $parts = explode('/', $trim);
            $lastSeg = end($parts);
            if (is_string($lastSeg) && ctype_digit($lastSeg)) {
                $next = (int) $lastSeg + 1;
            }
        }

        return $pathPrefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function otherMemoAttachmentsFormRelevant(OtherMemo $memo): bool
    {
        $def = MemoTypeDefinition::query()->where('slug', $memo->memo_type_slug)->first();
        if ($def && $def->attachments_enabled) {
            return true;
        }
        if ($memo->attachments_enabled_snapshot) {
            return true;
        }
        $att = $memo->attachment;
        if (is_array($att) && count($att) > 0) {
            return true;
        }

        return false;
    }

    private function validateOtherMemoAttachmentMultipartRules(Request $request): void
    {
        $request->validate([
            'attachments' => 'sometimes|array',
            'attachments.*.type' => 'nullable|string|max:255',
            'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
        ]);
    }

    private function assertNoAttachmentsWhenDisabled(Request $request, bool $enabled): void
    {
        if ($enabled || ! $this->requestHasAnyOtherMemoAttachmentFiles($request)) {
            return;
        }

        throw ValidationException::withMessages([
            'attachments' => 'Attachments are not enabled for this memo type.',
        ]);
    }

    private function requestHasAnyOtherMemoAttachmentFiles(Request $request): bool
    {
        $attachments = $request->file('attachments');
        if ($attachments === null) {
            return false;
        }
        if (! is_array($attachments)) {
            return $attachments->isValid();
        }
        foreach ($attachments as $item) {
            if ($item instanceof \Illuminate\Http\UploadedFile && $item->isValid()) {
                return true;
            }
            if (is_array($item)) {
                foreach ($item as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectUploadedOtherMemoAttachmentsOnCreate(Request $request, MemoTypeDefinition $definition): array
    {
        return \App\Support\OtherMemoAttachments::collectFromCreateRequest(
            $request,
            (bool) $definition->attachments_enabled
        );
    }

    private function assertAttachmentsPresentWhenRequired(MemoTypeDefinition $definition, array $attachmentRows): void
    {
        if (! $definition->attachments_enabled || $this->isUploadMemoSlug((string) $definition->slug)) {
            return;
        }
        if (count($attachmentRows) < 1) {
            throw ValidationException::withMessages([
                'attachments' => 'Please attach at least one file for this memo type.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storeOtherMemoAttachmentFile(\Illuminate\Http\UploadedFile $file): array
    {
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Invalid file type. Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, and DOCX files are allowed.',
            ]);
        }

        return \App\Support\OtherMemoAttachments::fileMetaFromUpload($file);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mergeOtherMemoAttachmentsFromRequest(Request $request, OtherMemo $memo): array
    {
        $existingAttachments = \App\Support\OtherMemoAttachments::normalizeStored($memo->attachment);
        $attachmentData = $request->input('attachments', []);
        if (! is_array($attachmentData)) {
            $attachmentData = [];
        }

        if ($attachmentData === []) {
            return $existingAttachments;
        }

        $uploadedByIndex = \App\Support\OtherMemoAttachments::extractUploadedFiles($request);

        $attachments = [];
        foreach ($attachmentData as $index => $attachmentInfo) {
            if (! is_array($attachmentInfo)) {
                continue;
            }
            $type = $attachmentInfo['type'] ?? 'Document';
            if (! is_string($type)) {
                $type = 'Document';
            }
            $file = $uploadedByIndex[(int) $index] ?? null;
            $shouldReplace = isset($attachmentInfo['replace']) && $attachmentInfo['replace'] == '1';
            $shouldDelete = isset($attachmentInfo['delete']) && $attachmentInfo['delete'] == '1';

            if ($shouldDelete) {
                continue;
            }

            if ($file && $file->isValid()) {
                $meta = $this->storeOtherMemoAttachmentFile($file);
                $meta['type'] = $type;
                $attachments[] = $meta;
            } elseif ($shouldReplace && isset($existingAttachments[$index])) {
                continue;
            } elseif (isset($existingAttachments[$index])) {
                $row = $existingAttachments[$index];
                $row['type'] = $type;
                $attachments[] = $row;
            }
        }

        return $attachments;
    }

    /**
     * Workflow id 1 “Role” column values (enabled definitions first), for approver label hints.
     *
     * @return array<int, string>
     */
    private function workflowOneRoleExamples(): array
    {
        $q = WorkflowDefinition::query()
            ->where('workflow_id', 1)
            ->orderBy('approval_order');

        $enabled = (clone $q)->where('is_enabled', 1)
            ->pluck('role')
            ->map(fn ($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($enabled !== []) {
            return $enabled;
        }

        return $q->pluck('role')
            ->map(fn ($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{staffOptions: \Illuminate\Support\Collection, roleExamples: array<int, string>}
     */
    private function approverFormSharedData(?OtherMemo $memo = null): array
    {
        $staffOptions = Staff::query()
            ->where('active', 1)
            ->orderBy('lname')
            ->orderBy('fname')
            ->get(['staff_id', 'title', 'fname', 'lname', 'job_name']);

        if ($memo) {
            $needed = collect($memo->approvers_config ?? [])
                ->pluck('staff_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique();
            $have = $staffOptions->pluck('staff_id')->map(fn ($v) => (int) $v);
            $missingIds = $needed->diff($have);
            if ($missingIds->isNotEmpty()) {
                $extra = Staff::query()
                    ->whereIn('staff_id', $missingIds->all())
                    ->orderBy('lname')
                    ->orderBy('fname')
                    ->get(['staff_id', 'title', 'fname', 'lname', 'job_name']);
                $staffOptions = $staffOptions->concat($extra)->unique('staff_id')->sortBy(['lname', 'fname'])->values();
            }
        }

        $activeMemoTypes = MemoTypeDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $ccEnabledTypes = $activeMemoTypes->where('cc_on_approval_enabled', true);

        return [
            'staffOptions' => $staffOptions,
            'roleExamples' => $this->workflowOneRoleExamples(),
            'ccEnabledTypeSlugs' => $ccEnabledTypes->pluck('slug')->values()->all(),
            'ccEnabledTypeNames' => $ccEnabledTypes->pluck('name', 'slug')->all(),
            'memoTypeCcBySlug' => $activeMemoTypes->mapWithKeys(
                fn (MemoTypeDefinition $m) => [$m->slug => (bool) $m->cc_on_approval_enabled]
            )->all(),
            'memoTypeReferencedMaxBySlug' => $activeMemoTypes->mapWithKeys(
                fn (MemoTypeDefinition $m) => [$m->slug => max(0, min(10, (int) ($m->referenced_memos_max ?? 0)))]
            )->all(),
            'memoTypesForCreate' => $activeMemoTypes
                ->map(fn (MemoTypeDefinition $m) => $m->toApiArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  callable(): OtherMemo  $action
     */
    private function respondOtherMemoForm(Request $request, callable $action, string $successMessage, string $showRouteName): RedirectResponse|JsonResponse
    {
        try {
            $memo = $action();
        } catch (ValidationException $e) {
            if ($this->otherMemoFormWantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->firstValidationMessage($e),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        if ($this->otherMemoFormWantsJson($request)) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'redirect_url' => route($showRouteName, $memo),
            ]);
        }

        return redirect()->route($showRouteName, $memo)
            ->with('msg', $successMessage)
            ->with('type', 'success');
    }

    private function otherMemoFormWantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->header('X-APM-Ajax-Form') === '1';
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0]) && $messages[0] !== '') {
                return $messages[0];
            }
        }

        return 'Please correct the errors below.';
    }

    /**
     * @return array{referenced_memos_max_snapshot: int, referenced_memos: ?array}
     */
    private function referencedMemosAttributesFromRequest(Request $request, MemoTypeDefinition $definition, ?OtherMemo $excludeMemo = null): array
    {
        $max = max(0, min(10, (int) ($definition->referenced_memos_max ?? 0)));
        $links = $request->input('referenced_memo_links', []);
        if (! is_array($links)) {
            $links = [];
        }

        $resolved = app(ApprovedMemoReferenceResolver::class)->resolveMany(
            $links,
            $max,
            $excludeMemo?->id
        );

        return [
            'referenced_memos_max_snapshot' => $max,
            'referenced_memos' => $resolved === [] ? null : $resolved,
        ];
    }

    private function authorizeView(OtherMemo $memo): void
    {
        if (! $this->canViewOtherMemo($memo)) {
            abort(403);
        }
    }

    /**
     * View access: creator, approvers, system admin, all-memos permission (87), or same division.
     * Does not grant approve/edit rights — those use canApproveOrReturn / canEdit.
     */
    private function canViewOtherMemo(OtherMemo $memo): bool
    {
        $sid = $this->staffId();
        if ($sid <= 0) {
            return false;
        }

        if ((int) $memo->staff_id === $sid) {
            return true;
        }

        if ($memo->overall_status === OtherMemo::STATUS_PENDING
            && (int) $memo->current_approver_staff_id === $sid) {
            return true;
        }

        $approverIds = collect($memo->approvers_config ?? [])
            ->pluck('staff_id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->all();
        if (in_array($sid, $approverIds, true)) {
            return true;
        }

        if ($this->isOtherMemoSystemAdmin()) {
            return true;
        }

        if (in_array(87, user_session('permissions', []), true)) {
            return true;
        }

        $userDivisionId = (int) (user_session('division_id') ?? 0);
        $memoDivisionId = (int) ($memo->division_id ?? 0);

        return $userDivisionId > 0 && $memoDivisionId > 0 && $userDivisionId === $memoDivisionId;
    }

    private function isOtherMemoSystemAdmin(): bool
    {
        return (int) (user_session('role') ?? 0) === 10;
    }

    private function canEdit(OtherMemo $memo): bool
    {
        if ($memo->staff_id !== $this->staffId()) {
            return false;
        }

        return in_array($memo->overall_status, [OtherMemo::STATUS_DRAFT, OtherMemo::STATUS_RETURNED], true);
    }

    private function canSubmit(OtherMemo $memo): bool
    {
        return $this->canEdit($memo);
    }

    private function canApproveOrReturn(OtherMemo $memo): bool
    {
        if ($memo->overall_status !== OtherMemo::STATUS_PENDING) {
            return false;
        }

        return (int) $memo->current_approver_staff_id === $this->staffId();
    }

    private function canPrint(OtherMemo $memo): bool
    {
        return $memo->overall_status === OtherMemo::STATUS_APPROVED
            && $this->canViewOtherMemo($memo);
    }

    private function memoAttachmentAtIndex(OtherMemo $memo, int $index): array
    {
        $rows = $memo->attachmentsList();
        if ($index < 0 || ! isset($rows[$index])) {
            abort(404, 'Attachment not found.');
        }

        return $rows[$index];
    }

    public function archive(OtherMemo $other_memo): RedirectResponse
    {
        if (! function_exists('can_archive_memo') || ! can_archive_memo($other_memo)) {
            return redirect()->back()
                ->with('error', 'You are not allowed to archive this memo.');
        }

        $other_memo->previous_overall_status = $this->determineOtherMemoStatusBeforeArchive($other_memo);
        $other_memo->overall_status = 'archived';
        $other_memo->save();

        return redirect()->back()
            ->with('success', 'Other memo archived successfully.');
    }

    public function unarchive(OtherMemo $other_memo): RedirectResponse
    {
        $user = session('user', []);
        $userRole = $user['role'] ?? $user['user_role'] ?? null;
        $isAdmin = ((int) $userRole) === 10;

        if (! $isAdmin) {
            return redirect()->route('other-memos.show', $other_memo)
                ->with('error', 'Only system administrators can unarchive this memo.');
        }

        if (($other_memo->overall_status ?? '') !== 'archived') {
            return redirect()->route('other-memos.show', $other_memo)
                ->with('error', 'This memo is not archived.');
        }

        $other_memo->overall_status = $other_memo->previous_overall_status ?: OtherMemo::STATUS_RETURNED;
        $other_memo->previous_overall_status = null;
        $other_memo->save();

        return redirect()->route('other-memos.show', $other_memo)
            ->with('success', 'Other memo unarchived successfully.');
    }

    private function determineOtherMemoStatusBeforeArchive(OtherMemo $memo): string
    {
        $current = (string) ($memo->overall_status ?? OtherMemo::STATUS_RETURNED);
        if ($current === OtherMemo::STATUS_APPROVED) {
            return OtherMemo::STATUS_APPROVED;
        }

        $maxSequence = collect($memo->approvers_config ?? [])
            ->pluck('sequence')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->max() ?: 0;
        if ($maxSequence <= 0) {
            return $current;
        }

        $finalApproved = OtherMemoApprovalTrail::query()
            ->where('other_memo_id', $memo->id)
            ->where('approval_order', $maxSequence)
            ->where('action', 'approved')
            ->exists();

        return $finalApproved ? OtherMemo::STATUS_APPROVED : $current;
    }
}
