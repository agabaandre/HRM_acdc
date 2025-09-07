@extends('layouts.app')

@section('title', 'View Activity')

@section('header', 'Activity Details')

@section('header-actions')

@endsection



@section('content')
<div class="row">
<div class="d-flex gap-1 mb-2" style="float: right;!important">
    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrix
    </a>

    
    @if(still_with_creator($matrix,$activity))
        <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}" class="btn btn-sm btn-warning">
            <i class="bx bx-edit"></i> Edit Activity
        </a>
    @endif

    
</div>
<div class="col-md-12 d-flex justify-content-end">
  {{-- Activity Operations Buttons --}}
            @if(allow_print_activity($activity))
            <div class="col-md-12 mb-3">
                <div class="card border-primary">
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-2 d-flex justify-content-end gap-2">
                                @if($activity->fundType && strtolower($activity->fundType->name) === 'extramural' && $matrix->overall_status === 'approved')
                                    @php
                                        // Check if ARF already exists for this activity
                                        $existingArfTop = \App\Models\RequestARF::where('source_id', $activity->id)
                                            ->where('model_type', 'App\\Models\\Activity')
                                            ->first();
                                    @endphp
                                    
                                    @if(!$existingArfTop)
                                        <button type="button" class="btn btn-success w-20" data-bs-toggle="modal" data-bs-target="#createArfModal">
                                            <i class="bx bx-file-plus me-2"></i>Create ARF Request
                                        </button>
                                    @elseif(in_array($existingArfTop->overall_status, ['pending', 'approved', 'returned']))
                                        <a href="{{ route('request-arf.show', $existingArfTop) }}" class="btn btn-outline-primary w-20">
                                            <i class="bx bx-show me-2"></i>View ARF Request
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('service-requests.create') }}?activity_id={{ $activity->id }}" 
                                       class="btn btn-info w-20" target="_blank">
                                        <i class="bx bx-wrench me-2"></i>Request for Services
                                    </a>
       
                                @endif
                            
                                <a href="{{ route('matrices.activities.memo-pdf', [$matrix, $activity]) }}" 
                                   class="btn btn-secondary w-20" target="_blank">
                                    <i class="bx bx-printer me-2"></i>Print Activity
                                </a>
                          
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
    
