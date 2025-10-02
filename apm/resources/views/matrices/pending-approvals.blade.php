@extends('layouts.app')

@section('title', 'Matrix Pending Approvals')
@section('header', 'Matrix Pending Approvals')

@section('header-actions')
    <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrices
    </a>
@endsection

@section('content')
    @if(isset($error))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bx bx-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <div>
                    <h4 class="mb-0 text-success fw-bold">
                        <i class="bx bx-time me-2 text-success"></i> Matrix Approval Management
                    </h4>
                    <small class="text-muted">Showing matrices at your current approval level</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6">
                        <i class="bx bx-time me-1"></i>
                        {{ $pendingMatrices->count() }} Pending
                    </div>
                </div>
            </div>

            <div class="row g-3 align-items-end" id="matrixFilters" autocomplete="off">
                <div class="col-12 mb-2">
                    <small class="text-muted">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>Note:</strong> These filters apply to matrices currently at your approval level (excluding draft matrices).
                    </small>
                </div>
                <div class="col-md-2">
                    <label for="yearFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-calendar me-1 text-success"></i> Year
                    </label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-calendar"></i></span>
                        <select class="form-select" id="yearFilter">
                            <option value="">All Years</option>
                            @foreach (range(date('Y'), date('Y') - 5) as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="quarterFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-time-five me-1 text-success"></i> Quarter
                    </label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-time-five"></i></span>
                        <select class="form-select" id="quarterFilter">
                            <option value="">All Quarters</option>
                            @foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter)
                                <option value="{{ $quarter }}">{{ $quarter }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="divisionFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-building me-1 text-success"></i> Division
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="divisionFilter">
                            <option value="">All Divisions</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="focalFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user-pin me-1 text-success"></i> Focal Person
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="focalFilter">
                            <option value="">All Focal Persons</option>
                            @foreach ($focalPersons as $person)
                                <option value="{{ $person->staff_id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="approvalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                        <i class="bx bx-time me-2"></i> Pending Approval
                        <span class="badge bg-warning text-dark ms-2">{{ $pendingMatrices->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                        <i class="bx bx-check-double me-2"></i> Approved by Me
                        <span class="badge bg-success ms-2">{{ $approvedByMe->count() }}</span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="approvalTabsContent">
                <!-- Pending Approval Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-warning fw-bold">
                                    <i class="bx bx-time me-2"></i> Pending Approval
                                </h6>
                                <small class="text-muted">Matrices currently at YOUR approval level (not in draft mode)</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.pending-approvals-csv') }}" class="btn btn-outline-warning btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @if($pendingMatrices->count() > 0)
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Filtered Results:</strong> Showing {{ $pendingMatrices->count() }} matrices that are currently at your approval level and require your action.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="pendingTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th>#</th>
                                            <th>Year</th>
                                            <th>Quarter</th>
                                            <th>Division</th>
                                            <th>Focal Person</th>       
                                            <th>Key Result Areas</th>
                                            <th>Activities</th>
                
                                            <th>Level</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>    
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($pendingMatrices->currentPage() - 1) * $pendingMatrices->perPage() + 1; @endphp
                                        @foreach($pendingMatrices as $matrix)
                                            <tr>    
                                                <td>{{ $count }}</td>
                                                <td>{{ $matrix->year }}</td>
                                                <td>{{ $matrix->quarter }}</td>
                                                <td>{{ $matrix->division->division_name ?? 'N/A' }}</td>
                                                <td>{{ $matrix->focalPerson->name ?? 'N/A' }}</td>
                                                <td>    
                                                    @php
                                                        $kras = is_string($matrix->key_result_area)
                                                            ? json_decode($matrix->key_result_area, true)
                                                            : $matrix->key_result_area;
                                                    @endphp
                                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal"  
                                                        data-bs-target="#kraModal{{ $matrix->id }}">
                                                        <i class="bx bx-list-check me-1"></i> {{ is_array($kras) ? count($kras) : 0 }}
                                                        Area(s)
                                                    </button>

                                                    <!-- Modal -->
                                                    <div class="modal fade" id="kraModal{{ $matrix->id }}" tabindex="-1"
                                                        aria-labelledby="kraModalLabel{{ $matrix->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="kraModalLabel{{ $matrix->id }}">
                                                                        Key Result Areas - {{ $matrix->year }} {{ $matrix->quarter }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    @if (is_array($kras) && count($kras))
                                                                        <ul class="list-group">
                                                                            @foreach ($kras as $kra)
                                                                                <li class="list-group-item">
                                                                                    <i class="bx bx-check-circle text-success me-2"></i>
                                                                                    {{ $kra['description'] ?? '' }}
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        <p class="text-muted">No key result areas defined.</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @php
                                                        $activities = $matrix->activities;
                                                    @endphp
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                        data-bs-target="#activitiesModal{{ $matrix->id }}">
                                                        <i class="bx bx-list-ul me-1"></i> {{ $activities->count() }} Activity(ies)
                                                    </button>
                                                    <!-- Modal -->
                                                    <div class="modal fade" id="activitiesModal{{ $matrix->id }}" tabindex="-1"
                                                        aria-labelledby="activitiesModalLabel{{ $matrix->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="activitiesModalLabel{{ $matrix->id }}">
                                                                        Activities - {{ $matrix->year }} {{ $matrix->quarter }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    @if ($activities->count())
                                                                        <ul class="list-group">
                                                                            @php $actCount = 1; @endphp
                                                                            @foreach ($activities as $activity)
                                                                                <li class="list-group-item">
                                                                                    <span class="fw-bold">{{ $actCount++ }}.</span> <i
                                                                                        class="bx bx-chevron-right text-primary me-2"></i>
                                                                                    {{ $activity->activity_title ?? 'Untitled Activity' }}
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        <p class="text-muted">No activities defined.</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $matrix->overall_status == 'approved' ? 'Registry' : ($matrix->workflow_definition ? $matrix->workflow_definition->role : 'Focal Person') }}
                                                    <small
                                                        class="text-muted">{{ $matrix->current_actor ? '(' . $matrix->current_actor->fname . ' ' . $matrix->current_actor->lname . ')' : '' }}</small>
                                                </td>
                                                <td> <span
                                                        class="p-1 rounded {{ config('approval_states')[$matrix->overall_status] ?? 'bg-secondary' }}">{{ strtoupper($matrix->overall_status) }}</span>
                                                </td>
                                                <td class="text-left">
                                                    <div class="btn-group">
                                                        <a href="{{ route('matrices.show', $matrix) }}"
                                                            class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if (still_with_creator($matrix))   
                                                            <a href="{{ route('matrices.edit', $matrix) }}"
                                                                class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @php $count++; @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            @if($pendingMatrices instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingMatrices->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $pendingMatrices->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-5">
                                <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
                                <h5 class="mt-3 text-muted">No Matrices at Your Approval Level</h5>
                                <p class="text-muted">You have no matrices currently waiting for your specific approval. This could mean:</p>
                                <ul class="text-muted text-start d-inline-block">
                                    <li>All matrices are still in draft mode</li>
                                    <li>Matrices are at different approval levels</li>
                                    <li>You're not assigned as an approver for current workflows</li>
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Approved by Me Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-check-double me-2"></i> Approved by Me
                                </h6>
                                <small class="text-muted">Matrices you have approved or acted upon</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.approved-by-me-csv') }}" class="btn btn-outline-success btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @if($approvedByMe->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="approvedTable">
                                    <thead class="table-success">
                                        <tr>
                                            <th>#</th>
                                            <th>Year</th>
                                            <th>Quarter</th>
                                            <th>Division</th>
                                            <th>Focal Person</th>       
                                            <th>Key Result Areas</th>
                                            <th>Activities</th>
                                            <th>Level</th>
                                            <th>Status</th>
                                            <th>Your Action</th>
                                            <th>Action Date</th>
                                            <th class="text-center">Actions</th>    
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                                        @foreach($approvedByMe as $matrix)
                                            @php
                                                $myApproval = $matrix->approvalTrails->where('staff_id', user_session('staff_id'))->first();
                                            @endphp
                                            <tr>    
                                                <td>{{ $count }}</td>
                                                <td>{{ $matrix->year }}</td>
                                                <td>{{ $matrix->quarter }}</td>
                                                <td>{{ $matrix->division->division_name ?? 'N/A' }}</td>
                                                <td>{{ $matrix->focalPerson->name ?? 'N/A' }}</td>
                                                <td>    
                                                    @php
                                                        $kras = is_string($matrix->key_result_area)
                                                            ? json_decode($matrix->key_result_area, true)
                                                            : $matrix->key_result_area;
                                                    @endphp
                                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal"  
                                                        data-bs-target="#kraModalApproved{{ $matrix->id }}">
                                                        <i class="bx bx-list-check me-1"></i> {{ is_array($kras) ? count($kras) : 0 }}
                                                        Area(s)
                                                    </button>

                                                    <!-- Modal -->
                                                    <div class="modal fade" id="kraModalApproved{{ $matrix->id }}" tabindex="-1"
                                                        aria-labelledby="kraModalApprovedLabel{{ $matrix->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="kraModalApprovedLabel{{ $matrix->id }}">
                                                                        Key Result Areas - {{ $matrix->year }} {{ $matrix->quarter }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    @if (is_array($kras) && count($kras))
                                                                        <ul class="list-group">
                                                                            @foreach ($kras as $kra)
                                                                                <li class="list-group-item">
                                                                                    <i class="bx bx-check-circle text-success me-2"></i>
                                                                                    {{ $kra['description'] ?? '' }}
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        <p class="text-muted">No key result areas defined.</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @php
                                                        $activities = $matrix->activities;
                                                    @endphp
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                        data-bs-target="#activitiesModalApproved{{ $matrix->id }}">
                                                        <i class="bx bx-list-ul me-1"></i> {{ $activities->count() }} Activity(ies)
                                                    </button>
                                                    <!-- Modal -->
                                                    <div class="modal fade" id="activitiesModalApproved{{ $matrix->id }}" tabindex="-1"
                                                        aria-labelledby="activitiesModalApprovedLabel{{ $matrix->id }}" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="activitiesModalApprovedLabel{{ $matrix->id }}">
                                                                        Activities - {{ $matrix->year }} {{ $matrix->quarter }}
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    @if ($activities->count())
                                                                        <ul class="list-group">
                                                                            @php $actCount = 1; @endphp
                                                                            @foreach ($activities as $activity)
                                                                                <li class="list-group-item">
                                                                                    <span class="fw-bold">{{ $actCount++ }}.</span> <i
                                                                                        class="bx bx-chevron-right text-primary me-2"></i>
                                                                                    {{ $activity->activity_title ?? 'Untitled Activity' }}
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    @else
                                                                        <p class="text-muted">No activities defined.</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $matrix->overall_status == 'approved' ? 'Registry' : ($matrix->workflow_definition ? $matrix->workflow_definition->role : 'Focal Person') }}
                                                    <small
                                                        class="text-muted">{{ $matrix->current_actor ? '(' . $matrix->current_actor->fname . ' ' . $matrix->current_actor->lname . ')' : '' }}</small>
                                                </td>
                                                <td> <span
                                                        class="p-1 rounded {{ config('approval_states')[$matrix->overall_status] ?? 'bg-secondary' }}">{{ strtoupper($matrix->overall_status) }}</span>
                                                </td>
                                                <td>
                                                    @if($myApproval)
                                                        <span class="badge bg-{{ $myApproval->action === 'approved' ? 'success' : ($myApproval->action === 'rejected' ? 'danger' : 'warning') }}">
                                                            {{ ucfirst($myApproval->action ?? 'Unknown') }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Unknown</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($myApproval && $myApproval->created_at)
                                                        {{ $myApproval->created_at->format('M d, Y H:i') }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('matrices.show', $matrix) }}"
                                                            class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @php $count++; @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            @if($approvedByMe instanceof \Illuminate\Pagination\LengthAwarePaginator && $approvedByMe->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $approvedByMe->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-5">
                                <i class="bx bx-info-circle text-info" style="font-size: 4rem;"></i>
                                <h5 class="mt-3 text-muted">No Approved Matrices</h5>
                                <p class="text-muted">You haven't approved any matrices yet.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2 for filters
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Filter functionality
    $('#applyFilters').click(function() {
        applyFilters();
    });

    // Filter on Enter key
    $('#matrixFilters select').keypress(function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });

    function applyFilters() {
        const year = $('#yearFilter').val();
        const quarter = $('#quarterFilter').val();
        const division = $('#divisionFilter').val();
        const focal = $('#focalFilter').val();

        // Filter pending table
        filterTable('#pendingTable', year, quarter, division, focal);
        
        // Filter approved table
        filterTable('#approvedTable', year, quarter, division, focal);
    }

    function filterTable(tableId, year, quarter, division, focal) {
        $(tableId + ' tbody tr').each(function() {
            let show = true;
            const row = $(this);

            if (year && row.find('td:eq(1)').text() !== year) show = false;
            if (quarter && row.find('td:eq(2)').text() !== quarter) show = false;
            if (division && row.find('td:eq(3)').text() !== $('#divisionFilter option:selected').text()) show = false;
            if (focal && row.find('td:eq(4)').text() !== $('#focalFilter option:selected').text()) show = false;

            row.toggle(show);
        });
    }

    // Clear filters
    function clearFilters() {
        $('#yearFilter, #quarterFilter, #divisionFilter, #focalFilter').val('').trigger('change');
        $(tableId + ' tbody tr').show();
    }
});
</script>
@endpush
