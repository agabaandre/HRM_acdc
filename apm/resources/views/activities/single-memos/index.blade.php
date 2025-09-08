@extends('layouts.app')

@section('title', 'Single Memos')

@section('header', 'Single Memos')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-md-0"><i class="bx bx-file-doc me-2 text-primary"></i>Single Memos</h5>
            </div>
            <div class="col-md-6">
                <form class="d-flex gap-2 justify-content-md-end" id="filterForm">
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
        
        <!-- Tabs -->
        <div class="mt-3">
            <ul class="nav nav-tabs" id="memoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="my-memos-tab" data-bs-toggle="tab" data-bs-target="#my-memos" type="button" role="tab">
                        <i class="bx bx-user me-2"></i>My Single Memos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="all-memos-tab" data-bs-toggle="tab" data-bs-target="#all-memos" type="button" role="tab">
                        <i class="bx bx-list-ul me-2"></i>All Single Memos
                    </button>
                </li>
            </ul>
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
        
        <!-- Tab Content -->
        <div class="tab-content" id="memoTabContent">
            <!-- My Single Memos Tab -->
            <div class="tab-pane fade show active" id="my-memos" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="myMemosTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Memo Ref</th>
                                <th>Title</th>
                                <th>Division</th>
                                <th>Date Range</th>
                                <th>Request Type</th>
                                <th>Current Level</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $myMemos = $singleMemos->where('staff_id', user_session('staff_id'));
                                $count = 1; 
                            @endphp
                            @forelse($myMemos as $memo)
                                <tr>
                                    <td>{{ $count++ }}</td>
                                    <td>
                                        <small class="text-primary fw-bold">{{ $memo->activity_ref ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ $memo->activity_title }}</div>
                                        <small class="text-muted">{{ Str::limit($memo->background, 50) }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <i class="bx bx-building me-1"></i>
                                            {{ $memo->matrix->division->division_name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                            <span class="text-muted">to</span><br>
                                            {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-info">{{ $memo->requestType->name ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        @if($memo->overall_status != 'approved')
                                            <small class="text-primary">{{ $memo->approval_level_display }}</small>
                                        @else
                                            <small class="text-success"><i class="bx bx-check"></i> Approved</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $memo->status_badge_class }} text-white">
                                            {{ ucfirst($memo->overall_status ?? 'draft') }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $memo->created_at->format('M d, Y') }}</small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                               class="btn btn-outline-info" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            
                                            @if($memo->overall_status === 'draft')
                                                <a href="{{ route('activities.single-memos.edit', $memo,$memo->matrix) }}" 
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-user-x fs-1 opacity-50"></i>
                                            <p class="mb-0 mt-2">You haven't created any single memos yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- All Single Memos Tab -->
            <div class="tab-pane fade" id="all-memos" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="allMemosTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Memo Ref</th>
                                <th>Title</th>
                                <th>Staff</th>
                                <th>Division</th>
                                <th>Date Range</th>
                                <th>Request Type</th>
                                <th>Current Level</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $count = 1; @endphp
                            @forelse($singleMemos as $memo)
                                <tr>
                                    <td>{{ $count++ }}</td>
                                    <td>
                                        <small class="text-primary fw-bold">{{ $memo->activity_ref ?? 'N/A' }}</small>
                                    </td>
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
                                        <small>
                                            {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                            <span class="text-muted">to</span><br>
                                            {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-info">{{ $memo->requestType->name ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        @if($memo->overall_status != 'approved')
                                            <small class="text-primary">{{ $memo->approval_level_display }}</small>
                                        @else
                                            <small class="text-success"><i class="bx bx-check"></i> Approved</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $memo->status_badge_class }} text-white">
                                            {{ ucfirst($memo->overall_status ?? 'draft') }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $memo->created_at->format('M d, Y') }}</small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                               class="btn btn-outline-info" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            
                                            @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                                <a href="{{ route('activities.single-memos.edit', $memo,$memo->matrix) }}" 
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
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4">
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
            </div>
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