</div>

    <div class="col-md-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ $activity->activity_title }}</h5>
                <small class="text-muted">Activity Ref: {{ $activity->activity_ref }}</small>
                @if($activity->my_last_action)
                    <p> Your Action: <small class=" text-white rounded p-1 {{($activity->my_last_action->action=='passed')?'bg-success':'bg-danger'}}">{{strtoupper($activity->my_last_action->action)}}</small></p>
                @endif
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Background:</strong>
                        <p>{{ $activity->background }}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Key Result Area:</strong>
                        <p>{{ $matrix->key_result_area[intval($activity->key_result_area)]['description'] ?? '' }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Date From: </strong>{{ $activity->date_from->format('Y-m-d') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Date To: </strong>{{ $activity->date_to->format('Y-m-d') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Status: </strong>
                        @php
                            // Determine the status and badge color
                            $statusText = '';
                            $badgeClass = 'bg-secondary';

                            //dd($activity);

                            if (can_approve_activity($activity)) {
                                if ($activity->matrix->overall_status == 'approved') {
                                    $statusText = ucwords($activity->status);
                                    $badgeClass = 'bg-success';
                                } else {
                                    $statusText = ucwords($activity->status);
                                    $badgeClass = ($activity->status == 'passed') ? 'bg-success' : 'bg-danger';
                                }
                            }
                        @endphp

                        @if(can_approve_activity($activity))
                            <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                        @else
                            <span class="badge bg-success">No Action Required</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Request Type: </strong>{{ $activity->requestType->name ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Fund Type: </strong>{{ $activity->fundType->name ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Responsible Staff: </strong>{{ $activity->focalPerson ? ($activity->focalPerson->fname." ".$activity->focalPerson->lname): 'Not assigned' }}
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Locations:</strong><br>
                    @foreach($locations as $loc)
                        <span class="badge bg-info">{{ $loc->name }}</span>
                    @endforeach
                </div>

                <div class="mb-3">
                    <strong>Internal Participants ({{ count($internalParticipants) }})</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <td>#</td>
                                    <th>Name</th>
                                    <th>Division</th>
                                    <th>Job Title</th>
                                    <th>Duty Station</th>
                                  
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $count = 1;
                                @endphp
                                @foreach($internalParticipants as $entry)
                                    <tr><td>{{$count}}</td>
                                            <td>{{ $entry['staff']->name ?? 'N/A' }}</td>
                                             <td>{{ $entry['staff']->division_name ?? 'N/A' }}</td>
                                            <td>{{ $entry['staff']->job_name ?? 'N/A' }}</td>
                                          <td>{{ $entry['staff']->duty_station_name ?? 'N/A' }}</td>
                                        <td>{{ $entry['participant_days'] ?? '-' }}</td>
                                    </tr>
                                    @php
                                        $count++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Remarks:</strong>
                    <p>{{ $activity->activity_request_remarks }}</p>
                </div>

                @if($attachments && count($attachments) > 0)
                <div class="mb-3">
                    <strong>Attachments:</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Document Type</th>
                                    <th>File Name</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attachments as $index => $attachment)
                                    @php
                                        $originalName = $attachment['original_name'] ?? $attachment['filename'] ?? $attachment['name'] ?? 'Unknown';
                                        $filePath = $attachment['path'] ?? $attachment['file_path'] ?? '';
                                        $ext = $filePath ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';
                                        $fileUrl = $filePath ? url('storage/'.$filePath) : '#';
                                        $isOffice = in_array($ext, ['ppt','pptx','xls','xlsx','doc','docx']);
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                        <td>{{ $originalName }}</td>
                                        <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                        <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                        <td>
                                            @if($filePath)
                                            <button type="button" class="btn btn-sm btn-info preview-attachment" 
                                                data-file-url="{{ $fileUrl }}"
                                                data-file-ext="{{ $ext }}"
                                                data-file-office="{{ $isOffice ? '1' : '0' }}">
                                                <i class="bx bx-show"></i> Preview
                                            </button>
                                            <a href="{{ $fileUrl }}" download="{{ $originalName }}" class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Download
                                            </a>
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
                @endif
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Budget Details</h5>
            </div>
            <div class="card-body">
        
             @foreach($fundCodes ?? [] as $fundCode )
             
                 <h6  style="color: #911C39; font-weight: 600;"> {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name }}) </h6>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cost Item</th>
                                <th>Unit Cost</th>
                                <th>Units</th>
                                <th>Days</th>
                                <th>Total</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                              $count = 1;
                              $grandTotal = 0;
                            @endphp
                           
                            @foreach($activity->activity_budget as $item)
                                @php
                                    $total = $item->unit_cost * $item->units * $item->days;
                                    $grandTotal+=$total;
                                @endphp
                                <tr>
                                    <td>{{$count}}</td>
                                    <td class="text-end">{{ $item->cost }}</td>
                                    <td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-end">{{ $item->units }}</td>
                                    <td class="text-end">{{ $item->days }}</td>
                                    <td class="text-end">{{ number_format($item->total, 2) }}</td>
                                    <td>{{ $item->description }}</td>
                                </tr>
                            @endforeach

                            @php
                                $count++;
                            @endphp
                            
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Grand Total</th>
                                <th class="text-end">{{  number_format($grandTotal?? 0, 2)}}</th>
                            </tr>
                        </tfoot>
                    </table>
                     @endforeach
                </div>

            @if((can_take_action($matrix ) && can_approve_activity($activity))  && !done_approving_activty($activity))
            <div class="col-md-4 mb-2 px-2 ms-auto">
              @include('activities.partials.approval-actions',['activity'=>$activity,'matrix'=>$matrix])
            </div>
            @endif

            {{-- Debug Information (Temporary) --}}
            @if(allow_activity_operations())
            {{-- <div class="col-md-12 mb-3">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bx bx-bug me-2"></i>Debug Info (Remove after testing)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>allow_activity_operations():</strong> {{ allow_activity_operations() ? 'true' : 'false' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Activity Status (DB):</strong> {{ $activity->status ?? 'NULL' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Matrix Status:</strong> {{ $matrix->overall_status ?? 'NULL' }}
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <strong>Final Approval Status:</strong> {{ $activity->final_approval_status ?? 'NULL' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Fund Type:</strong> {{ $activity->fundType->name ?? 'NULL' }}
                            </div>
                            <div class="col-md-4">
                                <strong>Should Show Buttons:</strong> 
                                {{ allow_activity_operations() ? 'YES (OVERRIDE)' : 'NO' }}
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <strong>Latest Approval Trail:</strong> 
                                @if($activity->my_last_action)
                                    {{ $activity->my_last_action->action ?? 'NULL' }} 
                                    ({{ $activity->my_last_action->created_at ?? 'NULL' }})
                                @else
                                    No approval trail found
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div> --}}
            @endif

          
     

            </div>
        </div>
    </div>
    @php
    //dd($activity->activityApprovalTrails);
    @endphp

    @include('matrices.partials.approval-trail',['trails'=>$activity->activityApprovalTrails])

    {{-- <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Service Requests</h5>
            </div>
            <div class="card-body">
                @forelse($activity->serviceRequests as $request)
                    <div class="mb-3">
                        <strong>{{ $request->service_type }}</strong><br>
                        <small>{{ $request->created_at->format('Y-m-d H:i') }}</small><br>
                        <span class="badge bg-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($request->status) }}
                        </span>
                    </div>
                @empty
                    <div class="text-muted">No service requests found.</div>
                @endforelse
            </div>
        </div>
      
    </div> --}}
</div>

{{-- Modal for preview --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="previewModalBody" style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
        <div class="text-center w-100">Loading preview...</div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('styles')
<style>
/* Attachment Preview Modal Styles */
#attachmentPreviewModal .modal-dialog {
    max-width: 90vw;
    margin: 1.75rem auto;
}

#attachmentPreviewModal .modal-body {
    min-height: 500px;
    max-height: 80vh;
    overflow: hidden;
}

#previewContainer {
    min-height: 500px;
    max-height: 80vh;
    overflow: auto;
}

/* Image preview styles */
#previewContainer img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* PDF iframe styles */
#previewContainer iframe {
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Text preview styles */
#previewContainer pre {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    line-height: 1.5;
}

/* Loading animation */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #attachmentPreviewModal .modal-dialog {
        max-width: 95vw;
        margin: 0.5rem auto;
    }
    
    #attachmentPreviewModal .modal-body {
        min-height: 400px;
        max-height: 70vh;
    }
    
    #previewContainer {
        min-height: 400px;
        max-height: 70vh;
    }
}
</style>
@endpush

@push('scripts')
<script>
$(document).on('click', '.preview-attachment', function() {
    var fileUrl = $(this).data('file-url');
    var ext = $(this).data('file-ext');
    var isOffice = $(this).data('file-office') == '1';
    var modalBody = $('#previewModalBody');
    var content = '';
    if(['jpg','jpeg','png'].includes(ext)) {
        content = '<img src="'+fileUrl+'" class="img-fluid" style="max-height:70vh;max-width:100%;margin:auto;display:block;">';
    } else if(ext === 'pdf') {
        content = '<iframe src="'+fileUrl+'#toolbar=1&navpanes=0&scrollbar=1" style="width:100%;height:70vh;border:none;"></iframe>';
    } else if(isOffice) {
        var gdocs = 'https://docs.google.com/viewer?url='+encodeURIComponent(fileUrl)+'&embedded=true';
        content = '<iframe src="'+gdocs+'" style="width:100%;height:70vh;border:none;"></iframe>';
    } else {
        content = '<div class="alert alert-info">Preview not available. <a href="'+fileUrl+'" target="_blank">Download/Open file</a></div>';
    }
    modalBody.html(content);
    var modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
});
</script>

        @if($activity->fundType && strtolower($activity->fundType->name) === 'extramural' && $matrix->overall_status === 'approved')
            @php
                // Check if ARF already exists for this activity
                $existingArf = \App\Models\RequestARF::where('source_id', $activity->id)
                    ->where('model_type', 'App\\Models\\Activity')
                    ->first();
            @endphp
            
            @if(!$existingArf)
            @include('request-arf.components.create-arf-modal', [
            'sourceType' => 'Activity',
            'sourceTitle' => $activity->activity_title,
            'fundTypeId' => $activity->fundType ? $activity->fundType->id : null,
            'fundTypeName' => $activity->fundType ? $activity->fundType->name : 'N/A',
            'divisionName' => $activity->matrix && $activity->matrix->division ? $activity->matrix->division->division_name : 'N/A',
            'dateFrom' => $activity->date_from ? \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') : 'N/A',
            'dateTo' => $activity->date_to ? \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') : 'N/A',
            'numberOfDays' => $activity->date_from && $activity->date_to ? 
                \Carbon\Carbon::parse($activity->date_from)->diffInDays(\Carbon\Carbon::parse($activity->date_to)) + 1 : 'N/A',
            'location' => $activity->locations() ? $activity->locations()->pluck('name')->join(', ') : 'N/A',
            'keyResultArea' => $activity->matrix && $activity->matrix->key_result_area ? 
                collect($activity->matrix->key_result_area)->pluck('description')->join(', ') : 'N/A',
            'quarterlyLinkage' => $activity->quarterly_linkage ?? 'N/A',
            'totalParticipants' => $activity->total_participants ?? 'N/A',
            'internalParticipants' => $activity->internal_participants 
                ? (is_string($activity->internal_participants) 
                    ? count(json_decode($activity->internal_participants, true) ?? []) 
                    : count($activity->internal_participants)) 
                : 0,
            'externalParticipants' => $activity->total_participants ? ($activity->total_participants - ($activity->internal_participants 
                ? (is_string($activity->internal_participants) 
                    ? count(json_decode($activity->internal_participants, true) ?? []) 
                    : count($activity->internal_participants)) 
                : 0)) : 0,
            'budgetCode' => $activity->fundCodes ? $activity->fundCodes->pluck('code')->join(', ') : 'N/A',
            'background' => $activity->background ?? 'N/A',
            'requestForApproval' => $activity->activity_request_remarks ?? 'N/A',
            'totalBudget' => $activity->total_budget ?? '0.00',
            'headOfDivision' => $activity->matrix && $activity->matrix->division && $activity->matrix->division->head ? 
                $activity->matrix->division->head->fname . ' ' . $activity->matrix->division->head->lname : 'N/A',
            'focalPerson' => $activity->staff ? 
                $activity->staff->fname . ' ' . $activity->staff->lname : 'N/A',
            'budgetBreakdown' => $activity->activity_budget ?? [],
            'budgetIds' => is_string($activity->budget_id) 
                ? json_decode($activity->budget_id, true) 
                : ($activity->budget_id ?? []),
            'fundCodes' => \App\Models\FundCode::whereIn('id', is_string($activity->budget_id) 
                ? json_decode($activity->budget_id, true) 
                : ($activity->budget_id ?? []))->with('fundType')->get()->keyBy('id'),
            'defaultTitle' => 'ARF Request - ' . $activity->activity_title,
            'sourceId' => $activity->id,
            'modelType' => 'App\\Models\\Activity'
        ])
            @elseif(in_array($existingArf->overall_status, ['pending', 'approved', 'returned']))
            <div class="alert alert-info">
                <i class="bx bx-info-circle me-2"></i>
                An ARF request has already been created for this activity.
                <a href="{{ route('request-arf.show', $existingArf) }}" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="bx bx-show me-1"></i>View ARF Request
                </a>
            </div>
            @endif
        @endif
@endpush
