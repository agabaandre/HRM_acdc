@extends('layouts.app')

@section('title', 'Fund Codes')

@section('header', 'Fund Codes')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bx bx-upload me-1"></i> Upload CSV
    </button>
    <a href="{{ route('fund-codes.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Add Fund Code
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="bx bx-barcode me-2"></i> Fund Code Management
        </h5>
    </div>
    <div class="card-body py-3 px-4 bg-light">

        <div class="row g-3 align-items-end" id="fundCodeFilters" autocomplete="off">
            <form action="{{ route('fund-codes.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="search" class="form-label fw-semibold mb-1"><i class="bx bx-search me-1 text-success"></i> Search</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search fund codes..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="fund_type_id" class="form-label fw-semibold mb-1"><i class="bx bx-category me-1 text-success"></i> Fund Type</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-category"></i></span>
                        <select name="fund_type_id" class="form-select">
                            <option value="">All Fund Types</option>
                            @foreach($fundTypes as $fundType)
                                <option value="{{ $fundType->id }}" {{ request('fund_type_id') == $fundType->id ? 'selected' : '' }}>
                                    {{ $fundType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="division_id" class="form-label fw-semibold mb-1"><i class="bx bx-building me-1 text-success"></i> Division</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-building"></i></span>
                        <select name="division_id" class="form-select">
                            <option value="">All Divisions</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                    {{ $division->division_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label fw-semibold mb-1"><i class="bx bx-calendar me-1 text-success"></i> Year</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-calendar"></i></span>
                        <select name="year" id="yearFilter" class="form-select">
                            @for($yearOption = date('Y'); $yearOption >= date('Y') - 5; $yearOption--)
                                <option value="{{ $yearOption }}" {{ $year == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold mb-1"><i class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-info-circle"></i></span>
                        <select name="status" id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="mb-0 text-success fw-bold">
                        <i class="bx bx-barcode me-2"></i> Fund Codes List
                    </h6>
                    <small class="text-muted">All funding codes and activities</small>
                </div>
            </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-success">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Year</th>
                        <th>Funder</th>
                        <th>Fund Type</th>
                        <th>Division</th>
                        <th>Activity</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $count = 1; @endphp
                    @foreach($fundCodes as $fundCode)
                        <tr>
                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $fundCode->code }}</div>
                                @if($fundCode->cost_centre)
                                    <small class="text-muted">CC: {{ $fundCode->cost_centre }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <i class="bx bx-calendar me-1"></i>{{ $fundCode->year }}
                                </span>
                            </td>
                            <td>{{ $fundCode->funder->name ?? 'N/A' }}</td>
                            <td>
                                @if($fundCode->fundType)
                                    <span class="badge bg-secondary text-white">
                                        <i class="bx bx-category me-1"></i>{{ $fundCode->fundType->name }}
                                    </span>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $fundCode->division->division_name ?? 'N/A' }}</td>
                            <td>
                                @if($fundCode->activity)
                                    <div class="text-truncate" style="max-width: 200px;" title="{{ $fundCode->activity }}">
                                        {{ $fundCode->activity }}
                                    </div>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusBadgeClass = $fundCode->is_active ? 'bg-success' : 'bg-danger';
                                    $statusText = $fundCode->is_active ? 'Active' : 'Inactive';
                                @endphp
                                <span class="badge {{ $statusBadgeClass }} text-white">
                                    <i class="bx bx-{{ $fundCode->is_active ? 'check-circle' : 'x-circle' }} me-1"></i>{{ $statusText }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="{{ route('fund-codes.show', $fundCode) }}" 
                                       class="btn btn-sm btn-light action-btn text-info" 
                                       data-bs-toggle="tooltip" 
                                       title="View Details">
                                        <i class="bx bx-show fs-6"></i>
                                    </a>
                                    <a href="{{ route('fund-codes.edit', $fundCode) }}" 
                                       class="btn btn-sm btn-light action-btn text-primary" 
                                       data-bs-toggle="tooltip" 
                                       title="Edit Fund Code">
                                        <i class="bx bx-edit fs-6"></i>
                                    </a>
                                    <a href="{{ route('fund-codes.transactions', $fundCode) }}" 
                                       class="btn btn-sm btn-light action-btn text-success" 
                                       data-bs-toggle="tooltip" 
                                       title="View Transactions">
                                        <i class="bx bx-history fs-6"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    @if($fundCodes->hasPages())
        <div class="card-footer">
            {{ $fundCodes->appends(request()->except('page'))->links() }}
        </div>
    @endif
</div>
@endsection

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">
                    <i class="bx bx-upload me-2"></i> Upload Fund Codes via CSV
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('fund-codes.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Instructions:</strong> Upload a CSV file with fund code data. Download the template below for the correct format.
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-semibold">
                            <i class="bx bx-file me-1 text-success"></i> CSV File <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control @error('csv_file') is-invalid @enderror" 
                               id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Only CSV files are allowed. Maximum file size: 5MB</small>
                        @error('csv_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
                            <label class="form-check-label" for="skip_duplicates">
                                Skip duplicate fund codes (based on code and year)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="validate_only" name="validate_only" value="1">
                            <label class="form-check-label" for="validate_only">
                                Validate only (don't import data)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('fund-codes.download-template') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-download me-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#csvFormat">
                            <i class="bx bx-help-circle me-1"></i> View Format
                        </button>
                    </div>

                    <div class="collapse mt-3" id="csvFormat">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="fw-bold">CSV Format Requirements:</h6>
                                <p class="mb-2"><strong>Required columns:</strong> funder_id, year, code, fund_type_id</p>
                                <p class="mb-2"><strong>Optional columns:</strong> activity, division_id, cost_centre, amert_code, fund, budget_balance, approved_budget, uploaded_budget, is_active</p>
                                <p class="mb-0"><strong>Note:</strong> Division is required for intramural fund types only.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="uploadBtn">
                        <i class="bx bx-upload me-1"></i> Upload & Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle file upload form
        $('#uploadForm').on('submit', function(e) {
            const fileInput = $('#csv_file')[0];
            const uploadBtn = $('#uploadBtn');
            
            if (fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select a CSV file to upload.');
                return;
            }

            // Show loading state
            uploadBtn.prop('disabled', true);
            uploadBtn.html('<i class="bx bx-loader-alt bx-spin me-1"></i> Processing...');
        });

        // File size validation
        $('#csv_file').on('change', function() {
            const file = this.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file && file.size > maxSize) {
                alert('File size must be less than 5MB.');
                this.value = '';
            }
        });
    });
</script>
@endpush
