@extends('layouts.app')

@section('title', 'Edit Workflow Definition')

@section('header', 'Edit Workflow Definition')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-info">
        <i class="bx bx-show"></i> View Workflow
    </a>
    <a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-list-ul"></i> All Workflows
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Edit Workflow Definition</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <form action="{{ route('workflows.update-definition', [$workflow->id, $definition->id]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-edit me-2"></i>Definition Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role" class="form-label">
                                        <i class="bx bx-user me-1"></i>Role
                                    </label>
                                    <input type="text" class="form-control @error('role') is-invalid @enderror"
                                        id="role" name="role"
                                        value="{{ old('role', $definition->role) }}" 
                                        placeholder="Enter role name" required>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="approval_order" class="form-label">
                                        <i class="bx bx-sort me-1"></i>Approval Order
                                    </label>
                                    <input type="number" class="form-control @error('approval_order') is-invalid @enderror" 
                                        id="approval_order" name="approval_order"
                                        value="{{ old('approval_order', $definition->approval_order) }}"
                                        placeholder="Enter approval order" min="1" required>
                                    @error('approval_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="fund_type" class="form-label">
                                        <i class="bx bx-money me-1"></i>Fund Type
                                    </label>
                                    <select class="form-select @error('fund_type') is-invalid @enderror" id="fund_type" name="fund_type">
                                        <option value="">Select Fund Type</option>
                                        <option value="intramural" {{ old('fund_type', $definition->fund_type) == 'intramural' ? 'selected' : '' }}>Intramural</option>
                                        <option value="extramural" {{ old('fund_type', $definition->fund_type) == 'extramural' ? 'selected' : '' }}>Extramural</option>
                                        <option value="both" {{ old('fund_type', $definition->fund_type) == 'both' ? 'selected' : '' }}>Both</option>
                                    </select>
                                    @error('fund_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="print_order" class="form-label">
                                        <i class="bx bx-printer me-1"></i>Print Order
                                    </label>
                                    <input type="number" class="form-control @error('print_order') is-invalid @enderror" 
                                        id="print_order" name="print_order"
                                        value="{{ old('print_order', $definition->print_order) }}"
                                        placeholder="Enter print order" min="1">
                                    @error('print_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="memo_print_section" class="form-label">
                                        <i class="bx bx-file me-1"></i>Memo Print Section
                                    </label>
                                    <select class="form-select @error('memo_print_section') is-invalid @enderror" id="memo_print_section" name="memo_print_section">
                                        <option value="through" {{ old('memo_print_section', $definition->memo_print_section) == 'through' ? 'selected' : '' }}>Through</option>
                                        <option value="to" {{ old('memo_print_section', $definition->memo_print_section) == 'to' ? 'selected' : '' }}>To</option>
                                        <option value="from" {{ old('memo_print_section', $definition->memo_print_section) == 'from' ? 'selected' : '' }}>From</option>
                                    </select>
                                    @error('memo_print_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" 
                                            value="1" {{ old('is_enabled', $definition->is_enabled) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_enabled">
                                            <i class="bx bx-power-off me-1"></i>Enabled
                                        </label>
                                    </div>
                                    <small class="text-muted">Enable or disable this definition</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_division_specific" name="is_division_specific" 
                                            value="1" {{ old('is_division_specific', $definition->is_division_specific) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_division_specific">
                                            <i class="bx bx-buildings me-1"></i>Division Specific
                                        </label>
                                    </div>
                                    <small class="text-muted">Check if this definition is specific to a division</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Definition Info</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">ID:</div>
                                <div class="col-7">
                                    <span class="badge bg-primary">{{ $definition->id }}</span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Workflow:</div>
                                <div class="col-7">
                                    <small>{{ $workflow->workflow_name }}</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Status:</div>
                                <div class="col-7">
                                    @if($definition->is_enabled)
                                        <span class="badge bg-success">Enabled</span>
                                    @else
                                        <span class="badge bg-danger">Disabled</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Fund Type:</div>
                                <div class="col-7">
                                    <span class="badge bg-info">{{ ucfirst($definition->fund_type ?? 'Not Set') }}</span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Division Specific:</div>
                                <div class="col-7">
                                    @if($definition->is_division_specific)
                                        <span class="badge bg-warning">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary me-2">
                    <i class="bx bx-x me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i>Update Definition
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
