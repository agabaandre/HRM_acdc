@extends('layouts.app')

@section('title', $title)

@section('header', $title)

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
    
    @if($activity->overall_status === 'draft' && $activity->staff_id === user_session('staff_id'))
        <a href="{{ route('activities.single-memos.edit', $activity) }}" class="btn btn-warning">
            <i class="bx bx-edit"></i> Edit
        </a>
    @endif
    
    <a href="{{ route('activities.single-memos.status', $activity) }}" class="btn btn-success">
        <i class="bx bx-check-circle"></i> Approval Status
    </a>
</div>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="bx bx-file-doc me-2 text-primary"></i>Single Memo Details
                    </h4>
                    <p class="text-muted mb-0">{{ $activity->activity_title }}</p>
                </div>
                <div class="d-flex gap-2">
                    @if($activity->overall_status === 'draft' && $activity->staff_id === user_session('staff_id'))
                        <form action="{{ route('activities.single-memos.submit-for-approval', $activity) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-send me-1"></i>Submit for Approval
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Status Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            @php
                                $statusBadgeClass = [
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning', 
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                ];
                                $statusClass = $statusBadgeClass[$activity->overall_status] ?? 'bg-secondary';
                            @endphp
                            <div class="mb-2">
                                <span class="badge {{ $statusClass }} fs-6 px-3 py-2">
                                    {{ ucfirst($activity->overall_status ?? 'draft') }}
                                </span>
                            </div>
                            <small class="text-muted">Current Status</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h5 class="mb-1">{{ $activity->staff->fname ?? '' }} {{ $activity->staff->lname ?? '' }}</h5>
                            <small class="text-muted">Created By</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h5 class="mb-1">{{ $activity->matrix->division->division_name ?? 'N/A' }}</h5>
                            <small class="text-muted">Division</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h5 class="mb-1">{{ $activity->date_from->format('M d, Y') }} - {{ $activity->date_to->format('M d, Y') }}</h5>
                            <small class="text-muted">Date Range</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="row">
                <div class="col-md-8">
                    <!-- Basic Information -->
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-primary"><i class="bx bx-info-circle me-2"></i>Basic Information</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Activity Title</label>
                                    <p class="mb-0">{{ $activity->activity_title }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Request Type</label>
                                    <p class="mb-0">{{ $activity->requestType->name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Start Date</label>
                                    <p class="mb-0">{{ $activity->date_from->format('F d, Y') }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">End Date</label>
                                    <p class="mb-0">{{ $activity->date_to->format('F d, Y') }}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Total Participants</label>
                                    <p class="mb-0">{{ $activity->total_participants }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">External Participants</label>
                                    <p class="mb-0">{{ $activity->total_external_participants }}</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Background</label>
                                <div class="html-content mb-0">{!! $activity->background ?: 'No background provided' !!}</div>
                            </div>
                            
                            @if($activity->activity_request_remarks)
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <div class="html-content mb-0">{!! $activity->activity_request_remarks !!}</div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Participants -->
                    @if(count($internalParticipants) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-info"><i class="bx bx-users me-2"></i>Internal Participants</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Division</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($internalParticipants as $participant)
                                        <tr>
                                            <td>{{ $participant['staff']->fname }} {{ $participant['staff']->lname }}</td>
                                            <td>{{ $participant['staff']->division_name }}</td>
                                            <td>{{ $participant['participant_start'] ? \Carbon\Carbon::parse($participant['participant_start'])->format('M d, Y') : 'N/A' }}</td>
                                            <td>{{ $participant['participant_end'] ? \Carbon\Carbon::parse($participant['participant_end'])->format('M d, Y') : 'N/A' }}</td>
                                            <td>{{ $participant['participant_days'] ?? 'N/A' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Budget Information -->
                    @if(count($budgetItems) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-money me-2"></i>Budget Information</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fund Code</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($budgetItems as $index => $amount)
                                        <tr>
                                            <td>{{ $fundCodes->where('id', $budgetCodes[$index] ?? null)->first()->fund_code ?? 'N/A' }}</td>
                                            <td>{{ number_format($amount, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Attachments -->
                    @if(count($attachments) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-warning"><i class="bx bx-paperclip me-2"></i>Attachments</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attachments as $index => $attachment)
                                        @php
                                            $ext = strtolower(pathinfo($attachment['original_name'] ?? $attachment['filename'] ?? '', PATHINFO_EXTENSION));
                                            $fileUrl = url('storage/'.($attachment['path'] ?? $attachment['file_path'] ?? ''));
                                            $isOffice = in_array($ext, ['ppt','pptx','xls','xlsx','doc','docx']);
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                            <td>{{ $attachment['original_name'] ?? $attachment['filename'] ?? $attachment['name'] ?? 'Unknown' }}</td>
                                            <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                            <td>
                                                @if($attachment['path'] ?? $attachment['file_path'])
                                                    @if($isOffice)
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="bx bx-download"></i> Download
                                                        </a>
                                                    @else
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-info" target="_blank">
                                                            <i class="bx bx-show"></i> View
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">File not available</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Approval Actions Section -->
                    @if(function_exists('can_take_action_generic') && can_take_action_generic($activity))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-check-circle me-2"></i>Approval Actions</h6>
                            </div>
                            <div class="card-body p-4">
                                @include('partials.approval-actions', ['resource' => $activity])
                            </div>
                        </div>
                    @endif

                    <!-- Submit for Approval Section -->
                    @if($activity->overall_status === 'draft' && $activity->staff_id === user_session('staff_id'))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-send me-2"></i>Submit for Approval</h6>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted mb-3">Ready to submit this single memo for approval?</p>
                                <form action="{{ route('activities.single-memos.submit-for-approval', $activity) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bx bx-send me-2"></i>Submit for Approval
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Quick Info -->
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-info"><i class="bx bx-info-circle me-2"></i>Quick Info</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Created</label>
                                <p class="mb-0">{{ $activity->created_at->format('M d, Y H:i') }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Last Updated</label>
                                <p class="mb-0">{{ $activity->updated_at->format('M d, Y H:i') }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Fund Type</label>
                                <p class="mb-0">{{ $activity->fundType->fund_type_name ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Key Result Area</label>
                                <p class="mb-0">{{ $activity->key_result_area ?: 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Trail -->
                    @if($activity->approvalTrails && $activity->approvalTrails->count() > 0)
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-history me-2"></i>Approval Trail</h6>
                            </div>
                            <div class="card-body p-4">
                                @include('partials.approval-trail', ['resource' => $activity])
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
