@extends('layouts.app')

@section('title', 'View Special Memo')

@section('content')
<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            
            <!-- Main Content Cards -->
            <div class="row g-4">
                <div class="col-lg-8">

                    <!-- Meta Info Panel -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0" style="font-size:1rem;">
                        <i class="bx bx-info-circle me-2 text-primary"></i>Special Memo Information
                    </h6>
                </div>
                <div class="card-body py-2 px-3">
                    <div class="row g-3 align-items-center matrix-meta-row">
                        <div class="col-md-3 matrix-meta-item">
                            <i class="bx bx-user"></i>
                            <span class="matrix-meta-label">Staff:</span>
                            <span class="matrix-meta-value">{{ optional($specialMemo->staff)->first_name }} {{ optional($specialMemo->staff)->last_name }}</span>
                        </div>
                        <div class="col-md-9 matrix-meta-item">
                            <i class="bx bx-building"></i>
                            <span class="matrix-meta-label">Division:</span>
                            <span class="matrix-meta-value">{{ optional($specialMemo->division)->division_name ?? '-' }}</span>
                        </div>
                        <div class="col-md-3 matrix-meta-item">
                            <i class="bx bx-calendar"></i>
                            <span class="matrix-meta-label">Date Range:</span>
                            <span class="matrix-meta-value">{{ $specialMemo->formatted_dates ?: '-' }}</span>
                        </div>
                        <div class="col-md-3 matrix-meta-item">
                            <i class="bx bx-check-circle"></i>
                            <span class="matrix-meta-label">Status:</span>
                            @php
                                $statusBadgeClass = [
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                ][$specialMemo->overall_status] ?? 'bg-secondary';
                            @endphp
                            <span class="badge {{ $statusBadgeClass }}">{{ ucfirst($specialMemo->overall_status) }}</span>
                        </div>
                        <div class="col-md-3 matrix-meta-item">
                            <i class="bx bx-cube"></i>
                            <span class="matrix-meta-label">Request Type:</span>
                            <span class="matrix-meta-value">{{ optional($specialMemo->requestType)->name ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-detail me-2"></i>Activity Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <span class="text-muted small">Activity Title</span>
                                <h5 class="fw-bold mb-0">{{ $specialMemo->activity_title ?? '-' }}</h5>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Key Result Area</span>
                                <div>{{ $specialMemo->key_result_area ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Background</span>
                                <div class="bg-light rounded p-2 " style="white-space: pre-line;">{{ $specialMemo->background ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Justification</span>
                                <div class="bg-light rounded p-2 " style="white-space: pre-line;">{{ $specialMemo->justification ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Supporting Reasons</span>
                                <div class="bg-light rounded p-2 " style="white-space: pre-line;">{{ $specialMemo->supporting_reasons ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Remarks</span>
                                <div class="bg-light rounded p-2" style="white-space: pre-line;">{{ $specialMemo->remarks ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-group me-2"></i>Participants & Location</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <span class="text-muted small">Location(s)</span>
                                <div><i class="bx bx-map me-1"></i>
                                        {{ $specialMemo->locations ?? '-' }}
                                </div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Participants</span>
                                <div><i class="bx bx-user me-1"></i> <strong>Total:</strong> {{ $specialMemo->total_participants ?? '-' }}</div>
                                <div class="mt-1"><span class="badge bg-info">Internal</span> 
                                    @if(is_array($specialMemo->internal_participants))
                                        {{ count($specialMemo->internal_participants) }}
                                    @endif
                                </div>
                                <div class="mt-1"><span class="badge bg-secondary">External</span> {{ $specialMemo->total_external_participants ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Internal Participants</span>
                                @if(!empty($specialMemo->internal_participants))
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Staff</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Days</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($specialMemo->internal_participants as $participant)
                                                    <tr>
                                                        <td>
                                                            @if($participant['staff'])
                                                                {{ $participant['staff']->fname }} {{ $participant['staff']->lname }}
                                                            @else
                                                                <span class="text-muted">Unknown</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $participant['participant_start'] ?? '-' }}</td>
                                                        <td>{{ $participant['participant_end'] ?? '-' }}</td>
                                                        <td>{{ $participant['participant_days'] ?? '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <span class="text-muted">No internal participants</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-comment-detail me-2"></i>Activity Request Remarks</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="bg-light rounded p-2 border" style="white-space: pre-line;">{{ $specialMemo->activity_request_remarks ?? '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-money me-2"></i>Budget</h6>
                        </div>
                        <div class="card-body p-4">
                            @if(is_array($specialMemo->budget) && count($specialMemo->budget))
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th>Amount</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($specialMemo->budget as $key => $item)
                                                <tr>
                                                    <td>{{ $key }}</td>
                                                    <td>
                                                        @if(is_array($item) && isset($item['amount']))
                                                            {{ number_format($item['amount'], 2) }}
                                                        @elseif(is_numeric($item))
                                                            {{ number_format($item, 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if(is_array($item))
                                                            <pre class="mb-0 small">{{ json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <span class="text-muted">No budget details</span>
                            @endif
                        </div>
                    </div>
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-paperclip me-2"></i>Attachments</h6>
                        </div>
                        <div class="card-body p-4">
                            @if(is_array($specialMemo->attachment) && count($specialMemo->attachment) > 0)
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($specialMemo->attachment as $attachment)
                                        <a href="{{ asset('storage/' . ($attachment['path'] ?? '')) }}" target="_blank" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
                                            <i class="bx bx-paperclip"></i> {{ $attachment['name'] ?? 'File' }}
                                            <small class="text-muted">({{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'Unknown size' }})</small>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">No attachments</span>
                            @endif
                        </div>
                    </div>


                    <!-- Approval Trail Section -->
                    
                            @if(isset($specialMemo->approvalTrails) && $specialMemo->approvalTrails->count() > 0)
                                @include('partials.approval-trail', ['resource' => $specialMemo])
                            @else
                            <div class="card matrix-card mb-4">
                                <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                    <h6 class="m-0 fw-semibold text-success"><i class="bx bx-history me-2"></i>Approval Trail</h6>
                                </div>
                                <div class="card-body p-4">
                                <div class="text-center text-muted py-4">
                                    <i class="bx bx-time bx-lg mb-3"></i>
                                    <p class="mb-0">No approval actions have been taken yet.</p>
                                    @if($specialMemo->overall_status === 'draft')
                                        <small>Submit this special memo for approval to start the approval trail.</small>
                                    @endif
                                </div>
                                </div>
                            </div>
                        </div>
                            @endif


                    <!-- Approval Actions Section -->
                    @if(function_exists('can_take_action_generic') && can_take_action_generic($specialMemo))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-check-circle me-2"></i>Approval Actions</h6>
                            </div>
                            <div class="card-body p-4">
                                @include('partials.approval-actions', ['resource' => $specialMemo])
                            </div>
                        </div>
                    @endif

                    <!-- Submit for Approval Section -->
                    @if($specialMemo->is_draft && $specialMemo->staff_id == user_session('staff_id'))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-send me-2"></i>Submit for Approval</h6>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted mb-3">Ready to submit this special memo for approval?</p>
                                <form action="{{ route('special-memo.submit-for-approval', $specialMemo) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bx bx-send me-2"></i>Submit for Approval
                                    </button>
                                </form>
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
        // Setup delete confirmation
        $('#deleteMemoForm').on('submit', function(e) {
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

<style>
.matrix-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    font-size: 0.92rem;
    line-height: 1.1;
    margin-bottom: 0.5rem;
}
.matrix-meta-item {
    display: flex;
    align-items: center;
    min-width: 120px;
    margin-bottom: 0;
}
.matrix-meta-item i {
    font-size: 1rem;
    margin-right: 0.3rem;
    color: #007bff;
}
.matrix-meta-label {
    color: #888;
    font-size: 0.85em;
    margin-right: 0.2em;
}
.matrix-meta-value {
    font-weight: 500;
}
.matrix-card {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px rgba(17,154,72,0.08);
    border: none;
}
.matrix-card .card-header {
    border-radius: 1.25rem 1.25rem 0 0;
    background: linear-gradient(90deg, #e9f7ef 0%, #fff 100%);
    border-bottom: 1px solid #e9f7ef;
}
.matrix-card .card-body {
    border-radius: 0 0 1.25rem 1.25rem;
}
</style>
