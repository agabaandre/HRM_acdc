@extends('layouts.app')

@section('title', 'View Matrix')

@section('header', 'Matrix Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Add Activity
    </a>
    <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-edit me-1"></i> Edit Matrix
    </a>
    <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2 text-primary"></i>Matrix Information</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-borderless table-hover">
                    <tr>
                        <th width="140" class="text-muted"><i class="bx bx-calendar-alt me-1 text-primary"></i> Year:</th>
                        <td>{{ $matrix->year }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><i class="bx bx-calendar-week me-1 text-primary"></i> Quarter:</th>
                        <td>{{ $matrix->quarter }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><i class="bx bx-building me-1 text-primary"></i> Division:</th>
                        <td>{{ $matrix->division->name }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><i class="bx bx-user-voice me-1 text-primary"></i> Focal Person:</th>
                        <td>{{ $matrix->focalPerson ? $matrix->focalPerson->name : 'Not assigned' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><i class="bx bx-calendar-plus me-1 text-primary"></i> Created At:</th>
                        <td>{{ $matrix->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted"><i class="bx bx-calendar-edit me-1 text-primary"></i> Last Update:</th>
                        <td>{{ $matrix->updated_at->format('Y-m-d H:i') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-target-lock me-2 text-primary"></i>Key Result Areas</h5>
            </div>
            <div class="card-body p-4">
                @php
                    $keyResultAreas = $matrix->key_result_area;
                    // Decode json
                    $keyResultAreas = json_decode($keyResultAreas, true);
                @endphp

                @if(empty($keyResultAreas))
                    <div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle me-2"></i> No key result areas have been added yet.
                    </div>
                @else
                    @foreach($keyResultAreas as $index => $area)
                        @php
                            $area = is_array($area) ? $area : [];
                            $title = $area['title'] ?? 'Untitled';
                            $description = $area['description'] ?? 'No description provided';
                            $targets = $area['targets'] ?? 'No targets specified';
                        @endphp
                        <div class="key-result-area card border shadow-sm mb-3">
                            <div class="card-header bg-light">
                                <h6 class="m-0 fw-semibold">
                                    <i class="bx bx-bullseye me-1 text-primary"></i> {{ $title }}
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="fw-semibold d-block text-primary">
                                        <i class="bx bx-detail me-1"></i> Description
                                    </label>
                                    <p class="mb-0">{{ $description }}</p>
                                </div>
                                <div>
                                    <label class="fw-semibold d-block text-primary">
                                        <i class="bx bx-bullseye me-1"></i> Expected Results
                                    </label>
                                    <p class="mb-0">{{ $targets }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><i class="bx bx-calendar-event me-2 text-primary"></i>Activities</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <input type="text" class="form-control w-auto shadow-sm" id="searchInput" placeholder="Search activities...">
                            <select class="form-select w-auto shadow-sm" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="fw-semibold">Title</th>
                                <th class="fw-semibold">Date Range</th>
                                <th class="fw-semibold">Location</th>
                                <th class="fw-semibold">Budget</th>
                                <th class="fw-semibold">Status</th>
                                <th class="fw-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($matrix->activities as $activity)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-primary">{{ $activity->activity_title }}</div>
                                        <small class="text-muted">{{ Str::limit($activity->description, 50) }}</small>
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
                                        <span class="badge bg-warning text-dark"><i class="bx bx-time-five me-1"></i>Pending</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group" aria-label="Activity actions">
                                            <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}"
                                               class="btn btn-sm btn-info"
                                               data-bs-toggle="tooltip"
                                               title="View Activity Details">
                                                <i class="bx bx-show-alt"></i>
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
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="bx bx-trash me-1"></i> Delete Activity</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-warning mb-3">
                                                            <i class="bx bx-error me-1"></i> Are you sure you want to delete this activity? This action cannot be undone.
                                                        </div>
                                                        <div class="card border">
                                                            <div class="card-body p-3">
                                                                <p class="mb-1"><strong><i class="bx bx-heading me-1 text-primary"></i> Title:</strong> {{ $activity->activity_title }}</p>
                                                                <p class="mb-0"><strong><i class="bx bx-calendar me-1 text-primary"></i> Date:</strong> {{ $activity->date_from->format('Y-m-d') }} to {{ $activity->date_to->format('Y-m-d') }}</p>
                                                            </div>
                                                        </div>
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
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bx bx-calendar-x fs-1 mb-3"></i>
                                            <p class="h5 text-muted">No activities found</p>
                                            <p class="small mt-2 text-muted">Click the "Add Activity" button to create a new activity</p>
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
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
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
