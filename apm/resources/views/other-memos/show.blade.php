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
        @if (!empty($canEmailPdf) && $canEmailPdf)
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#emailPdfModal" title="Email a PDF copy to yourself">
                <i class="bx bx-mail-send me-1"></i>Email PDF
            </button>
            @include('partials.email-pdf-modal', [
                'emailPdfFormAction' => route('other-memos.email-pdf', $memo),
                'emailPdfDocumentLabel' => $memo->document_number ?? $memo->memo_type_name_snapshot,
                'emailPdfRecipientChoices' => $emailPdfRecipientChoices ?? [],
            ])
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
        @if (user_session('role') == 10 && ($memo->overall_status ?? '') !== 'archived')
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#archiveOtherMemoModal">
                <i class="bx bx-archive me-1"></i>Archive
            </button>
        @endif
        <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>Back to list</a>
    </div>
@endsection

@section('content')
<style>
    .other-memo-show-page .summary-table {
        background: white;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .other-memo-show-page .summary-table .table { margin-bottom: 0; }
    .other-memo-show-page .summary-table .table th {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: none;
        font-weight: 600;
        color: #374151;
        padding: 1rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .other-memo-show-page .summary-table .table td {
        border: none;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
        vertical-align: middle;
    }
    .other-memo-show-page .summary-table .table tr:last-child td { border-bottom: none; }
    .other-memo-show-page .summary-table .table tr:hover { background-color: #f9fafb; }
    .other-memo-show-page .field-label {
        font-weight: 600;
        color: #374151;
        min-width: 150px;
        padding: 1rem 1rem 1rem 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-right: 3px solid #e2e8f0;
    }
    .other-memo-show-page .field-label i { font-size: 1.1rem; margin-right: 0.5rem; vertical-align: middle; }
    .other-memo-show-page .field-value { color: #1f2937; font-weight: 500; }
    .other-memo-show-page .field-value.null, .other-memo-show-page .field-value-null { color: #9ca3af; font-style: italic; }
    .other-memo-show-page .content-section {
        border-left: 4px solid #10b981;
        background: #fafafa;
    }
    .other-memo-show-page .content-section .card-body { background: #fff; }
    #otherMemoPreviewModal .modal-dialog {
        max-width: 90vw;
        margin: 1.75rem auto;
    }
    #otherMemoPreviewModal .modal-body {
        min-height: 500px;
        max-height: 80vh;
        overflow: hidden;
    }
    #otherMemoPreviewModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    #otherMemoPreviewModal .btn-close { filter: invert(1); }
    .other-memo-show-page .preview-other-memo-attachment { transition: all 0.2s ease; }
    .other-memo-show-page .preview-other-memo-attachment:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    .other-memo-show-page .text-pre-wrap { white-space: pre-wrap; }
</style>

<div class="other-memo-show-page">
    @if (session('msg'))
        <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
    @endif

    @if (user_session('role') == 10 && ($memo->overall_status ?? '') !== 'archived')
        <div class="modal fade" id="archiveOtherMemoModal" tabindex="-1" aria-labelledby="archiveOtherMemoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="archiveOtherMemoModalLabel">Archive Other Memo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Are you sure you want to archive this memo?</p>
                        <p class="text-muted small mb-0">This sets <code>forward_workflow_id</code> to null and marks the memo as archived.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('other-memos.archive', $memo) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger"><i class="bx bx-archive me-1"></i> Archive</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @php
        $memoAttachmentsShow = is_array($memo->attachment) ? $memo->attachment : [];
    @endphp

    <div class="container-fluid py-2">
        <div class="summary-table mb-4">
            <div class="card-header bg-light border-0 py-3 rounded-top">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-file-blank me-2 text-success"></i>{{ $memo->memo_type_name_snapshot }}
                    @if ($memo->document_number)
                        <span class="text-muted fw-normal">— {{ $memo->document_number }}</span>
                    @else
                        <span class="badge bg-secondary ms-2">Draft</span>
                    @endif
                </h5>
                <p class="text-muted small mb-0 mt-1">Review memo content, attachments, and approval progress.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <tbody>
                        <tr>
                            <td class="field-label"><i class="bx bx-check-circle me-2 text-success"></i>Status</td>
                            <td class="field-value" colspan="3">
                                {!! display_memo_status_auto($memo) !!}
                            </td>
                        </tr>
                        @if ($memo->document_number)
                            <tr>
                                <td class="field-label"><i class="bx bx-hash me-2 text-info"></i>Document number</td>
                                <td class="field-value" colspan="3">
                                    <span class="text-success fw-bold">{{ $memo->document_number }}</span>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="field-label"><i class="bx bx-user me-2 text-primary"></i>Creator</td>
                            <td class="field-value">
                                {{ trim(($memo->creator->title ?? '') . ' ' . ($memo->creator->fname ?? '') . ' ' . ($memo->creator->lname ?? '')) ?: '—' }}
                            </td>
                            <td class="field-label"><i class="bx bx-building me-2 text-secondary"></i>Division</td>
                            <td class="field-value">
                                {{ $memo->division->division_name ?? '—' }}
                            </td>
                        </tr>
                        @if ($memo->overall_status === 'pending' && $memo->currentApprover)
                            <tr>
                                <td class="field-label"><i class="bx bx-user-check me-2 text-warning"></i>Current approver</td>
                                <td class="field-value" colspan="3">
                                    {{ $memo->currentApprover->fname }} {{ $memo->currentApprover->lname }}
                                    @php
                                        $seq = (int) ($memo->active_sequence ?? 0);
                                        $row = $memo->approverAtSequence($seq);
                                    @endphp
                                    @if (is_array($row) && ! empty($row['role_label']))
                                        <span class="text-muted">({{ $row['role_label'] }})</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="field-label"><i class="bx bx-paperclip me-2 text-info"></i>Attachments</td>
                            <td class="field-value" colspan="3">
                                @if (count($memoAttachmentsShow) > 0)
                                    <span class="badge bg-info">{{ count($memoAttachmentsShow) }} file(s) attached</span>
                                @else
                                    <span class="text-muted">No attachments</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label"><i class="bx bx-plus-circle me-2 text-primary"></i>Created</td>
                            <td class="field-value">
                                {{ $memo->created_at ? $memo->created_at->format('M d, Y H:i') : '—' }}
                            </td>
                            <td class="field-label"><i class="bx bx-edit me-2 text-secondary"></i>Last updated</td>
                            <td class="field-value">
                                {{ $memo->updated_at ? $memo->updated_at->format('M d, Y H:i') : '—' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label"><i class="bx bx-layout me-2 text-secondary"></i>Signature layout</td>
                            <td class="field-value" colspan="3">{{ str_replace('_', ' ', $memo->signature_style_snapshot ?? '—') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                @include('other-memos.partials.show-content-cards', [
                    'schema' => $memo->fields_schema_snapshot ?? [],
                    'values' => $memo->payload ?? [],
                ])

                @if (count($memoAttachmentsShow) > 0)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-paperclip"></i>Attachments
                            </h6>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>File name</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($memoAttachmentsShow as $index => $attachment)
                                            @php
                                                $originalName = $attachment['original_name'] ?? ($attachment['filename'] ?? ($attachment['name'] ?? 'Unknown'));
                                                $filePath = $attachment['path'] ?? ($attachment['file_path'] ?? '');
                                                $ext = $filePath ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';
                                                $fileUrl = $filePath ? url('storage/' . $filePath) : '#';
                                                $isOffice = in_array($ext, ['ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'], true);
                                            @endphp
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                                <td>{{ $originalName }}</td>
                                                <td>{{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'N/A' }}</td>
                                                <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                                <td>
                                                    @if ($filePath)
                                                        <button type="button"
                                                            class="btn btn-sm btn-success preview-other-memo-attachment"
                                                            data-file-url="{{ $fileUrl }}"
                                                            data-file-ext="{{ $ext }}"
                                                            data-file-office="{{ $isOffice ? '1' : '0' }}">
                                                            <i class="bx bx-show"></i> Preview
                                                        </button>
                                                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="bx bx-download"></i> Download
                                                        </a>
                                                    @else
                                                        <span class="text-muted">File not found</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mb-3">
                    @include('matrices.partials.approval-trail', ['trails' => $memo->approvalTrails])
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">Quick status</div>
                    <div class="card-body small">
                        <p class="mb-2"><strong>Status:</strong> {!! display_memo_status_auto($memo) !!}</p>
                        <p class="mb-0"><strong>Approver chain:</strong></p>
                        <ol class="mb-0 ps-3 mt-1">
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
    </div>
</div>

    <div class="modal fade" id="otherMemoPreviewModal" tabindex="-1" aria-labelledby="otherMemoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="otherMemoPreviewModalLabel">Attachment preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="otherMemoPreviewModalBody" style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
                    <div class="text-center w-100">Loading preview…</div>
                </div>
            </div>
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
<script>
(function() {
    if (typeof jQuery === 'undefined') return;
    jQuery(document).on('click', '.preview-other-memo-attachment', function() {
        var $btn = jQuery(this);
        var fileUrl = $btn.data('file-url');
        var ext = ($btn.data('file-ext') || '').toLowerCase();
        var isOffice = String($btn.data('file-office')) === '1';
        var modalBody = jQuery('#otherMemoPreviewModalBody');
        var content = '';
        if (['jpg', 'jpeg', 'png'].indexOf(ext) !== -1) {
            content = '<img src="' + fileUrl + '" class="img-fluid" style="max-height:70vh;max-width:100%;margin:auto;display:block;">';
        } else if (ext === 'pdf') {
            content = '<iframe src="' + fileUrl + '#toolbar=1&navpanes=0&scrollbar=1" style="width:100%;height:70vh;border:none;"></iframe>';
        } else if (isOffice) {
            var gdocs = 'https://docs.google.com/viewer?url=' + encodeURIComponent(fileUrl) + '&embedded=true';
            content = '<iframe src="' + gdocs + '" style="width:100%;height:70vh;border:none;"></iframe>';
        } else {
            content = '<div class="alert alert-info">Preview not available. <a href="' + fileUrl + '" target="_blank">Download or open file</a></div>';
        }
        modalBody.html(content);
        var el = document.getElementById('otherMemoPreviewModal');
        if (el && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(el).show();
        }
    });
})();
</script>
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
