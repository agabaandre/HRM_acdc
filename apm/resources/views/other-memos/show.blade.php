@extends('layouts.app')

@section('title', $memo->document_number ?? 'Other memo')

@section('header')
    {{ $memo->memo_type_name_snapshot }}
    @if ($memo->document_number)
        <small class="text-muted ms-2"><code>{{ $memo->document_number }}</code></small>
    @endif
@endsection

@section('header-actions')
    <div class="d-flex flex-wrap gap-2">
        @if ($canPrint)
            <a href="{{ route('other-memos.print', $memo) }}" class="btn btn-primary" target="_blank">
                <i class="bx bx-printer me-1"></i>Print (PDF)
            </a>
        @endif
        @if ($canEdit)
            <a href="{{ route('other-memos.edit', $memo) }}" class="btn btn-outline-primary" wire:navigate>
                <i class="bx bx-edit-alt me-1"></i>Edit @if($memo->overall_status === 'returned')& resubmit @endif
            </a>
        @endif
        @if ($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
            <form method="post" action="{{ route('other-memos.destroy', $memo) }}" class="d-inline" onsubmit="return confirm('Delete this draft?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"><i class="bx bx-trash"></i> Delete draft</button>
            </form>
        @endif
        <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>Back to list</a>
    </div>
@endsection

@section('content')
    @if (session('msg'))
        <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header">Content</div>
                <div class="card-body">
                    @include('other-memos.partials.dynamic-fields', [
                        'schema' => $memo->fields_schema_snapshot ?? [],
                        'values' => $memo->payload ?? [],
                        'readonly' => true,
                    ])
                </div>
            </div>

            <div class="mb-3">
                @include('matrices.partials.approval-trail', ['trails' => $memo->approvalTrails])
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header">Status</div>
                <div class="card-body small">
                    <p><strong>Status:</strong>
                        @if ($memo->overall_status === 'approved')
                            <span class="badge bg-success">Approved</span>
                        @elseif($memo->overall_status === 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($memo->overall_status === 'returned')
                            <span class="badge bg-secondary">Returned to creator</span>
                        @elseif($memo->overall_status === 'draft')
                            <span class="badge bg-light text-dark">Draft</span>
                        @else
                            {{ $memo->overall_status }}
                        @endif
                    </p>
                    <p><strong>Signature layout (catalogue):</strong> {{ str_replace('_', ' ', $memo->signature_style_snapshot ?? '—') }}</p>
                    <p><strong>Creator:</strong> {{ $memo->creator->fname ?? '' }} {{ $memo->creator->lname ?? '' }}</p>
                    @if ($memo->overall_status === 'pending' && $memo->currentApprover)
                        <p class="mb-0"><strong>Awaiting:</strong> {{ $memo->currentApprover->fname }} {{ $memo->currentApprover->lname }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header">Approver chain</div>
                <div class="card-body small">
                    <ol class="mb-0 ps-3">
                        @foreach ($memo->approvers_config ?? [] as $row)
                            <li>
                                Step {{ $row['sequence'] ?? '?' }}:
                                @php $st = \App\Models\Staff::where('staff_id', $row['staff_id'] ?? 0)->first(); @endphp
                                {{ $st ? trim(($st->fname ?? '') . ' ' . ($st->lname ?? '')) : 'Staff #' . ($row['staff_id'] ?? '') }}
                                <span class="text-muted">({{ $row['role_label'] ?? 'Approver' }})</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            @if ($canSubmit)
                <div class="card shadow-sm border-success mb-3">
                    <div class="card-header bg-success bg-opacity-10">Submit for approval</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Sends this memo into the approval sequence. The first approver is notified immediately; each approval notifies the next person in line.
                        </p>
                        <form method="post" action="{{ route('other-memos.submit', $memo) }}" id="other-memo-show-submit-form">
                            @csrf
                            <input type="hidden" name="use_stored_memo_content" value="1">
                            <label class="form-label small">Notes to approvers (optional)</label>
                            <textarea name="submission_remarks" class="form-control form-control-sm mb-2" rows="2" placeholder="Optional message recorded on the approval trail">{{ old('submission_remarks') }}</textarea>
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#otherMemoSubmitConfirmModal">
                                <i class="bx bx-send"></i> Submit for approval
                            </button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">
                            To change fields or approvers before submitting, use <a href="{{ route('other-memos.edit', $memo) }}" wire:navigate>Edit</a>.
                        </p>
                    </div>
                </div>
            @endif

            @if ($canApproveOrReturn)
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning bg-opacity-25">Your action</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('other-memos.approve', $memo) }}" class="mb-3">
                            @csrf
                            <label class="form-label small">Remarks (optional)</label>
                            <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2"></textarea>
                            <button type="submit" class="btn btn-success w-100"><i class="bx bx-check"></i> Approve</button>
                        </form>
                        <form method="post" action="{{ route('other-memos.return-memo', $memo) }}">
                            @csrf
                            <label class="form-label small">Return to creator — remarks required</label>
                            <textarea name="remarks" class="form-control form-control-sm mb-2" rows="3" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bx bx-revision"></i> Return for revision</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($canSubmit)
        <div class="modal fade" id="otherMemoSubmitConfirmModal" tabindex="-1" aria-labelledby="otherMemoSubmitConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="otherMemoSubmitConfirmModalLabel">Submit for approval?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">This will send the memo into the approval sequence using the <strong>saved</strong> content and approver list.</p>
                        <p class="small text-muted mb-0 mt-2">Use <a href="{{ route('other-memos.edit', $memo) }}" wire:navigate>Edit</a> first if you need to change fields or approvers.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="otherMemoSubmitConfirmBtn">
                            <i class="bx bx-send"></i> Yes, submit for approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@if ($canSubmit)
<script>
(function() {
    var btn = document.getElementById('otherMemoSubmitConfirmBtn');
    var form = document.getElementById('other-memo-show-submit-form');
    var modalEl = document.getElementById('otherMemoSubmitConfirmModal');
    if (!btn || !form || !modalEl || typeof bootstrap === 'undefined') return;
    btn.addEventListener('click', function() {
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
        form.submit();
    });
})();
</script>
@endif
@endpush
