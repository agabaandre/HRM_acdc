@extends('layouts.app')

@section('title', 'Partner Details')

@section('header', 'Partner Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('partners.edit', $partner) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit
    </a>
    <a href="{{ route('partners.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i>Partner Information</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 30%">ID:</th>
                        <td>{{ $partner->id }}</td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td>{{ $partner->name }}</td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td>{{ $partner->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Last Updated:</th>
                        <td>{{ $partner->updated_at->format('Y-m-d H:i') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-list-ol me-2 text-primary"></i>Related Fund Codes</h5>
                <a href="{{ route('fund-codes.create', ['partner_id' => $partner->id]) }}" class="btn btn-sm btn-success">
                    <i class="bx bx-plus"></i> Add Fund Code
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Activity</th>
                                <th>Division</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($partner->fundCodes as $fundCode)
                                <tr>
                                    <td>{{ $fundCode->code }}</td>
                                    <td>{{ $fundCode->activity ?? 'N/A' }}</td>
                                    <td>{{ $fundCode->division->division_name ?? 'N/A' }}</td>
                                    <td>
                                        @if($fundCode->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('fund-codes.show', $fundCode) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            <a href="{{ route('fund-codes.edit', $fundCode) }}" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-folder-open fs-1"></i>
                                            <p class="mt-2">No fund codes linked to this partner</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
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
