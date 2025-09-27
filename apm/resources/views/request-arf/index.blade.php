@extends('layouts.app')

@section('title', 'ActRF')

@section('header', 'Request for ARF')

@section('header-actions')
<!-- Create functionality removed - requests will be handled from activities -->
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> ARF Request Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="arfFilters" autocomplete="off">
            <form action="{{ route('request-arf.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="division_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-building me-1 text-success"></i> Division</label>
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
                    <label for="staff_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-user me-1 text-success"></i> Staff</label>
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
                    <label for="overall_status" class="form-label fw-semibold mb-1"><i
                            class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <select name="overall_status" id="overall_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('overall_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
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
                    <a href="{{ route('request-arf.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </div>
                </form>
            </div>
        </div>
    </div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="arfTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted ARFs
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedArfs->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allArfs-tab" data-bs-toggle="tab" data-bs-target="#allArfs" type="button" role="tab" aria-controls="allArfs" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All ARF Requests
                        <span class="badge bg-primary text-white ms-2">{{ $allArfs->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="arfTabsContent">
            <!-- My Submitted ARFs Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted ARF Requests
                            </h6>
                            <small class="text-muted">All ARF requests you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('request-arf.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @if($mySubmittedArfs && $mySubmittedArfs->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th>#</th>
                                        <th>ARF Number</th>
                                        <th>Title</th>
                                        <th>Division</th>
                                        <th>Request Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($mySubmittedArfs as $arf)
                        <tr>
                                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $arf->arf_number }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-primary">{{ $arf->activity_title }}</div>
                            </td>
                            <td>{{ $arf->actual_division->division_name ?? 'N/A' }}</td>
                            <td>{{ $arf->request_date ? \Carbon\Carbon::parse($arf->request_date)->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <span class="fw-bold text-success">
                                    ${{ number_format($arf->requested_amount, 2) }}
                                </span>
                            </td>
                            <td>
                                {!! display_memo_status_auto($arf) !!}
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('request-arf.show', $arf) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($arf->overall_status === 'draft' || $arf->overall_status === 'returned')
                                        <a href="{{ route('request-arf.edit', $arf) }}" class="btn btn-outline-warning btn-sm" title="Edit">
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
                            <i class="bx bx-file-alt display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No ARF Requests Found</h5>
                            <p class="text-muted">You haven't submitted any ARF requests yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- All ARF Requests Tab -->
            @if(in_array(87, user_session('permissions', [])))
            <div class="tab-pane fade" id="allArfs" role="tabpanel" aria-labelledby="allArfs-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-grid me-2"></i> All ARF Requests
                            </h6>
                            <small class="text-muted">All ARF requests in the system</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('request-arf.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @if($allArfs && $allArfs->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>ARF Number</th>
                                        <th>Title</th>
                                        <th>Staff</th>
                                        <th>Division</th>
                                        <th>Request Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($allArfs as $arf)
                        <tr>
                                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $arf->arf_number }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-primary">{{ $arf->activity_title }}</div>
                            </td>
                            <td>{{ $arf->staff->name ?? 'N/A' }}</td>
                            <td>{{ $arf->actual_division->division_name ?? 'N/A' }}</td>
                            <td>{{ $arf->request_date ? \Carbon\Carbon::parse($arf->request_date)->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <span class="fw-bold text-success">
                                    ${{ number_format($arf->requested_amount, 2) }}
                                </span>
                            </td>
                            <td>
                                {!! display_memo_status_auto($arf) !!}
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('request-arf.show', $arf) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($arf->overall_status === 'draft' || $arf->overall_status === 'returned')
                                        <a href="{{ route('request-arf.edit', $arf) }}" class="btn btn-outline-warning btn-sm" title="Edit">
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
                            <i class="bx bx-file-alt display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No ARF Requests Found</h5>
                            <p class="text-muted">No ARF requests have been submitted yet.</p>
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
    $('#division_id, #staff_id').select2({
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });
});
</script>
@endpush