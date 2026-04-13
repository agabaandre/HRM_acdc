<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\MemoTypeDefinition;
use App\Models\OtherMemo;
use App\Models\OtherMemoApprovalTrail;
use App\Models\Staff;
use App\Models\WorkflowDefinition;
use App\Services\OtherMemoApproverNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtherMemoController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $currentStaffId = $this->staffId();

        $staff = Cache::remember('other_memos_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('other_memos_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $year = $this->resolveOtherMemoYearString($request);

        if ($request->ajax() && $request->filled('tab')) {
            $tab = $request->get('tab', '');
            $yearApplied = $this->resolveOtherMemoYearString($request);

            [$mySubmittedMemos, $allMemos] = $this->paginateOtherMemoTabs($request, $currentStaffId);

            $countMySubmitted = $mySubmittedMemos->total();
            $countAllMemos = $allMemos instanceof LengthAwarePaginator ? $allMemos->total() : $allMemos->count();

            $html = match ($tab) {
                'mySubmitted' => view('other-memos.partials.my-submitted-tab', compact('mySubmittedMemos'))->render(),
                'allMemos' => view('other-memos.partials.all-memos-tab', compact('allMemos'))->render(),
                default => '',
            };

            return response()->json([
                'html' => $html,
                'year_applied' => $yearApplied,
                'count_my_submitted' => $countMySubmitted,
                'count_all_memos' => $countAllMemos,
            ]);
        }

        [$mySubmittedMemos, $allMemos] = $this->paginateOtherMemoTabs($request, $currentStaffId);

        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $yearRange = range($currentYear, $minYear);
        $years = ['all' => 'All years'] + array_combine($yearRange, $yearRange);

        return view('other-memos.index', compact(
            'mySubmittedMemos',
            'allMemos',
            'staff',
            'divisions',
            'year',
            'years',
            'currentStaffId',
        ));
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: LengthAwarePaginator|\Illuminate\Support\Collection}
     */
    private function paginateOtherMemoTabs(Request $request, int $currentStaffId): array
    {
        $mySubmittedMemos = $this->buildOtherMemoMySubmittedQuery($request, $currentStaffId)
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

        return [$mySubmittedMemos, $allMemos];
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

    private function applyOtherMemoIndexFilters(Builder $query, Request $request): void
    {
        $year = $this->resolveOtherMemoYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }
        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%' . $request->document_number . '%');
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
        $like = '%' . addcslashes($term, '%_\\') . '%';
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
        $name = trim(($staff->title ? $staff->title . ' ' : '') . $staff->fname . ' ' . $staff->lname);

        return response()->json(['success' => true, 'name' => $name, 'email' => $staff->work_email]);
    }

    public function create(): View
    {
        return view('other-memos.create', $this->approverFormSharedData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $definition = $this->resolveDefinition($request);
        $payload = $this->validatePayloadForSchema($request, $definition->fields_schema ?? []);
        $approvers = $this->normalizeApprovers($request);

        $memo = OtherMemo::create([
            'memo_type_slug' => $definition->slug,
            'memo_type_name_snapshot' => $definition->name,
            'ref_prefix_snapshot' => $definition->ref_prefix,
            'signature_style_snapshot' => $definition->signature_style,
            'fields_schema_snapshot' => MemoTypeDefinition::normalizeFieldsSchemaRows($definition->fields_schema ?? []),
            'payload' => $payload,
            'approvers_config' => $approvers,
            'staff_id' => $this->staffId(),
            'division_id' => user_session('division_id'),
            'overall_status' => OtherMemo::STATUS_DRAFT,
        ]);

        return redirect()->route('other-memos.show', $memo)
            ->with('msg', 'Draft saved.')
            ->with('type', 'success');
    }

    public function show(OtherMemo $other_memo): View
    {
        $this->authorizeView($other_memo);
        $other_memo->load(['approvalTrails.staff', 'approvalTrails.otherMemo', 'creator', 'currentApprover']);

        return view('other-memos.show', [
            'memo' => $other_memo,
            'canEdit' => $this->canEdit($other_memo),
            'canSubmit' => $this->canSubmit($other_memo),
            'canApproveOrReturn' => $this->canApproveOrReturn($other_memo),
            'canPrint' => $this->canPrint($other_memo),
        ]);
    }

    public function edit(OtherMemo $other_memo): View|RedirectResponse
    {
        if (! $this->canEdit($other_memo)) {
            abort(403);
        }
        $other_memo->load(['creator']);

        return view('other-memos.edit', array_merge(
            ['memo' => $other_memo],
            $this->approverFormSharedData($other_memo)
        ));
    }

    public function update(Request $request, OtherMemo $other_memo): RedirectResponse
    {
        if (! $this->canEdit($other_memo)) {
            abort(403);
        }

        $schema = $other_memo->fields_schema_snapshot ?? [];
        $payload = $this->validatePayloadForSchema($request, $schema);
        $approvers = $this->normalizeApprovers($request);

        $other_memo->update([
            'payload' => $payload,
            'approvers_config' => $approvers,
        ]);

        return redirect()->route('other-memos.show', $other_memo)
            ->with('msg', 'Memo updated.')
            ->with('type', 'success');
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
        } else {
            $payload = $this->validatePayloadForSchema($request, $schema);
            $approvers = $this->normalizeApprovers($request);
        }

        if (count($approvers) < 1) {
            return back()->withInput()->with('msg', 'Add at least one approver in sequence.')->with('type', 'danger');
        }

        $other_memo->payload = $payload;
        $other_memo->approvers_config = $approvers;

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
        OtherMemoApprovalTrail::create([
            'other_memo_id' => $other_memo->id,
            'approval_order' => $seq,
            'staff_id' => $this->staffId(),
            'action' => 'approved',
            'remarks' => $request->input('remarks'),
        ]);

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

        $other_memo->load(['approvalTrails.staff', 'creator']);

        $pdf = mpdf_print('other-memos.pdf', [
            'memo' => $other_memo,
        ], ['preview_html' => false]);

        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $other_memo->document_number ?? 'memo');

        return response($pdf->Output($safe . '.pdf', 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safe . '.pdf"',
        ]);
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
            $raw = $request->input('payload.' . $key);

            if ($required && ($raw === null || $raw === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payload.' . $key => 'The ' . ($field['display'] ?? $key) . ' field is required.',
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
                $out[] = [
                    'sequence' => $seq++,
                    'staff_id' => $sid,
                    'role_label' => $role !== '' ? $role : 'Approver',
                ];
            }
        }

        return $out;
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
            $pathPrefix = $org . '/' . $divCode . '/' . $im . '/' . $refToken . '/' . $yy . '/';
        } else {
            $pathPrefix = $org . '/' . $im . '/' . $refToken . '/' . $yy . '/';
        }

        $last = OtherMemo::query()
            ->where('memo_type_slug', $memo->memo_type_slug)
            ->whereNotNull('document_number')
            ->where('document_number', 'like', $pathPrefix . '%')
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

        return $pathPrefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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

        return [
            'staffOptions' => $staffOptions,
            'roleExamples' => $this->workflowOneRoleExamples(),
        ];
    }

    private function authorizeView(OtherMemo $memo): void
    {
        $sid = $this->staffId();
        if ($memo->staff_id === $sid) {
            return;
        }
        if ($memo->overall_status === OtherMemo::STATUS_PENDING && (int) $memo->current_approver_staff_id === $sid) {
            return;
        }
        $ids = collect($memo->approvers_config ?? [])->pluck('staff_id')->map(fn ($v) => (int) $v)->all();
        if (in_array($sid, $ids, true)) {
            return;
        }
        abort(403);
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
            && $memo->staff_id === $this->staffId();
    }
}
