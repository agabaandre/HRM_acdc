@extends('layouts.app')

@section('title', 'Single Memos')

@section('header', 'Single Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('activities.single-memos.create') }}" class="btn btn-success">
        <i class="bx bx-plus"></i> Create Single Memo
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-md-0"><i class="bx bx-file-doc me-2 text-primary"></i>All Single Memos</h5>
            </div>
            <div class="col-md-6">
                <form class="d-flex gap-2 justify-content-md-end">
                    <div class="input-group">
                        <input type="text"
                               class="form-control"
                               id="searchInput"
                               placeholder="Search single memos...">
                        <button class="btn btn-outline-primary" type="button">
                            <i class="bx bx-search"></i>
                        </button>
                    </div>

                    <select class="form-select" id="staffFilter">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->staff_id }}">{{ $member->fname }} {{ $member->lname }}</option>
                        @endforeach
                    </select>

                    <select class="form-select" id="divisionFilter">
                        <option value="">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->division_id }}">{{ $division->division_name }}</option>
                        @endforeach
                    </select>

                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="returned">Returned</option>
                    </select>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Staff</th>
                        <th>Division</th>
                        <th>Date Range</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $count = 1; @endphp
                    @forelse($singleMemos as $memo)
                        <tr>
                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold">{{ $memo->activity_title }}</div>
                                <small class="text-muted">{{ Str::limit($memo->background, 50) }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <i class="bx bx-user me-1"></i>
                                    {{ $memo->staff->fname ?? '' }} {{ $memo->staff->lname ?? '' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <i class="bx bx-building me-1"></i>
                                    {{ $memo->matrix->division->division_name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                {{ $memo->date_from->format('Y-m-d') }}<br>
                                <small class="text-muted">to</small><br>
                                {{ $memo->date_to->format('Y-m-d') }}
                            </td>
                            <td>
                                @php
                                    $statusBadgeClass = [
                                        'draft' => 'bg-secondary',
                                        'pending' => 'bg-warning', 
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'returned' => 'bg-info',
                                    ];
                                    $statusClass = $statusBadgeClass[$memo->overall_status] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $statusClass }} text-white">
                                    {{ ucfirst($memo->overall_status ?? 'draft') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                       class="btn btn-outline-info" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    
                                    @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                        <a href="{{ route('activities.single-memos.edit', $memo) }}" 
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                        
                                        <form action="{{ route('activities.single-memos.destroy', $memo) }}" 
                                              method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" 
                                                    title="Delete" 
                                                    onclick="return confirm('Are you sure you want to delete this single memo?')">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                    
                                    <a href="{{ route('activities.single-memos.status', $memo) }}" 
                                       class="btn btn-outline-success" title="Approval Status">
                                        <i class="bx bx-check-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-file-doc fs-1 opacity-50"></i>
                                    <p class="mb-0 mt-2">No single memos found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($singleMemos->hasPages())
            <div class="d-flex justify-content-center p-3">
                {{ $singleMemos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Search functionality
    $('#searchInput').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('tbody tr').each(function() {
            const title = $(this).find('td:nth-child(2)').text().toLowerCase();
            const staff = $(this).find('td:nth-child(3)').text().toLowerCase();
            if (title.includes(searchTerm) || staff.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Filter functionality
    function applyFilters() {
        const staffFilter = $('#staffFilter').val();
        const divisionFilter = $('#divisionFilter').val();
        const statusFilter = $('#statusFilter').val();

        $('tbody tr').each(function() {
            const staff = $(this).find('td:nth-child(3)').text();
            const division = $(this).find('td:nth-child(4)').text();
            const status = $(this).find('td:nth-child(6)').text();

            let show = true;

            if (staffFilter && !staff.includes(staffFilter)) show = false;
            if (divisionFilter && !division.includes(divisionFilter)) show = false;
            if (statusFilter && !status.toLowerCase().includes(statusFilter.toLowerCase())) show = false;

            $(this).toggle(show);
        });
    }

    $('#staffFilter, #divisionFilter, #statusFilter').on('change', applyFilters);
});
</script>
@endpush
