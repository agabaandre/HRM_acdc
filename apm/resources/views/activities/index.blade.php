@extends('layouts.app')

@section('title', 'Activities')

@section('header', "Activities - {$matrix->quarter} {$matrix->year}")

@section('header-actions')
<div class="d-flex gap-2">
    @if($matrix->overall_status !== 'approved')
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success">
            <i class="bx bx-plus"></i> Add Activity
        </a>
    @endif
    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrix
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-md-0"><i class="bx bx-calendar-check me-2 text-primary"></i>All Activities</h5>
            </div>
            <div class="col-md-6">
                <form class="d-flex gap-2 justify-content-md-end">
                    <div class="input-group">
                        <input type="text"
                               class="form-control"
                               id="searchInput"
                               placeholder="Search activities...">
                        <button class="btn btn-outline-primary" type="button">
                            <i class="bx bx-search"></i>
                        </button>
                    </div>

                    <select class="form-select" id="typeFilter">
                        <option value="">All Types</option>
                        @foreach($requestTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>

                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </form>
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
                    <tr><th>#</th>
                        <th>Activity Code</th>
                        <th>Title</th>
                        <th>Date Range</th>
                        <th>Location</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                     @php
                        $count=1;
                       @endphp
                    @forelse($activities as $activity)
                        <tr data-type="{{ $activity->request_type_id }}">
                            <td>{{$count}}</td>
                            <td>
                                <small class="text-muted">{{ $activity->workplan_activity_code }}</small>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $activity->activity_title }}</div>
                                <small class="text-muted">{{ Str::limit($activity->background, 50) }}</small>
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
                                    <span class="text-success">${{ number_format($activity->budget['total'], 2) }}</span>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-warning">Pending</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}"
                                       class="btn btn-sm btn-info"
                                       data-bs-toggle="tooltip"
                                       title="View Activity">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($matrix->overall_status !== 'approved')
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
                                                data-bs-toggle="tooltip"
                                                title="Delete Activity">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    @endif
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
                        @php
                        $count++;
                       @endphp
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
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
    @if($activities->hasPages())
        <div class="card-footer">
            {{ $activities->links() }}
        </div>
    @endif
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

        // Type filter
        $('#typeFilter').change(function() {
            const value = $(this).val();
            if (value) {
                $('tbody tr').hide();
                $('tbody tr[data-type="' + value + '"]').show();
            } else {
                $('tbody tr').show();
            }
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

        // Date sorting (if needed)
        // $('.sort-date').click(function() {
        //     const rows = $('tbody tr').get();
        //     rows.sort(function(a, b) {
        //         const A = $(a).find('td:eq(2)').text();
        //         const B = $(b).find('td:eq(2)').text();
        //         return new Date(A) - new Date(B);
        //     });
        //     $.each(rows, function(index, row) {
        //         $('tbody').append(row);
        //     });
        // });
    });
</script>
@endpush
@endsection
