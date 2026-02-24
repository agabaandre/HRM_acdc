@extends('layouts.app')

@section('title', 'Divisions')

@section('header', 'Divisions')

@section('header-actions')
<div class="text-muted">
    <small><i class="bx bx-info-circle me-1"></i>Divisions are managed in the main system</small>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-building-house me-2 text-primary"></i>All Divisions</h5>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <form action="{{ route('divisions.index') }}" method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search divisions..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bx bx-search"></i>
                        </button>
                        @if(request('search'))
                            <a href="{{ route('divisions.index') }}" class="btn btn-outline-secondary">
                                <i class="bx bx-x"></i>
                            </a>
                        @endif
                    </form>
                    <a href="{{ route('divisions.export.excel', request()->query()) }}" class="btn btn-outline-success" title="Export to Excel">
                        <i class="bx bx-download me-1"></i> Export to Excel
                    </a>
                </div>
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
                        <th style="width: 60px;">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_direction' => request('sort_direction') === 'asc' ? 'desc' : 'asc']) }}" 
                               class="text-decoration-none text-dark">
                                # <i class="bx bx-sort"></i>
                            </a>
                        </th>
                        <th style="width: 150px;">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'division_name', 'sort_direction' => request('sort_direction') === 'asc' ? 'desc' : 'asc']) }}" 
                               class="text-decoration-none text-dark">
                                Division Name <i class="bx bx-sort"></i>
                            </a>
                        </th>
                        <th style="width: 120px;">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'division_short_name', 'sort_direction' => request('sort_direction') === 'asc' ? 'desc' : 'asc']) }}" 
                               class="text-decoration-none text-dark">
                                Short Name <i class="bx bx-sort"></i>
                            </a>
                        </th>
                        <th style="width: 100px;">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'category', 'sort_direction' => request('sort_direction') === 'asc' ? 'desc' : 'asc']) }}" 
                               class="text-decoration-none text-dark">
                                Category <i class="bx bx-sort"></i>
                            </a>
                        </th>
                        <th style="width: 200px;">Division Head</th>
                        <th style="width: 200px;">Focal Person</th>
                        <th style="width: 200px;">Admin Assistant</th>
                        <th style="width: 200px;">Finance Officer</th>
                        <th style="width: 100px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($divisions as $division)
                        <tr>
                            <td class="fw-bold">{{ $division->id }}</td>
                            <td style="max-width: 150px; word-wrap: break-word; vertical-align: middle;">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold" style="word-wrap: break-word; white-space: normal; line-height: 1.2;">{{ $division->division_name }}</span>
                                </div>
                            </td>
                            <td>
                                @if($division->division_short_name)
                                    <span class="badge bg-primary">{{ $division->division_short_name }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($division->category)
                                    <span class="badge bg-secondary">{{ $division->category }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($division->divisionHead)
                                    <div class="d-flex flex-column">
                                        <span>{{ $division->divisionHead->fname }} {{ $division->divisionHead->lname }}</span>
                                        <small class="text-muted">{{ $division->divisionHead->position ?? 'Staff' }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($division->focalPerson)
                                    <div class="d-flex flex-column">
                                        <span>{{ $division->focalPerson->fname }} {{ $division->focalPerson->lname }}</span>
                                        <small class="text-muted">{{ $division->focalPerson->position ?? 'Staff' }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($division->adminAssistant)
                                    <div class="d-flex flex-column">
                                        <span>{{ $division->adminAssistant->fname }} {{ $division->adminAssistant->lname }}</span>
                                        <small class="text-muted">{{ $division->adminAssistant->position ?? 'Staff' }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($division->financeOfficer)
                                    <div class="d-flex flex-column">
                                        <span>{{ $division->financeOfficer->fname }} {{ $division->financeOfficer->lname }}</span>
                                        <small class="text-muted">{{ $division->financeOfficer->position ?? 'Staff' }}</small>
                                    </div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('divisions.show', $division->id) }}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2 mb-0">No divisions found</p>
                                    @if(request('search'))
                                        <small>Try adjusting your search criteria</small>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(isset($divisions) && method_exists($divisions, 'hasPages') && $divisions->hasPages())
        <div class="card-footer bg-light">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        Showing {{ $divisions->firstItem() }} to {{ $divisions->lastItem() }} of {{ $divisions->total() }} divisions
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <label for="per_page" class="form-label mb-0 small">Per page:</label>
                            <select name="per_page" id="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="5" {{ request('per_page') == 5 ? 'selected' : '' }}>5</option>
                                <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                <option value="15" {{ request('per_page') == 15 || !request('per_page') ? 'selected' : '' }}>15</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>
                        {{ $divisions->links() }}
                    </div>
                </div>
            </div>
        </div>
    @endif
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

        // Add form inputs to per_page select
        $('#per_page').closest('form').append($('<input>').attr({
            type: 'hidden',
            name: 'search',
            value: '{{ request("search") }}'
        }));
        $('#per_page').closest('form').append($('<input>').attr({
            type: 'hidden',
            name: 'sort_by',
            value: '{{ request("sort_by", "division_name") }}'
        }));
        $('#per_page').closest('form').append($('<input>').attr({
            type: 'hidden',
            name: 'sort_direction',
            value: '{{ request("sort_direction", "asc") }}'
        }));
    });
</script>
@endpush