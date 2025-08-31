@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')
@section('header', 'Quarterly Travel Matrices')

@section('header-actions')
    @php $isFocal = isfocal_person(); @endphp

@endsection

@php
    //dd($matrices->toArray());
@endphp



@section('content')
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">

            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <h4 class="mb-0 text-success fw-bold"><i class="bx bx-grid-alt me-2 text-success"></i> Matrix Details</h4>
                @if ($isFocal)
                    <a href="{{ route('matrices.create') }}" class="btn btn-success">
                        <i class="bx bx-plus"></i> Create New Matrix
                    </a>
                @endif
            </div>

            <div class="row g-3 align-items-end" id="matrixFilters" autocomplete="off">
                <div class="col-md-2">
                    <label for="yearFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-calendar me-1 text-success"></i> Year</label>
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
                    <label for="quarterFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-time-five me-1 text-success"></i> Quarter</label>
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
                    <label for="divisionFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-building me-1 text-success"></i> Division</label>
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
                    <label for="focalFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-user-pin me-1 text-success"></i> Focal Person</label>
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
            <ul class="nav nav-tabs nav-fill" id="matrixTabs" role="tablist">
                @if($myDivisionMatrices->count() > 0)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="true">
                            <i class="bx bx-home me-2"></i> My Division Matrices 
                            <span class="badge bg-success text-dark ms-2">{{ $myDivisionMatrices->count() }}</span>
                        </button>
                    </li>
                @endif
                @if(in_array(87, user_session('permissions', [])))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $myDivisionMatrices->count() == 0 ? 'active' : '' }}" id="allMatrices-tab" data-bs-toggle="tab" data-bs-target="#allMatrices" type="button" role="tab" aria-controls="allMatrices" aria-selected="{{ $myDivisionMatrices->count() == 0 ? 'true' : 'false' }}">
                            <i class="bx bx-grid me-2"></i> All Matrices
                            <span class="badge bg-primary text-white ms-2">{{ $allMatrices->count() ?? 0 }}</span>
                        </button>
                    </li>
                @endif
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="matrixTabsContent">
                <!-- My Division Matrices Tab -->
                @if($myDivisionMatrices->count() > 0)
                <div class="tab-pane fade show active" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-home me-2"></i> My Division Matrices
                                </h6>
                                <small class="text-muted">All Matrices in your division</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.division-csv') }}" class="btn btn-outline-success btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @if($myDivisionMatrices->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
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
                                        @php $count = 1; @endphp
                                        @foreach($myDivisionMatrices as $matrix)
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
                                                        <div class="modal-dialog modal-md modal-dialog-centered">
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
                                                        <div class="modal-dialog modal-md modal-dialog-centered">
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
                    
                                                <td>{{ $matrix->overall_status == 'approved' ? 'Registry' : (($matrix->workflow_definition && $matrix->approval_level>0) ? $matrix->workflow_definition->role : 'Focal Person') }}
                                                    <small
                                                        class="text-muted">{{ $matrix->current_actor ? '(' . $matrix->current_actor->fname . ' ' . $matrix->current_actor->lname . ')' : '' }}</small>
                                                </td>
                                                <td> <span
                                                        class="p-1 rounded {{ config('approval_states')[$matrix->overall_status] }}">{{ strtoupper($matrix->overall_status) }}</span>
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
                            @if($myDivisionMatrices instanceof \Illuminate\Pagination\LengthAwarePaginator && $myDivisionMatrices->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $myDivisionMatrices->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-check-circle fs-1 text-success opacity-50"></i>
                                <p class="mb-0">No matrices found in your division.</p>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- All Matrices Tab -->
                @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade {{ $myDivisionMatrices->count() == 0 ? 'show active' : '' }}" id="allMatrices" role="tabpanel" aria-labelledby="allMatrices-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Matrices
                                </h6>
                                <small class="text-muted">All matrices in the system</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.csv') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @if($allMatrices->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
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
                                        @php $count = 1; @endphp
                                        @foreach($allMatrices as $matrix)
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
                                                            <div class="modal-dialog modal-md modal-dialog-centered">
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
                                                            <div class="modal-dialog modal-md modal-dialog-centered">
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
                                                            class="p-1 rounded {{ config('approval_states')[$matrix->overall_status] }}">{{ strtoupper($matrix->overall_status) }}</span>
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
                            @if($allMatrices instanceof \Illuminate\Pagination\LengthAwarePaginator && $allMatrices->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $allMatrices->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-calendar-x fs-1 opacity-50"></i>
                                <p class="mb-0">No matrices found in the system.</p>
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
            // Pre-fill filters from URL
            const params = new URLSearchParams(window.location.search);
            $('#yearFilter').val(params.get('year') || '');
            $('#quarterFilter').val(params.get('quarter') || '');
            $('#divisionFilter').val(params.get('division') || '');
            $('#focalFilter').val(params.get('focal_person') || '');

            // Apply Select2
            $('.select2').select2({
                width: '100%'
            });

            // Handle filter change
            $('#applyFilters').on('click', function() {
                const url = new URL(window.location.href);
                ['year', 'quarter', 'division', 'focal_person'].forEach(id => {
                    const val = $('#' + id + 'Filter').val();
                    if (val) url.searchParams.set(id, val);
                    else url.searchParams.delete(id);
                });
                window.location.href = url.toString();
            });
        });
    </script>
@endpush
