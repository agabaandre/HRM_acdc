@extends('layouts.app')

@section('title', 'Assign Models to Workflows')

@section('header', 'Assign Models to Workflows')

@section('header-actions')
<a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary" style="border-color: #2c3d50; color: #2c3d50;">
    <i class="bx bx-arrow-back"></i> Back to Workflows
</a>
@endsection

@section('content')
<style>
:root {
    --primary-color: #119a48;
    --secondary-color: #2c3d50;
    --light-grey: #f8f9fa;
    --text-muted: #6c757d;
}

.theme-primary { color: var(--primary-color) !important; }
.theme-secondary { color: var(--secondary-color) !important; }
.bg-theme-primary { background-color: var(--primary-color) !important; }
.bg-theme-secondary { background-color: var(--secondary-color) !important; }
.bg-light-grey { background-color: var(--light-grey) !important; }
.border-theme-primary { border-color: var(--primary-color) !important; }
.border-theme-secondary { border-color: var(--secondary-color) !important; }

.btn-theme-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.btn-theme-primary:hover {
    background-color: #0d7a3a;
    border-color: #0d7a3a;
    color: white;
}

.btn-outline-theme-secondary {
    color: var(--secondary-color);
    border-color: var(--secondary-color);
}

.btn-outline-theme-secondary:hover {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
    color: white;
}

.card-theme {
    border: 1px solid #e9ecef;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header-theme {
    background-color: var(--light-grey);
    border-bottom: 1px solid #e9ecef;
}

.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

.is-invalid {
    border-color: #dc3545 !important;
}

.is-invalid:focus {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

.card-body h6 {
    font-weight: 600;
    margin-bottom: 1rem;
}

.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.text-muted {
    color: var(--text-muted) !important;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card card-theme">
            <div class="card-header card-header-theme">
                <h5 class="mb-0 theme-secondary">
                    <i class="bx bx-link me-2 theme-primary"></i>Model Workflow Assignments
                </h5>
                <small class="text-muted">Assign forward workflow IDs to different models for automatic workflow assignment</small>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="background-color: rgba(17, 154, 72, 0.1); border-color: var(--primary-color); color: var(--primary-color);">
                        <i class="bx bx-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background-color: rgba(220, 53, 69, 0.1); border-color: #dc3545; color: #dc3545;">
                        <i class="bx bx-error-circle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('workflows.store-model-assignments') }}" method="POST" id="assignmentsForm">
                    @csrf
                    
                    <div class="row g-4">
                        @foreach($models as $modelKey => $modelName)
                        <div class="col-md-6 col-lg-4">
                            <div class="card border-0 bg-light-grey">
                                <div class="card-body">
                                    <h6 class="card-title theme-primary mb-3">
                                        <i class="bx bx-file me-2"></i>{{ $modelName }}
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label for="workflow_{{ $modelKey }}" class="form-label fw-semibold theme-secondary">
                                            Select Workflow
                                        </label>
                                        <select class="form-select" name="assignments[{{ $loop->index }}][workflow_id]" id="workflow_{{ $modelKey }}" required>
                                            <option value="">Choose a workflow...</option>
                                            @foreach($workflows as $workflow)
                                                <option value="{{ $workflow->id }}" 
                                                    {{ (old('assignments.' . $loop->parent->index . '.workflow_id') == $workflow->id || 
                                                        (isset($currentAssignments[$modelKey]) && $currentAssignments[$modelKey]->workflow_id == $workflow->id)) ? 'selected' : '' }}>
                                                    {{ $workflow->workflow_name }}
                                                    @if($workflow->Description)
                                                        - {{ Str::limit($workflow->Description, 30) }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="assignments[{{ $loop->index }}][model]" value="{{ $modelKey }}">
                                        
                                        @if(isset($currentAssignments[$modelKey]))
                                            <div class="form-text theme-primary">
                                                <i class="bx bx-check-circle me-1"></i>
                                                Currently assigned to: <strong>{{ $currentAssignments[$modelKey]->workflow->workflow_name }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="text-muted small">
                                        <i class="bx bx-info-circle me-1"></i>
                                        This workflow will be automatically assigned to new {{ strtolower($modelName) }}s
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bx bx-info-circle me-1"></i>
                                    These assignments will be used by controllers to automatically set forward_workflow_id
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('workflows.index') }}" class="btn btn-outline-theme-secondary">
                                        <i class="bx bx-x me-1"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-theme-primary">
                                        <i class="bx bx-save me-1"></i> Save Assignments
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Information Modal -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header card-header-theme">
                <h5 class="modal-title theme-secondary" id="infoModalLabel">
                    <i class="bx bx-info-circle me-2 theme-primary"></i>Model Workflow Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="theme-secondary">How it works:</h6>
                <ul class="mb-3">
                    <li>Each model can be assigned a default workflow</li>
                    <li>When creating new records, controllers will automatically use the assigned workflow</li>
                    <li>This ensures consistent workflow assignment across the system</li>
                </ul>
                
                <h6 class="theme-secondary">Supported Models:</h6>
                <ul>
                    <li><strong class="theme-primary">Matrix:</strong> Quarterly travel matrices</li>
                    <li><strong class="theme-primary">Activity:</strong> Individual activities within matrices</li>
                    <li><strong class="theme-primary">Non Travel Memo:</strong> Non-travel related memos</li>
                    <li><strong class="theme-primary">Special Memo:</strong> Special travel memos</li>
                    <li><strong class="theme-primary">Request ARF:</strong> Advance Request Forms</li>
                    <li><strong class="theme-primary">Service Request:</strong> Service requests (DSA, Imprest, Tickets)</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-theme-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('assignmentsForm');
    
    form.addEventListener('submit', function(e) {
        const selects = form.querySelectorAll('select[required]');
        let isValid = true;
        
        selects.forEach(select => {
            if (!select.value) {
                select.classList.add('is-invalid');
                isValid = false;
            } else {
                select.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please select a workflow for all models.');
        }
    });
    
    // Remove invalid class on change
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', function() {
            this.classList.remove('is-invalid');
        });
    });
});
</script>
@endpush
