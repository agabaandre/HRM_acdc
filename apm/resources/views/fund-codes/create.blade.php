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
                    <div class="col-md-6">
                        <div class="form-group position-relative">
                            <label for="fund_type_id" class="form-label fw-semibold">
                                <i class="bx bx-category me-1 text-primary"></i>Fund Type <span class="text-danger">*</span>
                            </label>
                            <select name="fund_type_id" id="fund_type_id"
                                class="form-select form-select-lg @error('fund_type_id') is-invalid @enderror" required>
                                <option value="">Select Fund Type</option>
                                @foreach($fundTypes as $fundType)
                                    <option value="{{ $fundType->id }}" {{ old('fund_type_id', $selectedFundType) == $fundType->id ? 'selected' : '' }}>
                                        {{ $fundType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The category this fund code belongs to</small>
                            @error('fund_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group position-relative">
                            <label for="division_id" class="form-label fw-semibold">
                                <i class="bx bx-building me-1 text-primary"></i>Division <span class="text-danger">*</span>
                            </label>
                            <select name="division_id" id="division_id"
                                class="form-select form-select-lg @error('division_id') is-invalid @enderror" required>
                                <option value="">Select Division</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                        {{ $division->division_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">The division this fund code is associated with</small>
                            @error('division_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="form-group position-relative">
                            <label for="code" class="form-label fw-semibold">
                                <i class="bx bx-code me-1 text-primary"></i>Fund Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg @error('code') is-invalid @enderror"
                                id="code" name="code" value="{{ old('code') }}" placeholder="Enter fund code" required>
                            <small class="text-muted mt-1 d-block">Example: AF-2025-01, USAID-2025</small>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group position-relative">
                            <label for="name" class="form-label fw-semibold">
                                <i class="bx bx-text me-1 text-primary"></i>Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror"
                                id="name" name="name" value="{{ old('name') }}" placeholder="Enter fund name" required>
                            <small class="text-muted mt-1 d-block">Descriptive name for this fund code</small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="form-group position-relative">
                        <label for="description" class="form-label fw-semibold">
                            <i class="bx bx-detail me-1 text-primary"></i>Description
                        </label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                            name="description" rows="4"
                            placeholder="Enter detailed description">{{ old('description') }}</textarea>
                        <small class="text-muted mt-1 d-block">Additional information about this fund code</small>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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