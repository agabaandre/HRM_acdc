@extends('layouts.app')

@section('title', 'View Matrix')

@section('header', 'Matrix Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success">
        <i class="bx bx-plus"></i> Add Activity
    </a>
    <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit Matrix
    </a>
    <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Matrix Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="120">Year:</th>
                        <td>{{ $matrix->year }}</td>
                    </tr>
                    <tr>
                        <th>Quarter:</th>
                        <td>{{ $matrix->quarter }}</td>
                    </tr>
                    <tr>
                        <th>Division:</th>
                        <td>{{ $matrix->division->name }}</td>
                    </tr>
                    <tr>
                        <th>Staff:</th>
                        <td>{{ $matrix->staff->name }}</td>
                    </tr>
                    <tr>
                        <th>Focal Person:</th>
                        <td>{{ $matrix->focalPerson->name }}</td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td>{{ $matrix->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Last Update:</th>
                        <td>{{ $matrix->updated_at->format('Y-m-d H:i') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Key Result Areas</h5>
            </div>
            <div class="card-body">
                @foreach($matrix->key_result_area as $index => $area)
                    <div class="key-result-area">
                        <h6 class="text-primary mb-2">{{ $area['title'] }}</h6>
                        <p class="text-muted mb-2"><strong>Description:</strong><br>{{ $area['description'] }}</p>
                        <p class="mb-0"><strong>Expected Results:</strong><br>{{ $area['targets'] }}</p>
                    </div>
                    @if(!$loop->last)
                        <hr>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Activities</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <input type="text" class="form-control w-auto" id="searchInput" placeholder="Search activities...">
                            <select class="form-select w-auto" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
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
                                <th>Title</th>
                                <th>Date Range</th>
                                <th>Location</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($matrix->activities as $activity)
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $activity->activity_title }}</div>
                                        <small class="text-muted">{{ $activity->workplan_activity_code }}</small>
                                    </td>
                                    <td>
                                        {{ $activity->date_from->format('Y-m-d') }}<br>
                                        <small class="text-muted">to</small><br>
                                        {{ $activity->date_to->format('Y-m-d') }}
                                    </td>
                                    <td>
                                        @foreach($activity->location_id as $location)
                                            <span class="badge bg-info">{{ $location }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if(isset($activity->budget['total']))
                                            <span class="text-success">
                                                ${{ number_format($activity->budget['total'], 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Pending</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}"
                                               class="btn btn-sm btn-info"
                                               data-bs-toggle="tooltip"
                                               title="View Activity">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}"
                                               class="btn btn-sm btn-warning"
                                               data-bs-toggle="tooltip"
                                               title="Edit Activity">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal{{ $activity->id }}"
                                                    title="Delete Activity">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal{{ $activity->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Activity</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this activity? This action cannot be undone.</p>
                                                        <p class="mb-0">
                                                            <strong>Title:</strong> {{ $activity->activity_title }}<br>
                                                            <strong>Date:</strong> {{ $activity->date_from->format('Y-m-d') }} to {{ $activity->date_to->format('Y-m-d') }}
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="{{ route('matrices.activities.destroy', [$matrix, $activity]) }}" method="POST" class="d-inline">
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
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-calendar-x fs-1"></i>
                                            <p class="mt-2">No activities found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Activity search
        $('#searchInput').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Status filter
        $('#statusFilter').change(function() {
            const value = $(this).val().toLowerCase();
            if (value) {
                $('tbody tr').filter(function() {
                    const status = $(this).find('.badge').text().toLowerCase();
                    $(this).toggle(status === value);
                });
            } else {
                $('tbody tr').show();
            }
        });
    });
</script>
@endpush
@endsection
