@php $isFocal = function_exists('isfocal_person') ? isfocal_person() : false; @endphp
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

            <div class="w-100" id="matrixFilters" autocomplete="off">
                @include('partials.apm-matrix-list-filters', [
                    'filterId' => 'matrixFilters',
                    'resetUrl' => route('matrices.index'),
                    'divisions' => $divisions,
                    'focalPersons' => $focalPersons,
                    'selectedYear' => $selectedYear,
                    'selectedQuarter' => $selectedQuarter,
                    'selectedStatus' => $selectedStatus ?? 'active',
                ])
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="matrixTabs" role="tablist">
                @if(($myDivisionMatricesCount ?? 0) > 0)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="true">
                            <i class="bx bx-home me-2"></i> My Division Matrices 
                            <span class="badge bg-success text-dark ms-2">{{ (int) ($myDivisionMatricesCount ?? 0) }}</span>
                        </button>
                    </li>
                @endif
                @if(in_array(87, user_session('permissions', [])))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ ($myDivisionMatricesCount ?? 0) == 0 ? 'active' : '' }}" id="allMatrices-tab" data-bs-toggle="tab" data-bs-target="#allMatrices" type="button" role="tab" aria-controls="allMatrices" aria-selected="{{ ($myDivisionMatricesCount ?? 0) == 0 ? 'true' : 'false' }}">
                            <i class="bx bx-grid me-2"></i> All Matrices
                            <span class="badge bg-primary text-white ms-2">{{ (int) ($allMatricesCount ?? 0) }}</span>
                        </button>
                    </li>
                @endif
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="matrixTabsContent">
                <!-- My Division Matrices Tab -->
                <div class="tab-pane fade show active" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
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
                        <div id="myDivision-matrix-table-host" class="matrix-tab-table-host" @if(!empty($initialMyDivisionMatrices)) data-server-rendered="1" @endif>
                            @if(!empty($initialMyDivisionMatrices))
                                @include('matrices.partials.my-division-tab', [
                                    'myDivisionMatrices' => $initialMyDivisionMatrices,
                                    'selectedYear' => $selectedYear,
                                    'selectedQuarter' => $selectedQuarter,
                                    'selectedStatus' => $selectedStatus ?? 'active',
                                ])
                            @else
                                <div class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-success mb-2" role="status"><span class="visually-hidden">Loading…</span></div>
                                    <div>Loading matrices…</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- All Matrices Tab -->
                @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade {{ ($myDivisionMatricesCount ?? 0) == 0 ? 'show active' : '' }}" id="allMatrices" role="tabpanel" aria-labelledby="allMatrices-tab">
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
                        <div id="allMatrices-matrix-table-host" class="matrix-tab-table-host" @if(!empty($initialAllMatrices)) data-server-rendered="1" @endif>
                            @if(!empty($initialAllMatrices))
                                @include('matrices.partials.all-matrices-tab', [
                                    'allMatrices' => $initialAllMatrices,
                                    'selectedYear' => $selectedYear,
                                    'selectedQuarter' => $selectedQuarter,
                                    'selectedStatus' => $selectedStatus ?? 'active',
                                ])
                            @elseif(($myDivisionMatricesCount ?? 0) > 0)
                                <div class="text-center py-4 text-muted">
                                    <i class="bx bx-grid fs-3 d-block mb-2 opacity-50"></i>
                                    <div>Select the <strong>All Matrices</strong> tab above to load this list.</div>
                                </div>
                            @else
                                <div class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"><span class="visually-hidden">Loading…</span></div>
                                    <div>Loading matrices…</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
