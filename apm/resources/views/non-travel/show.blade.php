@extends('layouts.app')

@section('title', 'View Non-Travel Memo')

@section('header', 'View Non-Travel Memo')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i> Back to List
    </a>
    <a href="{{ route('non-travel.edit', $nonTravel) }}" class="btn btn-warning">
        <i class="bx bx-edit me-1"></i> Edit
    </a>
</div>
@endsection

@section('content')
<div class="row g-4">
    <!-- Status Display -->
    <div class="col-12 mb-3">
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bx bx-info-circle me-2"></i>
            <div>
                <strong>Status:</strong> {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
            </div>
        </div>
    </div>
    <!-- Main Details Card -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-detail me-2 text-primary"></i>Memo Details</h5>
            </div>
            <div class="card-body p-4">
                <!-- Meta Info Panel -->
                <div class="mb-3">
                    <span class="fw-semibold text-muted">Status:</span>
                    <span class="badge bg-info text-dark">{{ ucfirst($nonTravel->overall_status ?? 'draft') }}</span>
                </div>
                <h3 class="fw-bold text-primary mb-3">{{ $nonTravel->activity_title }}</h3>
                <div class="row g-3 align-items-center mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted">Category:</span>
                        <span>{{ $nonTravel->nonTravelMemoCategory->name ?? 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted">Staff:</span>
                        <span>{{ $nonTravel->staff->name ?? 'Unknown' }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted">Memo Date:</span>
                        <span>{{ $nonTravel->memo_date ? $nonTravel->memo_date->format('F d, Y') : '-' }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted">Activity Code:</span>
                        <span>{{ $nonTravel->workplan_activity_code }}</span>
                    </div>
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted">Approval Level:</span>
                        <span>{{ $nonTravel->approval_level ?? '-' }}</span>
                    </div>
                </div>
                <!-- End Meta Info Panel -->
                
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-semibold mb-0"><i class="bx bx-receipt me-2 text-primary"></i>Memo Content</h5>
                        <hr class="flex-grow-1 ms-3">
                    </div>
                    
                    <div class="card mb-3 border">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-semibold"><i class="bx bx-info-circle me-1 text-primary"></i>Background</h6>
                        </div>
                        <div class="card-body">
                            {!! nl2br(e($nonTravel->background)) !!}
                        </div>
                    </div>
                    
                    <div class="card mb-3 border">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-semibold"><i class="bx bx-message-detail me-1 text-primary"></i>Request Remarks</h6>
                        </div>
                        <div class="card-body">
                            {!! nl2br(e($nonTravel->activity_request_remarks)) !!}
                        </div>
                    </div>
                    
                    <div class="card border">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-semibold"><i class="bx bx-check-shield me-1 text-primary"></i>Justification</h6>
                        </div>
                        <div class="card-body">
                            {!! nl2br(e($nonTravel->justification)) !!}
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-semibold mb-0"><i class="bx bx-money me-2 text-primary"></i>Budget Breakdown</h5>
                        <hr class="flex-grow-1 ms-3">
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php 
                                    $grandTotal = 0;
                                    $budgetBreakdown = is_string($nonTravel->budget_breakdown) ? json_decode($nonTravel->budget_breakdown, true) : $nonTravel->budget_breakdown;
                                    $budgetBreakdown = is_array($budgetBreakdown) ? $budgetBreakdown : [];
                                    
                                    // Remove grand_total from the array if it exists
                                    unset($budgetBreakdown['grand_total']);
                                    
                                    $rowIndex = 1;
                                @endphp
                                @forelse($budgetBreakdown as $codeId => $items)
                                    @if(is_array($items))
                                        @foreach($items as $item)
                                            @php 
                                                $itemTotal = ($item['quantity'] ?? 1) * ($item['unit_cost'] ?? 0);
                                                $grandTotal += $itemTotal;
                                            @endphp
                                            <tr>
                                                <td>{{ $rowIndex++ }}</td>
                                                <td>
                                                    {{ $item['description'] ?? 'N/A' }}
                                                    @if(isset($item['notes']) && !empty($item['notes']))
                                                        <p class="text-muted small mb-0">{{ $item['notes'] }}</p>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                                                <td class="text-end">{{ number_format($item['unit_cost'] ?? 0, 2) }}</td>
                                                <td class="text-end fw-bold">{{ number_format($itemTotal, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-3">No budget items found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <th colspan="4" class="text-end">Grand Total:</th>
                                    <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if( can_take_action_generic($nonTravel))
                    <div class="card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-check-circle me-2"></i>Approval Actions</h6>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="hidden" name="action" value="approved">
                                <button type="submit" class="btn btn-success w-100 mb-2">
                                    <i class="bx bx-check me-1"></i> Approve
                                </button>
                            </form>
                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST" class="mb-2">
                                @csrf
                                <input type="hidden" name="action" value="rejected">
                                <button type="submit" class="btn btn-danger w-100 mb-2">
                                    <i class="bx bx-x me-1"></i> Reject
                                </button>
                            </form>
                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST">
                                @csrf
                                <input type="hidden" name="action" value="returned">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="bx bx-undo me-1"></i> Return for Revision
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-md-4">


        <!-- Locations Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-map-pin me-2 text-primary"></i>Locations</h5>
            </div>
            <div class="card-body">
                @php
                    $locations = is_array($nonTravel->location_id) ? $nonTravel->location_id : (is_string($nonTravel->location_id) ? json_decode($nonTravel->location_id, true) : []);
                @endphp
                @if(!empty($locations) && count($locations) > 0)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($locations as $locationId)
                            @php
                                $location = App\Models\Location::find($locationId);
                            @endphp
                            @if($location)
                                <span class="badge bg-light text-dark border">
                                    <i class="bx bx-map me-1 text-primary"></i> {{ $location->location_name }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No locations specified</p>
                @endif
            </div>
        </div>
        <!-- Budget Items Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-money-withdraw me-2 text-primary"></i>Budget Items</h5>
            </div>
            <div class="card-body">
                @if(!empty($nonTravel->budget_id) && is_array($nonTravel->budget_id))
                    <ul class="list-group">
                        @foreach($nonTravel->budget_id as $budgetId)
                            @php
                                $budget = App\Models\Budget::find($budgetId);
                            @endphp
                            @if($budget)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bx bx-dollar-circle me-1 text-primary"></i> {{ $budget->description }}
                                    </div>
                                    <span class="badge bg-primary rounded-pill">{{ number_format($budget->amount, 2) }}</span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">No budget items specified</p>
                @endif
            </div>
        </div>
        <!-- Attachments Card -->
        @php
            $attachments = is_array($nonTravel->attachment) ? $nonTravel->attachment : (is_string($nonTravel->attachment) ? json_decode($nonTravel->attachment, true) : []);
        @endphp
        @if(!empty($attachments) && count($attachments) > 0)
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bx bx-paperclip me-2 text-primary"></i>Attachments</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach($attachments as $index => $attachment)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bx bx-file me-2 text-primary"></i>
                                        {{ $attachment['name'] ?? 'File #'.($index+1) }}
                                        <small class="text-muted d-block">
                                            {{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}
                                        </small>
                                    </div>
                                    <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bx bx-download me-1"></i> Download
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        
    
            <!-- Approval Trail Card (shared partial) -->
            @include('partials.approval-trail', ['resource' => $nonTravel])
                <!-- Submit for Approval Button (if with creator) -->
                @if(function_exists('is_with_creator_generic') && is_with_creator_generic($nonTravel))
                <div class="card mb-4">
                    <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                        <h6 class="m-0 fw-semibold text-success"><i class="bx bx-send me-2"></i>Submit for Approval</h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted mb-3">Ready to submit this non-travel memo for approval?</p>
                        <form action="{{ route('non-travel.submit-for-approval', $nonTravel) }}" method="POST" class="d-inline">
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
@endsection
