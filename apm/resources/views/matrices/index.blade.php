@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')

@section('header', 'Quarterly Travel Matrices')

@section('header-actions')
@php
 $isFocal = isfocal_person();
 //dd(user_session());
@endphp

@if ($isFocal)
<a href="{{ route('matrices.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Create New Matrix
</a>
@endif
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-calendar-check me-2 text-primary"></i>All Matrices</h5>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2">
                    <div class="input-group w-auto">
                        <span class="input-group-text bg-light"><i class="bx bx-calendar-alt text-primary"></i></span>
                        <select class="form-select w-auto" id="yearFilter">
                            <option value="">All Years</option>
                            @foreach(range(date('Y'), date('Y')-5) as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="input-group w-auto">
                        <span class="input-group-text bg-light"><i class="bx bx-calendar-week text-primary"></i></span>
                        <select class="form-select w-auto" id="quarterFilter">
                            <option value="">All Quarters</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><i class="bx bx-calendar-alt me-1 text-primary"></i> Year</th>
                        <th><i class="bx bx-calendar-week me-1 text-primary"></i> Quarter</th>
                        <th><i class="bx bx-building me-1 text-primary"></i> Division</th>
                        <th><i class="bx bx-user me-1 text-primary"></i> Staff</th>
                        <th><i class="bx bx-user-voice me-1 text-primary"></i> Focal Person</th>
                        <th><i class="bx bx-list-check me-1 text-primary"></i> Activities</th>
                        <th><i class="bx bx-time me-1 text-primary"></i> Created At</th>
                        <th class="text-center"><i class="bx bx-cog me-1 text-primary"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrices as $matrix)
                        <tr>
                            <td>{{ $matrix->year }}</td>
                            <td>{{ $matrix->quarter }}</td>
                            <td>{{ $matrix->division->name ?? 'N/A' }}</td>
                            <td>{{ $matrix->staff->name ?? 'N/A' }}</td>
                            <td>{{ $matrix->focalPerson->name ?? 'N/A' }}</td>
                            <td>
                                <a href="{{ route('matrices.activities.index', $matrix) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bx bx-list-check me-1"></i> {{ $matrix->activities_count ?? 0 }} Activities
                                </a>
                            </td>
                            <td>{{ $matrix->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('matrices.show', $matrix) }}"
                                       class="btn btn-sm btn-outline-info"
                                       data-bs-toggle="tooltip"
                                       title="View Matrix">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('matrices.edit', $matrix) }}"
                                       class="btn btn-sm btn-outline-warning"
                                       data-bs-toggle="tooltip"
                                       title="Edit Matrix">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $matrix->id }}"
                                            title="Delete Matrix">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $matrix->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title"><i class="bx bx-trash me-2"></i>Delete Matrix</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="bx bx-error-circle me-2"></i>Are you sure you want to delete this matrix? This action cannot be undone.
                                                </div>
                                                <div class="card border shadow-sm">
                                                    <div class="card-body py-3">
                                                        <p class="mb-0">
                                                            <i class="bx bx-calendar-alt me-1 text-primary"></i> <strong>Year:</strong> {{ $matrix->year }}<br>
                                                            <i class="bx bx-calendar-week me-1 text-primary"></i> <strong>Quarter:</strong> {{ $matrix->quarter }}<br>
                                                            <i class="bx bx-building me-1 text-primary"></i> <strong>Division:</strong> {{ $matrix->division->name ?? 'N/A' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                    <i class="bx bx-x me-1"></i>Cancel
                                                </button>
                                                <form action="{{ route('matrices.destroy', $matrix) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bx bx-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bx bx-calendar-x fs-1 text-primary opacity-50"></i>
                                    <p class="mt-2">No matrices found</p>
                                    <a href="{{ route('matrices.create') }}" class="btn btn-sm btn-primary mt-2">
                                        <i class="bx bx-plus me-1"></i> Create New Matrix
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($matrices->hasPages())
        <div class="card-footer bg-light py-2">
            {{ $matrices->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Year and Quarter filter handling
        $('#yearFilter, #quarterFilter').change(function() {
            const year = $('#yearFilter').val();
            const quarter = $('#quarterFilter').val();
            const url = new URL(window.location.href);

            if (year) url.searchParams.set('year', year);
            else url.searchParams.delete('year');

            if (quarter) url.searchParams.set('quarter', quarter);
            else url.searchParams.delete('quarter');

            window.location.href = url.toString();
        });

        // Set initial filter values from URL params
        const urlParams = new URLSearchParams(window.location.search);
        $('#yearFilter').val(urlParams.get('year') || '');
        $('#quarterFilter').val(urlParams.get('quarter') || '');
    });
</script>
@endpush
@endsection
