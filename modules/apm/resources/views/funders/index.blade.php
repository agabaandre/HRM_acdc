@extends('layouts.app')

@section('title', 'Funders')

@section('header', 'Funders')

@section('header-actions')
<a wire:navigate href="{{ route('funders.create') }}" class="btn btn-success shadow-sm">
    <i class="bx bx-plus-circle me-1"></i> Add Funder
</a>
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-building me-2 text-success"></i> Funder Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="funderFilters" autocomplete="off">
            <form id="funders-filter-form" class="row g-3 align-items-end w-100" role="search">
                <div class="col-md-3">
                    <label for="search" class="form-label fw-semibold mb-1"><i class="bx bx-search me-1 text-success"></i> Search</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search funders..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold mb-1"><i class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-info-circle"></i></span>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label fw-semibold mb-1"><i class="bx bx-calendar me-1 text-success"></i> Year</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-calendar"></i></span>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a wire:navigate href="{{ route('funders.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </div>
            </form>
            <a id="funders-filter-navigate" wire:navigate href="{{ route('funders.index') }}" class="d-none" aria-hidden="true"></a>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="mb-0 text-success fw-bold">
                        <i class="bx bx-building me-2"></i> Funders List
                    </h6>
                    <small class="text-muted">All funding organizations</small>
                </div>
            </div>

            @if($funders && $funders->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-success">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Fund Codes</th>
                            <th>Activity Code</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $count = 1; @endphp
                        @foreach($funders as $funder)
                            <tr>
                                <td>{{ $count++ }}</td>
                                <td>
                                    <div class="fw-bold text-primary">{{ $funder->name }}</div>
                                    @if($funder->description)
                                        <small class="text-muted">{{ Str::limit($funder->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>{{ $funder->contact_person ?? 'N/A' }}</td>
                                <td>
                                    @if($funder->email)
                                        <a href="mailto:{{ $funder->email }}" class="text-decoration-none">
                                            <i class="bx bx-envelope me-1"></i>{{ $funder->email }}
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    @if($funder->phone)
                                        <a href="tel:{{ $funder->phone }}" class="text-decoration-none">
                                            <i class="bx bx-phone me-1"></i>{{ $funder->phone }}
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <i class="bx bx-barcode me-1"></i>{{ $funder->fundCodes()->count() }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $activityCodeBadge = $funder->show_activity_code ? 'bg-primary' : 'bg-secondary';
                                        $activityCodeText = $funder->show_activity_code ? 'Yes' : 'No';
                                    @endphp
                                    <span class="badge {{ $activityCodeBadge }} text-white">
                                        <i class="bx bx-{{ $funder->show_activity_code ? 'check' : 'minus' }}-circle me-1"></i>{{ $activityCodeText }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $statusBadgeClass = $funder->is_active ? 'bg-success' : 'bg-danger';
                                        $statusText = $funder->is_active ? 'Active' : 'Inactive';
                                    @endphp
                                    <span class="badge {{ $statusBadgeClass }} text-white">
                                        <i class="bx bx-{{ $funder->is_active ? 'check-circle' : 'x-circle' }} me-1"></i>{{ $statusText }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a wire:navigate href="{{ route('funders.show', $funder) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                            <i class="bx bx-show me-1"></i>View
                                        </a>
                                        <a wire:navigate href="{{ route('funders.edit', $funder) }}" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bx bx-edit me-1"></i>Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-center py-5">
                    <div class="text-muted">
                        <i class="bx bx-building fs-1"></i>
                        <p class="mt-2">No funders found</p>
                        <a wire:navigate href="{{ route('funders.create') }}" class="btn btn-success">
                            <i class="bx bx-plus me-1"></i> Add First Funder
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if($funders->hasPages())
        <div class="card-footer">
            {{ $funders->appends(request()->except('page'))->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    function initFundersIndex() {
        var form = document.getElementById('funders-filter-form');
        var navLink = document.getElementById('funders-filter-navigate');
        if (form && navLink) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var base = '{{ route('funders.index') }}';
                var params = new URLSearchParams();
                var search = (form.querySelector('[name="search"]') || {}).value;
                var status = (form.querySelector('[name="status"]') || {}).value;
                var year = (form.querySelector('[name="year"]') || {}).value;
                if (search && search.trim()) params.set('search', search.trim());
                if (status) params.set('status', status);
                if (year) params.set('year', year);
                navLink.href = params.toString() ? (base + '?' + params.toString()) : base;
                navLink.click();
            });
        }
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
            if (!el._tooltip) new bootstrap.Tooltip(el);
        });
    }
    if (document.readyState === 'complete') initFundersIndex();
    else document.addEventListener('DOMContentLoaded', initFundersIndex);
    document.addEventListener('livewire:navigated', initFundersIndex);
})();
</script>
@endpush
