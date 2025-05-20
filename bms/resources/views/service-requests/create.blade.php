@extends('layouts.app')

@section('title', 'Create Service Request')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bx bx-file me-1"></i> Create Service Request
                    </h6>
                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bx bx-arrow-back me-1"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('service-requests.store') }}" method="POST" enctype="multipart/form-data" id="serviceRequestForm">
                        @csrf
                        
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-lg-8">
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Request Information</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Request Number</label>
                                                    <input type="text" 
                                                           name="request_number" 
                                                           class="form-control @error('request_number') is-invalid @enderror"
                                                           value="{{ $requestNumber }}"
                                                           readonly>
                                                    @error('request_number')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Request Date</label>
                                                    <input type="date" 
                                                           name="request_date" 
                                                           id="request_date"
                                                           class="form-control @error('request_date') is-invalid @enderror"
                                                           value="{{ old('request_date', date('Y-m-d')) }}"
                                                           required>
                                                    @error('request_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Service Title</label>
                                                    <input type="text" 
                                                           name="service_title" 
                                                           class="form-control @error('service_title') is-invalid @enderror"
                                                           value="{{ old('service_title') }}"
                                                           placeholder="Enter service request title"
                                                           required>
                                                    @error('service_title')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Description</label>
                                                    <textarea name="description" 
                                                              class="form-control @error('description') is-invalid @enderror" 
                                                              rows="4"
                                                              placeholder="Describe the service requested"
                                                              required>{{ old('description') }}</textarea>
                                                    @error('description')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Justification</label>
                                                    <textarea name="justification" 
                                                              class="form-control @error('justification') is-invalid @enderror" 
                                                              rows="3"
                                                              placeholder="Provide justification for this request"
                                                              required>{{ old('justification') }}</textarea>
                                                    @error('justification')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Required By Date</label>
                                                    <input type="date" 
                                                           name="required_by_date" 
                                                           id="required_by_date"
                                                           class="form-control @error('required_by_date') is-invalid @enderror"
                                                           value="{{ old('required_by_date') }}"
                                                           required>
                                                    @error('required_by_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Location</label>
                                                    <input type="text" 
                                                           name="location" 
                                                           class="form-control @error('location') is-invalid @enderror"
                                                           value="{{ old('location') }}"
                                                           placeholder="Where is this service needed?">
                                                    @error('location')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 fw-semibold">Service Specifications</h6>
                                        <button type="button" id="add-specification" class="btn btn-sm btn-primary">
                                            <i class="bx bx-plus me-1"></i> Add Specification
                                        </button>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="specifications-container">
                                            @if(old('specifications') && count(old('specifications')))
                                                @foreach(old('specifications') as $index => $spec)
                                                    <div class="specification-item mb-3 border rounded p-3 position-relative">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-specification position-absolute top-0 end-0 m-2">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="form-label fw-semibold">Item Name</label>
                                                                    <input type="text" 
                                                                           name="specifications[{{ $index }}][name]" 
                                                                           class="form-control"
                                                                           value="{{ $spec['name'] ?? '' }}"
                                                                           placeholder="Enter item name"
                                                                           required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="form-label fw-semibold">Specification Details</label>
                                                                    <textarea name="specifications[{{ $index }}][details]" 
                                                                             class="form-control" 
                                                                             rows="2"
                                                                             placeholder="Enter specification details">{{ $spec['details'] ?? '' }}</textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                        <div id="no-specifications" class="text-center py-4 {{ old('specifications') && count(old('specifications')) ? 'd-none' : '' }}">
                                            <div class="text-muted">
                                                <i class="bx bx-list text-secondary mb-2" style="font-size: 2rem;"></i>
                                                <p>No specifications added yet. Click "Add Specification" to add items.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card border shadow-sm">
                                    <div class="card-header bg-light py-3">
                                        <h6 class="m-0 fw-semibold">Attachments</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="form-group">
                                            <label class="form-label fw-semibold">Attach Files</label>
                                            <input type="file" 
                                                   name="attachments[]" 
                                                   class="form-control @error('attachments.*') is-invalid @enderror" 
                                                   multiple>
                                            @error('attachments.*')
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
                                        <h6 class="m-0 fw-semibold">Requestor & Department</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Requestor</label>
                                                    <select name="staff_id" 
                                                            class="form-select @error('staff_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Requestor</option>
                                                        @foreach($staff as $s)
                                                            <option value="{{ $s->id }}" {{ old('staff_id') == $s->id ? 'selected' : '' }}>
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
                                                            <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                                                {{ $division->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('division_id')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Related Activity</label>
                                                    <select name="activity_id" 
                                                            class="form-select @error('activity_id') is-invalid @enderror">
                                                        <option value="">None</option>
                                                        @foreach($activities as $activity)
                                                            <option value="{{ $activity->id }}" {{ old('activity_id') == $activity->id ? 'selected' : '' }}>
                                                                {{ $activity->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('activity_id')
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
                                                    <select name="workflow_id" 
                                                            class="form-select @error('workflow_id') is-invalid @enderror"
                                                            required>
                                                        <option value="">Select Workflow</option>
                                                        @foreach($workflows as $workflow)
                                                            <option value="{{ $workflow->id }}" {{ old('workflow_id') == $workflow->id ? 'selected' : '' }}>
                                                                {{ $workflow->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('workflow_id')
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
                                                            <option value="{{ $workflow->id }}" {{ old('reverse_workflow_id') == $workflow->id ? 'selected' : '' }}>
                                                                {{ $workflow->name }}
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
                                        <h6 class="m-0 fw-semibold">Request Details</h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Service Type</label>
                                                    <select name="service_type" 
                                                            class="form-select @error('service_type') is-invalid @enderror"
                                                            required>
                                                        <option value="it" {{ old('service_type') == 'it' ? 'selected' : '' }}>IT</option>
                                                        <option value="maintenance" {{ old('service_type') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                                        <option value="procurement" {{ old('service_type') == 'procurement' ? 'selected' : '' }}>Procurement</option>
                                                        <option value="travel" {{ old('service_type') == 'travel' ? 'selected' : '' }}>Travel</option>
                                                        <option value="other" {{ old('service_type', 'other') == 'other' ? 'selected' : '' }}>Other</option>
                                                    </select>
                                                    @error('service_type')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Priority</label>
                                                    <select name="priority" 
                                                            class="form-select @error('priority') is-invalid @enderror"
                                                            required>
                                                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                                        <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>Medium</option>
                                                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                                        <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                                    </select>
                                                    @error('priority')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Estimated Cost</label>
                                                    <div class="input-group mb-3">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" 
                                                               name="estimated_cost" 
                                                               class="form-control @error('estimated_cost') is-invalid @enderror"
                                                               value="{{ old('estimated_cost', '0.00') }}"
                                                               step="0.01"
                                                               min="0"
                                                               required>
                                                        @error('estimated_cost')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label fw-semibold">Status</label>
                                                    <select name="status" 
                                                            class="form-select @error('status') is-invalid @enderror">
                                                        <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                                                        <option value="submitted" {{ old('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
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
                                                              placeholder="Optional notes or remarks">{{ old('remarks') }}</textarea>
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
                                        <i class="bx bx-save me-1"></i> Save Service Request
                                    </button>
                                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary">
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
            dropdownParent: $('#serviceRequestForm'),
        });

        // Handle date validation for required by date
        $('#request_date').on('change', function() {
            $('#required_by_date').attr('min', $(this).val());
            
            // If required_by_date is already set and now invalid, reset it
            var requiredByDate = $('#required_by_date').val();
            if (requiredByDate && new Date(requiredByDate) < new Date($(this).val())) {
                $('#required_by_date').val('');
            }
        });
        
        // Set initial min date
        $('#required_by_date').attr('min', $('#request_date').val());
        
        // Initialize specifications counter
        let specIndex = {{ old('specifications') ? count(old('specifications')) : 0 }};
        
        // Add specification
        $('#add-specification').on('click', function() {
            $('#no-specifications').addClass('d-none');
            
            const specHtml = `
                <div class="specification-item mb-3 border rounded p-3 position-relative">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-specification position-absolute top-0 end-0 m-2">
                        <i class="bx bx-x"></i>
                    </button>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold">Item Name</label>
                                <input type="text" 
                                       name="specifications[${specIndex}][name]" 
                                       class="form-control"
                                       placeholder="Enter item name"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold">Specification Details</label>
                                <textarea name="specifications[${specIndex}][details]" 
                                         class="form-control" 
                                         rows="2"
                                         placeholder="Enter specification details"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#specifications-container').append(specHtml);
            specIndex++;
        });
        
        // Remove specification
        $(document).on('click', '.remove-specification', function() {
            $(this).closest('.specification-item').remove();
            
            // Show "no specifications" message if no items left
            if ($('.specification-item').length === 0) {
                $('#no-specifications').removeClass('d-none');
            }
        });

        // Form validation
        $('#serviceRequestForm').on('submit', function(e) {
            // Show loading indicator
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="bx bx-loader bx-spin me-2"></i> Saving...');
            submitBtn.prop('disabled', true);

            return true;
        });
    });
</script>
@endpush
