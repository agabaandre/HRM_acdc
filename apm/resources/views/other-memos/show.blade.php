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

            <div class="card shadow-sm mb-3">
                <div class="card-header">Approval trail</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($memo->approvalTrails as $t)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ ucfirst($t->action) }}</strong>
                                    <span class="text-muted small">{{ $t->created_at->format('M j, Y g:i a') }}</span>
                                </div>
                                <div class="small">
                                    {{ $t->staff->fname ?? '' }} {{ $t->staff->lname ?? '' }}
                                    @if ($t->approval_order)
                                        <span class="badge bg-light text-dark">Step {{ $t->approval_order }}</span>
                                    @endif
                                </div>
                                @if ($t->remarks)
                                    <div class="mt-1 small text-muted">{{ $t->remarks }}</div>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No actions yet.</li>
                        @endforelse
                    </ul>
                </div>
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
@endsection
