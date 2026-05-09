@php
    $isFocal = function_exists('isfocal_person') ? isfocal_person() : false;
    $canViewAllMatrices = in_array(87, user_session('permissions', []), true);
    $myDivisionTotalCount = (int) ($myDivisionTotalCount ?? 0);
    $allMatricesTotalCount = (int) ($allMatricesTotalCount ?? 0);
    $hasMyDivisionTab = $myDivisionTotalCount > 0;
@endphp
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">

            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <h4 class="mb-0 text-success fw-bold"><i class="bx bx-grid-alt me-2 text-success"></i> Matrix Details</h4>
                @if ($isFocal)
                    <a wire:navigate href="{{ route('matrices.create') }}" class="btn btn-success">
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
                            @foreach (range(date('Y') + 1, date('Y') - 5) as $year)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>{{ $year }}</option>
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
                            <option value="" {{ ($selectedQuarter === '' || $selectedQuarter === null) ? 'selected' : '' }}>All Quarters</option>
                            @foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter)
                                <option value="{{ $quarter }}" {{ $selectedQuarter == $quarter ? 'selected' : '' }}>{{ $quarter }}</option>
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
                <div class="col-md-2">
                    <label for="statusFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-archive me-1 text-success"></i> Matrix Status</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-filter-alt"></i></span>
                        <select class="form-select" id="statusFilter">
                            <option value="active" {{ ($selectedStatus ?? 'active') === 'active' ? 'selected' : '' }}>Active Only</option>
                            <option value="archived" {{ ($selectedStatus ?? 'active') === 'archived' ? 'selected' : '' }}>Archived Only</option>
                            <option value="all" {{ ($selectedStatus ?? 'active') === 'all' ? 'selected' : '' }}>All</option>
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

    @if(! $hasMyDivisionTab && ! $canViewAllMatrices)
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5 text-muted">
                <i class="bx bx-folder-open fs-1 d-block mb-2 opacity-50"></i>
                <p class="mb-0">No matrices match the current filters.</p>
                <small>Try another year, quarter, or status.</small>
            </div>
        </div>
    @else
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="matrixTabs" role="tablist">
                @if($hasMyDivisionTab)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $hasMyDivisionTab ? 'active' : '' }}" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="{{ $hasMyDivisionTab ? 'true' : 'false' }}">
                            <i class="bx bx-home me-2"></i> My Division Matrices
                            <span class="badge bg-success text-dark ms-2">{{ $myDivisionTotalCount }}</span>
                        </button>
                    </li>
                @endif
                @if($canViewAllMatrices)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ ! $hasMyDivisionTab ? 'active' : '' }}" id="allMatrices-tab" data-bs-toggle="tab" data-bs-target="#allMatrices" type="button" role="tab" aria-controls="allMatrices" aria-selected="{{ ! $hasMyDivisionTab ? 'true' : 'false' }}">
                            <i class="bx bx-grid me-2"></i> All Matrices
                            <span class="badge bg-primary text-white ms-2">{{ $allMatricesTotalCount }}</span>
                        </button>
                    </li>
                @endif
            </ul>

            <!-- Tab Content: tables load via AJAX when this block is near the viewport -->
            <div class="tab-content" id="matrixTabsContent">
                @if($hasMyDivisionTab)
                <div class="tab-pane fade {{ $hasMyDivisionTab ? 'show active' : '' }}" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-home me-2"></i> My Division Matrices
                                </h6>
                                <small class="text-muted">Matrices in your division and divisions where you are the head</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.division-csv') }}" class="btn btn-outline-success btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>

                        <div id="myDivision-ajax-body" class="matrices-tab-ajax-body" style="min-height: 12rem;">
                            <div class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading…</span></div>
                                <p class="small mb-0 mt-2">Scroll here or wait a moment to load matrices…</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($canViewAllMatrices)
                <div class="tab-pane fade {{ ! $hasMyDivisionTab ? 'show active' : '' }}" id="allMatrices" role="tabpanel" aria-labelledby="allMatrices-tab">
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

                        <div id="allMatrices-ajax-body" class="matrices-tab-ajax-body" style="min-height: 12rem;">
                            <div class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                                <p class="small mb-0 mt-2">Open this tab or scroll here to load matrices…</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
