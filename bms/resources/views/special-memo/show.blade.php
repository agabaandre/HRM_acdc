@extends('layouts.app')

@section('title', 'View Special Memo')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bx bx-file me-1"></i> Special Memo Details
                    </h6>
                    <div>
                        <a href="{{ route('special-memo.edit', $specialMemo) }}" class="btn btn-primary btn-sm me-2">
                            <i class="bx bx-edit me-1"></i> Edit
                        </a>
                        <a href="{{ route('special-memo.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Memo Information -->
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-3">
                                    <h6 class="m-0 fw-semibold">Memo Information</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-4">
                                        <h5 class="fw-bold mb-3">{{ $specialMemo->subject }}</h5>
                                        <div class="d-flex flex-wrap mb-3 align-items-center">
                                            <div class="me-4 mb-2">
                                                <span class="text-muted small me-2">Memo Number:</span>
                                                <span class="badge bg-light text-dark border">{{ $specialMemo->memo_number }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="text-muted small me-2">Date:</span>
                                                <span class="badge bg-light text-dark border">{{ $specialMemo->memo_date->format('d M, Y') }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="text-muted small me-2">Priority:</span>
                                                @php
                                                    $priorityBadgeClass = [
                                                        'low' => 'bg-light text-dark',
                                                        'medium' => 'bg-info',
                                                        'high' => 'bg-warning',
                                                        'urgent' => 'bg-danger',
                                                    ][$specialMemo->priority] ?? 'bg-secondary';
                                                @endphp
                                                <span class="badge {{ $priorityBadgeClass }}">
                                                    {{ ucfirst($specialMemo->priority) }}
                                                </span>
                                            </div>
                                            <div class="mb-2">
                                                <span class="text-muted small me-2">Status:</span>
                                                @php
                                                    $statusBadgeClass = [
                                                        'draft' => 'bg-secondary',
                                                        'submitted' => 'bg-primary',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                    ][$specialMemo->status] ?? 'bg-secondary';
                                                @endphp
                                                <span class="badge {{ $statusBadgeClass }}">
                                                    {{ ucfirst($specialMemo->status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h6 class="fw-semibold mb-2">Memo Content</h6>
                                        <div class="bg-light p-3 rounded border">
                                            {!! nl2br(e($specialMemo->body)) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recipients -->
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-semibold">Recipients</h6>
                                    <span class="badge bg-primary">{{ count($specialMemo->recipients ?? []) }}</span>
                                </div>
                                <div class="card-body p-4">
                                    @if(!empty($specialMemo->recipients) && count($specialMemo->recipients) > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Name</th>
                                                        <th>Division</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($specialMemo->recipients as $index => $recipientId)
                                                        @php 
                                                            $recipient = App\Models\Staff::find($recipientId);
                                                        @endphp
                                                        @if($recipient)
                                                            <tr>
                                                                <td>{{ $index + 1 }}</td>
                                                                <td>{{ $recipient->first_name }} {{ $recipient->last_name }}</td>
                                                                <td>{{ $recipient->division->name ?? 'N/A' }}</td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="alert alert-info mb-0">
                                            <i class="bx bx-info-circle me-2"></i>
                                            No recipients specified for this memo.
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Attachments -->
                            <div class="card border shadow-sm">
                                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-semibold">Attachments</h6>
                                    <span class="badge bg-primary">{{ count($specialMemo->attachment ?? []) }}</span>
                                </div>
                                <div class="card-body p-4">
                                    @if(!empty($specialMemo->attachment) && count($specialMemo->attachment) > 0)
                                        <div class="list-group">
                                            @foreach($specialMemo->attachment as $index => $attachment)
                                                <a href="{{ asset('storage/' . ($attachment['path'] ?? '')) }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="bx 
                                                        @if(in_array(strtolower(pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION)), ['pdf']))
                                                            bxs-file-pdf text-danger
                                                        @elseif(in_array(strtolower(pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION)), ['doc', 'docx']))
                                                            bxs-file-doc text-primary
                                                        @elseif(in_array(strtolower(pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']))
                                                            bxs-file-image text-success
                                                        @else
                                                            bxs-file text-secondary
                                                        @endif
                                                        me-2"></i>
                                                        {{ $attachment['name'] ?? 'File' }}
                                                        <small class="text-muted">
                                                            ({{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'Unknown size' }})
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-primary rounded-pill">
                                                        <i class="bx bx-download"></i>
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="alert alert-info mb-0">
                                            <i class="bx bx-info-circle me-2"></i>
                                            No attachments for this memo.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Author Information -->
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-3">
                                    <h6 class="m-0 fw-semibold">Author Information</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="bx bx-user text-primary" style="font-size: 1.5rem;"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-0">{{ $specialMemo->staff->first_name }} {{ $specialMemo->staff->last_name }}</h6>
                                            <span class="text-muted small">{{ $specialMemo->staff->position ?? 'No Position' }}</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Email</div>
                                        <div>{{ $specialMemo->staff->email ?? 'No Email' }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Phone</div>
                                        <div>{{ $specialMemo->staff->phone ?? 'No Phone' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Division</div>
                                        <div>{{ $specialMemo->division->name ?? 'No Division' }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Workflow Information -->
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-3">
                                    <h6 class="m-0 fw-semibold">Workflow Information</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <h6 class="fw-semibold small mb-2">Forward Workflow</h6>
                                        <div class="bg-light p-2 rounded border mb-2">
                                            {{ $specialMemo->forwardWorkflow->name ?? 'Not Specified' }}
                                        </div>
                                        <div class="text-muted small">
                                            This workflow is used for forwarding the memo to the next approver.
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="fw-semibold small mb-2">Reverse Workflow</h6>
                                        <div class="bg-light p-2 rounded border mb-2">
                                            {{ $specialMemo->reverseWorkflow->name ?? 'Not Specified' }}
                                        </div>
                                        <div class="text-muted small">
                                            This workflow is used when the memo needs to be returned to previous approvers.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Details -->
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-3">
                                    <h6 class="m-0 fw-semibold">Additional Details</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Created At</div>
                                        <div>{{ $specialMemo->created_at->format('d M, Y h:i A') }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Last Updated</div>
                                        <div>{{ $specialMemo->updated_at->format('d M, Y h:i A') }}</div>
                                    </div>
                                    @if($specialMemo->remarks)
                                        <div>
                                            <div class="text-muted small mb-1">Remarks</div>
                                            <div class="bg-light p-2 rounded border">
                                                {{ $specialMemo->remarks }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <a href="{{ route('special-memo.edit', $specialMemo) }}" class="btn btn-primary">
                                    <i class="bx bx-edit me-1"></i> Edit Special Memo
                                </a>
                                <form action="{{ route('special-memo.destroy', $specialMemo) }}" method="POST" id="deleteMemoForm">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="bx bx-trash me-1"></i> Delete Special Memo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Setup delete confirmation
        $('#deleteMemoForm').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this special memo.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bx bx-trash me-1"></i> Yes, delete it!',
                cancelButtonText: '<i class="bx bx-x me-1"></i> Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
