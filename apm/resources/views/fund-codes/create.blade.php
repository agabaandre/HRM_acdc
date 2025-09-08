@extends('layouts.app')

@section('title', 'Create Fund Code')
@section('header', 'Create Fund Code')

@section('header-actions')
<a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back me-1 text-success"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="bx bx-plus-circle me-2"></i> Fund Code Details
        </h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('fund-codes.store') }}" method="POST" id="fundCodeForm">
            @csrf
            <input type="hidden" name="_token" value="{{ csrf_token() }}">

            <!-- Section 1: Basic Information -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-info-circle me-2"></i> Basic Information
                </h6>
                
                <div class="row g-4">
                    <!-- Funder -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="funder_id" class="form-label fw-semibold">
                                <i class="bx bx-building me-1 text-success"></i> Funder <span class="text-danger">*</span>
                            </label>
                            <select name="funder_id" id="funder_id" class="form-select select2 border-success @error('funder_id') is-invalid @enderror" required>
                                <option value="">Select Funder</option>
                                @foreach($funders as $funder)
                                    <option value="{{ $funder->id }}" {{ old('funder_id') == $funder->id ? 'selected' : '' }}>{{ $funder->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The funding organization</small>
                            @error('funder_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Year -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="year" class="form-label fw-semibold">
                                <i class="bx bx-calendar me-1 text-success"></i> Year <span class="text-danger">*</span>
                            </label>
                            <select name="year" id="year" class="form-select select2 border-success @error('year') is-invalid @enderror" required>
                                <option value="">Select Year</option>
                                @for($yearOption = date('Y'); $yearOption >= date('Y') - 5; $yearOption--)
                                    <option value="{{ $yearOption }}" {{ old('year') == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                                @endfor
                            </select>
                            <small class="text-muted mt-1 d-block">Funding year</small>
                            @error('year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Fund Code -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="code" class="form-label fw-semibold">
                                <i class="bx bx-barcode me-1 text-success"></i> Fund Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control border-success @error('code') is-invalid @enderror" 
                                   id="code" 
                                   name="code" 
                                   value="{{ old('code') }}" 
                                   placeholder="Enter fund code"
                                   required>
                            <small class="text-muted mt-1 d-block">Example: AF-2025-01, USAID-2025</small>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
            <!-- Section 2: Classification -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-tags me-2"></i> Classification
                </h6>
                
                <div class="row g-4">
                    <!-- Fund Type -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="fund_type_id" class="form-label fw-semibold">
                                <i class="bx bx-category me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                            </label>
                            <select name="fund_type_id" id="fund_type_id" class="form-select select2 border-success @error('fund_type_id') is-invalid @enderror" required>
                                <option value="">Select Fund Type</option>
                                @foreach($fundTypes as $fundType)
                                    <option value="{{ $fundType->id }}" {{ old('fund_type_id', $selectedFundType) == $fundType->id ? 'selected' : '' }}>{{ $fundType->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">Category of funding</small>
                            @error('fund_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Division -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="division_id" class="form-label fw-semibold">
                                <i class="bx bx-building me-1 text-success"></i> Division <span id="division-required" class="text-danger" style="display: none;">*</span>
                            </label>
                            <select name="division_id" id="division_id" class="form-select select2 border-success @error('division_id') is-invalid @enderror">
                                <option value="">Select Division</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>{{ $division->division_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">Associated division (required for intramural)</small>
                            @error('division_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Activity -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="activity" class="form-label fw-semibold">
                                <i class="bx bx-task me-1 text-success"></i> Activity
                            </label>
                            <textarea class="form-control border-success @error('activity') is-invalid @enderror" 
                                      id="activity" 
                                      name="activity" 
                                      rows="3" 
                                      placeholder="Describe the activity">{{ old('activity') }}</textarea>
                            <small class="text-muted mt-1 d-block">Activity description</small>
                            @error('activity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
            <!-- Section 3: Financial Information -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-dollar-sign me-2"></i> Financial Information
                </h6>
                
                <div class="row g-4">
                    <!-- Cost Centre -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cost_centre" class="form-label fw-semibold">
                                <i class="bx bx-building-house me-1 text-success"></i> Cost Centre
                            </label>
                            <input type="text" 
                                   class="form-control border-success @error('cost_centre') is-invalid @enderror" 
                                   id="cost_centre" 
                                   name="cost_centre" 
                                   value="{{ old('cost_centre') }}" 
                                   placeholder="Enter cost centre">
                            <small class="text-muted mt-1 d-block">Cost centre code</small>
                            @error('cost_centre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Amert Code -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="amert_code" class="form-label fw-semibold">
                                <i class="bx bx-barcode me-1 text-success"></i> Amert Code
                            </label>
                            <input type="text" 
                                   class="form-control border-success @error('amert_code') is-invalid @enderror" 
                                   id="amert_code" 
                                   name="amert_code" 
                                   value="{{ old('amert_code') }}" 
                                   placeholder="Enter amert code">
                            <small class="text-muted mt-1 d-block">Amert accounting code</small>
                            @error('amert_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Fund -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="fund" class="form-label fw-semibold">
                                <i class="bx bx-wallet me-1 text-success"></i> Fund
                            </label>
                            <input type="text" 
                                   class="form-control border-success @error('fund') is-invalid @enderror" 
                                   id="fund" 
                                   name="fund" 
                                   value="{{ old('fund') }}" 
                                   placeholder="Enter fund">
                            <small class="text-muted mt-1 d-block">Fund identifier</small>
                            @error('fund')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-3">
                    <!-- Budget Balance -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="budget_balance" class="form-label fw-semibold">
                                <i class="bx bx-balance me-1 text-success"></i> Budget Balance
                            </label>
                            <input type="number" 
                                   step="0.01"
                                   class="form-control border-success @error('budget_balance') is-invalid @enderror" 
                                   id="budget_balance" 
                                   name="budget_balance" 
                                   value="{{ old('budget_balance') }}" 
                                   placeholder="0.00">
                            <small class="text-muted mt-1 d-block">Current budget balance</small>
                            @error('budget_balance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Approved Budget -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="approved_budget" class="form-label fw-semibold">
                                <i class="bx bx-check-circle me-1 text-success"></i> Approved Budget
                            </label>
                            <input type="number" 
                                   step="0.01"
                                   class="form-control border-success @error('approved_budget') is-invalid @enderror" 
                                   id="approved_budget" 
                                   name="approved_budget" 
                                   value="{{ old('approved_budget') }}" 
                                   placeholder="0.00">
                            <small class="text-muted mt-1 d-block">Approved budget amount</small>
                            @error('approved_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Uploaded Budget -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="uploaded_budget" class="form-label fw-semibold">
                                <i class="bx bx-upload me-1 text-success"></i> Uploaded Budget
                            </label>
                            <input type="number" 
                                   step="0.01"
                                   class="form-control border-success @error('uploaded_budget') is-invalid @enderror" 
                                   id="uploaded_budget" 
                                   name="uploaded_budget" 
                                   value="{{ old('uploaded_budget') }}" 
                                   placeholder="0.00">
                            <small class="text-muted mt-1 d-block">Uploaded budget amount</small>
                            @error('uploaded_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Status -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-toggle-on me-2"></i> Status
                </h6>
                
                <div class="row g-4">
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="is_active">
                                <i class="bx bx-check-circle me-1 text-success"></i> Active Status
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">Inactive fund codes will not be available for selection in other modules</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-3 mt-5 pt-4 border-top">
                <a href="{{ route('fund-codes.index') }}" class="btn btn-light px-4">
                    <i class="bx bx-x me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bx bx-save me-1"></i> Save Fund Code
                </button>
            </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for all select fields
    $('.select2').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });

    const fundTypeSelect = document.getElementById('fund_type_id');
    const divisionSelect = document.getElementById('division_id');
    const divisionRequired = document.getElementById('division-required');
    
    function toggleDivisionRequired() {
        const selectedFundType = fundTypeSelect.value;
        const selectedOption = fundTypeSelect.options[fundTypeSelect.selectedIndex];
        const fundTypeName = selectedOption ? selectedOption.text.toLowerCase() : '';
        
        // Make division required only for intramural, optional for extramural and external source
        if (fundTypeName === 'intramural') {
            divisionSelect.required = true;
            divisionRequired.style.display = 'inline';
        } else {
            divisionSelect.required = false;
            divisionRequired.style.display = 'none';
        }
    }
    
    // Initial check
    toggleDivisionRequired();
    
    // Listen for changes on Select2
    $(fundTypeSelect).on('change', function() {
        toggleDivisionRequired();
    });
});
</script>
@endpush