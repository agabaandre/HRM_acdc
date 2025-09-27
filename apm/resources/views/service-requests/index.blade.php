@extends('layouts.app')

@section('title', 'Service Requests')

@section('header', 'Service Requests')

@section('header-actions')
<!-- Create functionality removed - requests will be handled from activities -->
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-cog me-2 text-success"></i> Service Request Management</h4>
        </div>

        <form action="{{ route('service-requests.index') }}" method="GET" class="row g-3 align-items-end w-100" id="serviceFilters" autocomplete="off">
            <div class="col-md-2">
                <label for="division_id" class="form-label fw-semibold mb-1">
                    <i class="bx bx-building me-1 text-success"></i> Division
                </label>
                <select name="division_id" id="division_id" class="form-select">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                            {{ $division->division_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="staff_id" class="form-label fw-semibold mb-1">
                    <i class="bx bx-user me-1 text-success"></i> Staff
                </label>
                <select name="staff_id" id="staff_id" class="form-select">
                    <option value="">All Staff</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="overall_status" class="form-label fw-semibold mb-1">
                    <i class="bx bx-info-circle me-1 text-success"></i> Status
                </label>
                <select name="overall_status" id="overall_status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('overall_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending" {{ request('overall_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('overall_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="returned" {{ request('overall_status') == 'returned' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                    <i class="bx bx-search-alt-2 me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                    <i class="bx bx-reset me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="serviceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted Requests
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedRequests->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allRequests-tab" data-bs-toggle="tab" data-bs-target="#allRequests" type="button" role="tab" aria-controls="allRequests" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Service Requests
                        <span class="badge bg-primary text-white ms-2">{{ $allRequests->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="serviceTabsContent">
            <!-- My Submitted Requests Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Service Requests
                            </h6>
                            <small class="text-muted">All service requests you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('service-requests.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @if($mySubmittedRequests && $mySubmittedRequests->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 120px;">Request Number</th>
                                        <th style="width: 120px;">Document Number</th>
                                        <th style="width: 280px;">Title</th>
                                        <th style="width: 120px;">Staff</th>
                                        <th style="width: 120px;">Division</th>
                                        <th style="width: 100px;">Request Date</th>
                                        <th style="width: 100px;">Total Budget</th>
                                        <th style="width: 150px;">Status</th>
                                        <th class="text-center" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($mySubmittedRequests as $request)
                        <tr>
                                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $request->request_number }}</div>
                            </td>
                            <td style="width: 120px;">
                                <div class="text-muted small">{{ $request->document_number ?? 'N/A' }}</div>
                            </td>
                            <td style="width: 280px;">
                                <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $request->title ?? 'N/A' }}">{{ $request->title ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                            <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                <div>{{ $request->division->division_name ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <span class="fw-bold text-success">
                                    ${{ number_format($request->new_total_budget ?? 0, 2) }}
                                </span>
                            </td>
                            <td>
                                {!! display_memo_status_auto($request) !!}
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('service-requests.show', $request) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($request->overall_status === 'draft' || $request->overall_status === 'returned')
                                        <a href="{{ route('service-requests.edit', $request) }}" class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                                    @endforeach
                </tbody>
            </table>
        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bx bx-cog display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No Service Requests Found</h5>
                            <p class="text-muted">You haven't submitted any service requests yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- All Service Requests Tab -->
            @if(in_array(87, user_session('permissions', [])))
            <div class="tab-pane fade" id="allRequests" role="tabpanel" aria-labelledby="allRequests-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-grid me-2"></i> All Service Requests
                            </h6>
                            <small class="text-muted">All service requests in the system</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('service-requests.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @if($allRequests && $allRequests->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 120px;">Request Number</th>
                                        <th style="width: 120px;">Document Number</th>
                                        <th style="width: 280px;">Title</th>
                                        <th style="width: 120px;">Staff</th>
                                        <th style="width: 120px;">Division</th>
                                        <th style="width: 100px;">Request Date</th>
                                        <th style="width: 100px;">Total Budget</th>
                                        <th style="width: 150px;">Status</th>
                                        <th class="text-center" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($allRequests as $request)
                        <tr>
                                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $request->request_number }}</div>
                            </td>
                            <td style="width: 120px;">
                                <div class="text-muted small">{{ $request->document_number ?? 'N/A' }}</div>
                            </td>
                            <td style="width: 280px;">
                                <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $request->title ?? 'N/A' }}">{{ $request->title ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                            <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                <div>{{ $request->division->division_name ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <span class="fw-bold text-success">
                                    ${{ number_format($request->new_total_budget ?? 0, 2) }}
                                </span>
                            </td>
                            <td>
                                {!! display_memo_status_auto($request) !!}
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('service-requests.show', $request) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($request->overall_status === 'draft' || $request->overall_status === 'returned')
                                        <a href="{{ route('service-requests.edit', $request) }}" class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                                    @endforeach
                </tbody>
            </table>
        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bx bx-cog display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No Service Requests Found</h5>
                            <p class="text-muted">No service requests have been submitted yet.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2 for better dropdowns
    $('#division_id, #staff_id, #overall_status').select2({
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });
});
</script>
@endpush