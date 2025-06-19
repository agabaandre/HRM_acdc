@extends('layouts.app')

@section('title', 'Create Fund Code')

@section('header', 'Create Fund Code')

@section('header-actions')
    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="bx bx-plus-circle me-2"></i>New Fund Code</h5>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('fund-codes.store') }}" method="POST">
                @csrf

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="funder_id" class="form-label fw-semibold">
                                <i class="bx bx-user me-1 text-primary"></i>Funder
                            </label>
                            <select name="funder_id" id="funder_id" class="form-select form-select-lg @error('funder_id') is-invalid @enderror">
                                <option value="">Select Funder</option>
                                @foreach($funders as $funder)
                                    <option value="{{ $funder->id }}" {{ old('funder_id') == $funder->id ? 'selected' : '' }}>{{ $funder->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The funder for this fund code</small>
                            @error('funder_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="year" class="form-label fw-semibold">
                                <i class="bx bx-calendar me-1 text-primary"></i>Year <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control form-control-lg @error('year') is-invalid @enderror" id="year" name="year" value="{{ old('year') }}" placeholder="e.g. 2025" min="2000" max="2100" required>
                            <small class="text-muted mt-1 d-block">Year for this fund code</small>
                            @error('year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="code" class="form-label fw-semibold">
                                <i class="bx bx-code me-1 text-primary"></i>Fund Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" placeholder="Enter fund code" required>
                            <small class="text-muted mt-1 d-block">Example: AF-2025-01, USAID-2025</small>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="fund_type_id" class="form-label fw-semibold">
                                <i class="bx bx-category me-1 text-primary"></i>Fund Type
                            </label>
                            <select name="fund_type_id" id="fund_type_id" class="form-select form-select-lg @error('fund_type_id') is-invalid @enderror">
                                <option value="">Select Fund Type</option>
                                @foreach($fundTypes as $fundType)
                                    <option value="{{ $fundType->id }}" {{ old('fund_type_id', $selectedFundType) == $fundType->id ? 'selected' : '' }}>{{ $fundType->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The category this fund code belongs to</small>
                            @error('fund_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="division_id" class="form-label fw-semibold">
                                <i class="bx bx-building me-1 text-primary"></i>Division
                            </label>
                            <select name="division_id" id="division_id" class="form-select form-select-lg @error('division_id') is-invalid @enderror">
                                <option value="">Select Division</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>{{ $division->division_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The division this fund code is associated with</small>
                            @error('division_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="activity" class="form-label fw-semibold">
                                <i class="bx bx-task me-1 text-primary"></i>Activity
                            </label>
                            <input type="text" class="form-control form-control-lg @error('activity') is-invalid @enderror" id="activity" name="activity" value="{{ old('activity') }}" placeholder="Activity">
                            <small class="text-muted mt-1 d-block">Activity for this fund code</small>
                            @error('activity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="cost_centre" class="form-label fw-semibold">
                                <i class="bx bx-building-house me-1 text-primary"></i>Cost Centre
                            </label>
                            <input type="text" class="form-control form-control-lg @error('cost_centre') is-invalid @enderror" id="cost_centre" name="cost_centre" value="{{ old('cost_centre') }}" placeholder="Cost Centre">
                            <small class="text-muted mt-1 d-block">Cost centre for this fund code</small>
                            @error('cost_centre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="amert_code" class="form-label fw-semibold">
                                <i class="bx bx-barcode me-1 text-primary"></i>Amert Code
                            </label>
                            <input type="text" class="form-control form-control-lg @error('amert_code') is-invalid @enderror" id="amert_code" name="amert_code" value="{{ old('amert_code') }}" placeholder="Amert Code">
                            <small class="text-muted mt-1 d-block">Amert code for this fund code</small>
                            @error('amert_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="fund" class="form-label fw-semibold">
                                <i class="bx bx-wallet me-1 text-primary"></i>Fund
                            </label>
                            <input type="text" class="form-control form-control-lg @error('fund') is-invalid @enderror" id="fund" name="fund" value="{{ old('fund') }}" placeholder="Fund">
                            <small class="text-muted mt-1 d-block">Fund for this fund code</small>
                            @error('fund')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="budget_balance" class="form-label fw-semibold">
                                <i class="bx bx-balance me-1 text-primary"></i>Budget Balance
                            </label>
                            <input type="text" class="form-control form-control-lg @error('budget_balance') is-invalid @enderror" id="budget_balance" name="budget_balance" value="{{ old('budget_balance') }}" placeholder="Budget Balance">
                            <small class="text-muted mt-1 d-block">Budget balance for this fund code</small>
                            @error('budget_balance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="approved_budget" class="form-label fw-semibold">
                                <i class="bx bx-check-circle me-1 text-primary"></i>Approved Budget
                            </label>
                            <input type="text" class="form-control form-control-lg @error('approved_budget') is-invalid @enderror" id="approved_budget" name="approved_budget" value="{{ old('approved_budget') }}" placeholder="Approved Budget">
                            <small class="text-muted mt-1 d-block">Approved budget for this fund code</small>
                            @error('approved_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group position-relative">
                            <label for="uploaded_budget" class="form-label fw-semibold">
                                <i class="bx bx-upload me-1 text-primary"></i>Uploaded Budget
                            </label>
                            <input type="text" class="form-control form-control-lg @error('uploaded_budget') is-invalid @enderror" id="uploaded_budget" name="uploaded_budget" value="{{ old('uploaded_budget') }}" placeholder="Uploaded Budget">
                            <small class="text-muted mt-1 d-block">Uploaded budget for this fund code</small>
                            @error('uploaded_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_active">Active Status</label>
                    </div>
                    <small class="text-muted d-block">Inactive fund codes will not be available for selection in other
                        modules</small>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary px-4">
                        <i class="bx bx-arrow-back me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                        <i class="bx bx-save me-2"></i> Save Fund Code
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection