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
                        @if(can_approve_activity($activity))
                            <span badge class="badge {{((($activity->my_last_action)?$activity->my_last_action->action:$activity->status)=='passed')?'bg-success':'bg-danger'}}">{{ucwords(($activity->my_last_action)?$activity->my_last_action->action:$activity->status)}}</span>
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
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                        <td>{{ $attachment['original_name'] ?? 'Unknown' }}</td>
                                        <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                        <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info preview-attachment" 
                                                    data-file-url="{{ url('storage/'.$attachment['path']) }}" 
                                                    data-file-name="{{ $attachment['original_name'] }}"
                                                    data-file-type="{{ strtolower(pathinfo($attachment['original_name'], PATHINFO_EXTENSION)) }}">
                                                <i class="bx bx-show"></i> Preview
                                            </button>
                                            <a href="{{ url('storage/'.$attachment['path']) }}" target="_blank" class="btn btn-sm btn-secondary">
                                                <i class="bx bx-external-link"></i> Open
                                            </a>
                                            <a href="{{ url('storage/'.$attachment['path']) }}" download="{{ $attachment['original_name'] }}" class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Download
                                            </a>
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
             
                 <h6  style="color: #911C39; font-weight: 600;"> {{ $fundCode->activity }} - {{ $fundCode->code }} </h6>

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
     

            </div>
        </div>
    </div>

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

<!-- Attachment Preview Modal -->
<div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-labelledby="attachmentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachmentPreviewModalLabel">
                    <i class="bx bx-file me-2"></i>
                    <span id="previewFileName">Document Preview</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="previewContainer" class="w-100 h-100">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Close
                </button>
                <a href="#" id="downloadLink" class="btn btn-success" download>
                    <i class="bx bx-download me-1"></i> Download
                </a>
                <a href="#" id="openLink" class="btn btn-primary" target="_blank">
                    <i class="bx bx-external-link me-1"></i> Open in New Tab
                </a>
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
$(document).ready(function() {
    // Attachment preview functionality
    $('.preview-attachment').on('click', function() {
        const fileUrl = $(this).data('file-url');
        const fileName = $(this).data('file-name');
        const fileType = $(this).data('file-type');
        
        // Update modal title
        $('#previewFileName').text(fileName);
        
        // Update action links
        $('#downloadLink').attr('href', fileUrl).attr('download', fileName);
        $('#openLink').attr('href', fileUrl);
        
        // Clear previous content
        $('#previewContainer').empty();
        
        // Show loading
        $('#previewContainer').html(`
            <div class="d-flex justify-content-center align-items-center" style="height: 500px;">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading preview...</p>
                </div>
            </div>
        `);
        
        // Show modal
        $('#attachmentPreviewModal').modal('show');
        
        // Handle different file types
        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileType)) {
            // Image files
            $('#previewContainer').html(`
                <div class="text-center p-4">
                    <img src="${fileUrl}" class="img-fluid" style="max-height: 600px;" alt="${fileName}">
                </div>
            `);
        } else if (fileType === 'pdf') {
            // PDF files
            $('#previewContainer').html(`
                <iframe src="${fileUrl}" 
                        style="width: 100%; height: 600px; border: none;" 
                        title="${fileName}">
                    <p>Your browser does not support PDF preview. 
                       <a href="${fileUrl}" target="_blank">Click here to view the PDF</a>
                    </p>
                </iframe>
            `);
        } else if (['txt', 'csv', 'json', 'xml', 'html', 'htm', 'css', 'js'].includes(fileType)) {
            // Text files
            $('#previewContainer').html(`
                <div class="p-3">
                    <pre class="bg-light p-3 rounded" style="max-height: 600px; overflow-y: auto; font-size: 12px;">
                        <code id="textContent">Loading...</code>
                    </pre>
                </div>
            `);
            
            // Load text content
            $.get(fileUrl)
                .done(function(content) {
                    $('#textContent').text(content);
                })
                .fail(function() {
                    $('#textContent').text('Unable to load file content. Please download the file to view it.');
                });
        } else {
            // Unsupported file types
            $('#previewContainer').html(`
                <div class="text-center p-5">
                    <div class="mb-3">
                        <i class="bx bx-file bx-lg text-muted"></i>
                    </div>
                    <h5 class="text-muted">Preview Not Available</h5>
                    <p class="text-muted">This file type (${fileType.toUpperCase()}) cannot be previewed in the browser.</p>
                    <div class="mt-3">
                        <a href="${fileUrl}" target="_blank" class="btn btn-primary me-2">
                            <i class="bx bx-external-link me-1"></i> Open in New Tab
                        </a>
                        <a href="${fileUrl}" download="${fileName}" class="btn btn-success">
                            <i class="bx bx-download me-1"></i> Download File
                        </a>
                    </div>
                </div>
            `);
        }
    });
    
    // Clean up iframe when modal is closed
    $('#attachmentPreviewModal').on('hidden.bs.modal', function() {
        $('#previewContainer').empty();
    });
});
</script>
@endpush
