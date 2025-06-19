@extends('layouts.app')

@section('title', 'Fund Codes')

@section('header', 'Fund Codes')

@section('header-actions')
<a href="{{ route('fund-codes.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add Fund Code
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-3"><i class="bx bx-list-ul me-2 text-primary"></i>Fund Codes Management</h5>
        
        <form action="{{ route('fund-codes.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="fund_type_id" class="form-select">
                    <option value="">All Fund Types</option>
                    @foreach($fundTypes as $fundType)
                        <option value="{{ $fundType->id }}" {{ request('fund_type_id') == $fundType->id ? 'selected' : '' }}>
                            {{ $fundType->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="division_id" class="form-select">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                            {{ $division->division_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bx bx-filter-alt"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Year</th>
                        <th>Funder</th>
                        <th>Fund Type</th>
                        <th>Division</th>
                        <th>Activity</th>
                        <th>Cost Centre</th>
                        <th>Amert Code</th>
                        <th>Fund</th>
                        <th>Budget Balance</th>
                        <th>Approved Budget</th>
                        <th>Uploaded Budget</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fundCodes as $fundCode)
                        <tr>
                            <td><strong>{{ $fundCode->code }}</strong></td>
                            <td>{{ $fundCode->year }}</td>
                            <td>{{ $fundCode->funder->name ?? 'N/A' }}</td>
                            <td>{{ $fundCode->fundType->name ?? 'N/A' }}</td>
                            <td>{{ $fundCode->division->division_name ?? 'N/A' }}</td>
                            <td>{{ $fundCode->activity }}</td>
                            <td>{{ $fundCode->cost_centre }}</td>
                            <td>{{ $fundCode->amert_code }}</td>
                            <td>{{ $fundCode->fund }}</td>
                            <td>{{ $fundCode->budget_balance }}</td>
                            <td>{{ $fundCode->approved_budget }}</td>
                            <td>{{ $fundCode->uploaded_budget }}</td>
                            <td>
                                @if($fundCode->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('fund-codes.show', $fundCode) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2">No fund codes found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush
