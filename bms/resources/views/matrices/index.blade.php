@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')

@section('header', 'Quarterly Travel Matrices')

@section('header-actions')
<a href="{{ route('matrices.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Create New Matrix
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">All Matrices</h5>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2">
                    <select class="form-select w-auto" id="yearFilter">
                        <option value="">All Years</option>
                        @foreach(range(date('Y'), date('Y')-5) as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
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
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Quarter</th>
                        <th>Division</th>
                        <th>Staff</th>
                        <th>Focal Person</th>
                        <th>Activities</th>
                        <th>Created At</th>
                        <th>Actions</th>
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
                                    {{ $matrix->activities_count ?? 0 }} Activities
                                </a>
                            </td>
                            <td>{{ $matrix->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('matrices.show', $matrix) }}"
                                       class="btn btn-sm btn-info"
                                       data-bs-toggle="tooltip"
                                       title="View Matrix">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('matrices.edit', $matrix) }}"
                                       class="btn btn-sm btn-warning"
                                       data-bs-toggle="tooltip"
                                       title="Edit Matrix">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-danger"
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
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Matrix</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this matrix? This action cannot be undone.</p>
                                                <p class="mb-0">
                                                    <strong>Year:</strong> {{ $matrix->year }}<br>
                                                    <strong>Quarter:</strong> {{ $matrix->quarter }}<br>
                                                    <strong>Division:</strong> {{ $matrix->division->name ?? 'N/A' }}
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('matrices.destroy', $matrix) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-calendar-x fs-1"></i>
                                    <p class="mt-2">No matrices found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($matrices->hasPages())
        <div class="card-footer">
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
