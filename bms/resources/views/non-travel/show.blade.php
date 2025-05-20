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
    <!-- Main Details Card -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-detail me-2 text-primary"></i>Memo Details</h5>
                <span class="badge bg-primary rounded-pill">{{ $nonTravel->nonTravelMemoCategory->name ?? 'N/A' }}</span>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-12 mb-3">
                        <h4 class="fw-bold text-primary">{{ $nonTravel->activity_title }}</h4>
                        <p class="text-muted mb-0">
                            <i class="bx bx-code-block me-1"></i> Activity Code: <strong>{{ $nonTravel->workplan_activity_code }}</strong>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="bx bx-calendar me-1 text-primary"></i> Memo Date:</p>
                        <p class="mb-0 fs-5">{{ $nonTravel->memo_date->format('F d, Y') }}</p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="bx bx-user me-1 text-primary"></i> Staff Member:</p>
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2 bg-light rounded-circle">
                                <span class="avatar-text">{{ substr($nonTravel->staff->name ?? 'U', 0, 1) }}</span>
                            </div>
                            <p class="mb-0 fs-5">{{ $nonTravel->staff->name ?? 'Unknown' }}</p>
                        </div>
                    </div>
                </div>
                
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
                                @php $grandTotal = 0; @endphp
                                @forelse($nonTravel->budget_breakdown as $index => $item)
                                    @php 
                                        $itemTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                                        $grandTotal += $itemTotal;
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            {{ $item['description'] ?? 'N/A' }}
                                            @if(isset($item['notes']) && !empty($item['notes']))
                                                <p class="text-muted small mb-0">{{ $item['notes'] }}</p>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                                        <td class="text-end">{{ number_format($item['unit_price'] ?? 0, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($itemTotal, 2) }}</td>
                                    </tr>
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
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-md-4">
        <!-- Workflows Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-git-branch me-2 text-primary"></i>Workflows</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <p class="mb-1 fw-semibold text-muted"><i class="bx bx-git-branch me-1 text-primary"></i> Forward Workflow:</p>
                    <p class="mb-0 fs-6">{{ $nonTravel->forwardWorkflow->name ?? 'Not specified' }}</p>
                </div>
                <div>
                    <p class="mb-1 fw-semibold text-muted"><i class="bx bx-git-repo-forked me-1 text-primary"></i> Reverse Workflow:</p>
                    <p class="mb-0 fs-6">{{ $nonTravel->reverseWorkflow->name ?? 'Not specified' }}</p>
                </div>
            </div>
        </div>
        
        <!-- Locations Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-map-pin me-2 text-primary"></i>Locations</h5>
            </div>
            <div class="card-body">
                @if(!empty($nonTravel->location_id) && is_array($nonTravel->location_id))
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($nonTravel->location_id as $locationId)
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
        @if(!empty($nonTravel->attachment) && count($nonTravel->attachment) > 0)
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bx bx-paperclip me-2 text-primary"></i>Attachments</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach($nonTravel->attachment as $index => $attachment)
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
    </div>
</div>
@endsection
