@extends('layouts.app')

@section('title', 'Edit Special Memo')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bx bx-edit me-1"></i> Edit Special Memo
                    </h6>
                    <div>
                        <a href="{{ route('special-memo.show', $specialMemo) }}" class="btn btn-outline-info btn-sm me-2">
                            <i class="bx bx-show me-1"></i> View Details
                        </a>
                        <a href="{{ route('special-memo.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('special-memo.update', $specialMemo) }}" method="POST" enctype="multipart/form-data" id="specialMemoForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-lg-8">
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Memo Information</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Memo Number</label>
                                                    <input type="text" 
                                                           name="memo_number" 
                                                           class="form-control @error('memo_number') is-invalid @enderror"
                                                           value="{{ old('memo_number', $specialMemo->memo_number) }}"
                                                           readonly>
                                                    @error('memo_number')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Memo Date</label>
                                                    <input type="date" 
                                                           name="memo_date" 
                                                           class="form-control @error('memo_date') is-invalid @enderror"
                                                           value="{{ old('memo_date', $specialMemo->memo_date ? $specialMemo->memo_date->format('Y-m-d') : '') }}"
                                                           required>
                                                    @error('memo_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Subject</label>
                                                    <input type="text" 
                                                           name="subject" 
                                                           class="form-control @error('subject') is-invalid @enderror"
                                                           value="{{ old('subject', $specialMemo->subject) }}"
                                                           placeholder="Enter memo subject"
                                                           required>
                                                    @error('subject')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Memo Body Content</label>
                                                    <textarea name="body" 
                                                              class="form-control @error('body') is-invalid @enderror" 
                                                              rows="8"
                                                              placeholder="Enter memo content"
                                                              required>{{ old('body', $specialMemo->body) }}</textarea>
                                                    @error('body')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Recipients</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="form-group">
                                            <label class="form-label fw-semibold">Select Recipients</label>
                                            <select name="recipients[]" 
                                                    class="form-select @error('recipients') is-invalid @enderror" 
                                                    multiple>
                                                @foreach($staff as $s)
                                                    <option value="{{ $s->id }}" {{ in_array($s->id, old('recipients', $specialMemo->recipients ?? [])) ? 'selected' : '' }}>
                                                        {{ $s->first_name }} {{ $s->last_name }} ({{ $s->division->name ?? 'No Division' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('recipients')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Hold Ctrl or Shift to select multiple recipients</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm">
                                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 fw-semibold">Attachments</h6>
                                        <span class="badge bg-primary">{{ count($specialMemo->attachment ?? []) }} file(s)</span>
                                    </div>
                                    <div class="card-body p-4">
                                        @if(count($specialMemo->attachment ?? []) > 0)
                                            <div class="mb-3">
                                                <h6 class="fw-semibold small">Current Attachments</h6>
                                                <div class="list-group">
                                                    @foreach($specialMemo->attachment as $index => $attachment)
                                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
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
                                                                <a href="{{ asset('storage/' . ($attachment['path'] ?? '')) }}" target="_blank" class="text-decoration-none">
                                                                    {{ $attachment['name'] ?? 'File' }}
                                                                </a>
                                                                <small class="text-muted">
                                                                    ({{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'Unknown size' }})
                                                                </small>
                                                            </div>
                                                            <form action="{{ route('special-memo.remove-attachment', $specialMemo) }}" method="POST" class="d-inline remove-attachment-form">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="attachment_index" value="{{ $index }}">
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="bx bx-trash me-1"></i> Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <div class="form-group">
                                            <label class="form-label fw-semibold">Attach New Files</label>
                                            <input type="file" 
                                                   name="attachment[]" 
                                                   class="form-control @error('attachment.*') is-invalid @enderror" 
                                                   multiple>
                                            @error('attachment.*')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                Accepted formats: PDF, DOC, DOCX, JPG, JPEG, PNG. Maximum size: 10MB each.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-lg-4">
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Author & Department</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Author</label>
                                                    <select name="staff_id" 
                                                            class="form-select @error('staff_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Author</option>
                                                        @foreach($staff as $s)
                                                            <option value="{{ $s->id }}" {{ old('staff_id', $specialMemo->staff_id) == $s->id ? 'selected' : '' }}>
                                                                {{ $s->first_name }} {{ $s->last_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('staff_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Division</label>
                                                    <select name="division_id" 
                                                            class="form-select @error('division_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Division</option>
                                                        @foreach($divisions as $division)
                                                            <option value="{{ $division->id }}" {{ old('division_id', $specialMemo->division_id) == $division->id ? 'selected' : '' }}>
                                                                {{ $division->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('division_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Workflow Settings</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Forward Workflow</label>
                                                    <select name="forward_workflow_id" 
                                                            class="form-select @error('forward_workflow_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Workflow</option>
                                                        @foreach($workflows as $workflow)
                                                            <option value="{{ $workflow->id }}" {{ old('forward_workflow_id', $specialMemo->forward_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                                                {{ $workflow->workflow_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('forward_workflow_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Reverse Workflow</label>
                                                    <select name="reverse_workflow_id" 
                                                            class="form-select @error('reverse_workflow_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Workflow</option>
                                                        @foreach($workflows as $workflow)
                                                            <option value="{{ $workflow->id }}" {{ old('reverse_workflow_id', $specialMemo->reverse_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                                                {{ $workflow->workflow_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('reverse_workflow_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Memo Details</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Priority</label>
                                                    <select name="priority" 
                                                            class="form-select @error('priority') is-invalid @enderror"
                                                            required>
                                                        <option value="low" {{ old('priority', $specialMemo->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                                        <option value="medium" {{ old('priority', $specialMemo->priority) == 'medium' ? 'selected' : '' }}>Medium</option>
                                                        <option value="high" {{ old('priority', $specialMemo->priority) == 'high' ? 'selected' : '' }}>High</option>
                                                        <option value="urgent" {{ old('priority', $specialMemo->priority) == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                                    </select>
                                                    @error('priority')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Status</label>
                                                    <select name="status" 
                                                            class="form-select @error('status') is-invalid @enderror">
                                                        <option value="draft" {{ old('status', $specialMemo->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                                                        <option value="submitted" {{ old('status', $specialMemo->status) == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                                        <option value="approved" {{ old('status', $specialMemo->status) == 'approved' ? 'selected' : '' }}>Approved</option>
                                                        <option value="rejected" {{ old('status', $specialMemo->status) == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                                    </select>
                                                    @error('status')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Remarks</label>
                                                    <textarea name="remarks" 
                                                              class="form-control @error('remarks') is-invalid @enderror" 
                                                              rows="3"
                                                              placeholder="Optional notes or remarks">{{ old('remarks', $specialMemo->remarks) }}</textarea>
                                                    @error('remarks')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-save me-1"></i> Update Special Memo
                                    </button>
                                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary">
                                        <i class="bx bx-x me-1"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function() {
        // Initialize Select2 for better dropdown UX
        $('.form-select').select2({
            dropdownParent: $('#specialMemoForm'),
        });

        // Setup remove attachment confirmation
        $('.remove-attachment-form').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Remove Attachment?',
                text: "This attachment will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bx bx-trash me-1"></i> Yes, remove it!',
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

        // Form validation
        $('#specialMemoForm').on('submit', function(e) {
            // Show loading indicator
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="bx bx-loader bx-spin me-2"></i> Updating...');
            submitBtn.prop('disabled', true);

            return true;
        });
    });
</script>
@endpush
