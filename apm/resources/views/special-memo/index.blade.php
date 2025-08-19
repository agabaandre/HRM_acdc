@extends('layouts.app')

@section('title', 'Special Memos')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">Special Memos</h6>
                    <a href="{{ route('special-memo.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="bx bx-plus-circle me-1"></i> New Special Memo
                    </a>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <form action="{{ route('special-memo.index') }}" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small mb-1">Staff</label>
                                <select name="staff_id" class="form-select form-select-sm">
                                    <option value="">All Staff</option>
                                    @foreach($staff as $s)
                                        <option value="{{ $s->id }}" {{ request('staff_id') == $s->id ? 'selected' : '' }}>
                                            {{ $s->first_name }} {{ $s->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small mb-1">Division</label>
                                <select name="division_id" class="form-select form-select-sm">
                                    <option value="">All Divisions</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bx bx-filter-alt me-1"></i> Filter
                                    </button>
                                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bx bx-reset me-1"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" width="60">ID</th>
                                    {{-- <th width="120">Memo Number</th> --}}
                                    <th width="110">Date</th>
                                    <th>Subject</th>
                                    <th>Author</th>
                                    <th>Division</th>
                                    <th width="90">Status</th>
                                    <th class="text-center" width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($specialMemos as $memo)
                                    <tr>
                                         <td class="text-center">{{ $memo->id }}</td>
                                       {{-- <td>
                                            <span class="fw-medium">{{ $memo->memo_number }}</span>
                                        </td> --}}
                                        <td>{{ $memo->formatted_dates }}</td>
                                        <td>
                                            <a href="{{ route('special-memo.show', $memo) }}" class="text-decoration-none fw-medium text-dark">
                                                {{ Str::limit($memo->activity_title, 50) }}
                                            </a>
                                        </td>
                                        @php 
                                      
                                        @endphp
                                        <td>{{ optional($memo->staff)->fname ?? '-' }} {{ optional($memo->staff)->lname ?? '' }}</td>
                                        <td>{{ optional($memo->division)->division_name ?? '-' }}</td>
                                        <td>
                                            @php
                                                $statusBadgeClass = [
                                                    'draft' => 'bg-secondary',
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'returned' => 'bg-info',
                                                ][$memo->overall_status] ?? 'bg-secondary';
                                            @endphp
                                            <span class="badge {{ $statusBadgeClass }}">
                                                @if($memo->overall_status === 'draft')
                                                    <i class="bx bx-edit me-1"></i>
                                                @endif
                                                {{ ucfirst($memo->overall_status) }}
                                                @if($memo->overall_status === 'draft')
                                                    (Draft)
                                                @endif
                                            </span>
                                            @if($memo->overall_status === 'pending')
                                                <br><small class="text-muted">Level {{ $memo->approval_level ?? 0 }}</small>
                                                @if($memo->workflow_definition)
                                                    <br><small class="text-muted">{{ $memo->workflow_definition->role ?? 'Role' }}</small>
                                                @endif
                                                @if($memo->current_actor)
                                                    <br><small class="text-muted">Supervisor: {{ $memo->current_actor->fname . ' ' . $memo->current_actor->lname }}</small>
                                                @endif
                                            @elseif($memo->overall_status === 'draft')
                                                <br><small class="text-muted">Ready to submit</small>
                                            @elseif($memo->overall_status === 'returned')
                                                <br><small class="text-muted">Needs revision</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex">
                                                <a href="{{ route('special-memo.show', $memo) }}" class="btn btn-sm btn-icon btn-outline-primary me-1" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                @if($memo->overall_status === 'approved')
                                                    <a href="{{ route('special-memo.print', $memo) }}" target="_blank" class="btn btn-sm btn-icon btn-outline-success me-1" data-bs-toggle="tooltip" title="Print PDF">
                                                        <i class="bx bx-printer"></i>
                                                    </a>
                                                @endif
                                                @if($memo->overall_status === 'draft' && $memo->staff_id == user_session('staff_id'))
                                                    <a href="{{ route('special-memo.edit', $memo) }}" class="btn btn-sm btn-icon btn-outline-primary me-1" data-bs-toggle="tooltip" title="Edit">
                                                        <i class="bx bx-edit"></i>
                                                    </a>
                                                @endif
                                                @if(can_take_action_generic($memo))
                                                    <a href="{{ route('special-memo.status', $memo) }}" class="btn btn-sm btn-icon btn-outline-success me-1" data-bs-toggle="tooltip" title="Approval Status">
                                                        <i class="bx bx-check-circle"></i>
                                                    </a>
                                                @endif
                                                @if($memo->overall_status === 'draft' && $memo->staff_id == user_session('staff_id'))
                                                    <form action="{{ route('special-memo.destroy', $memo) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" data-bs-toggle="tooltip" title="Delete">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bx bx-file-find text-secondary mb-2" style="font-size: 2rem;"></i>
                                                <h6 class="text-muted mb-1">No special memos found</h6>
                                                <p class="text-muted small">Try adjusting your search or create a new special memo</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $specialMemos->appends(request()->except('page'))->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.form-select').select2({
            width: '100%',
            dropdownAutoWidth: true,
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Setup delete confirmation
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this special memo.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bx bx-trash me-1"></i> Yes, delete it!',
                cancelButtonText: '<i class="bx bx-x me-1"></i> Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
