@extends('layouts.app')

@section('title', 'View Non-Travel Memo')

@section('styles')
<style>
    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-approved { @apply bg-green-100 text-green-800 border border-green-200; }
    .status-rejected { @apply bg-red-100 text-red-800 border border-red-200; }
    .status-pending { @apply bg-yellow-100 text-yellow-800 border border-yellow-200; }
    .status-draft { @apply bg-gray-100 text-gray-800 border border-gray-200; }
    
    .gradient-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .meta-card {
        background: #f8fafc;
        border-radius: 0.75rem;
        padding: 1rem;
        border: 1px solid #e2e8f0;
    }
    
    .content-section {
        border-left: 4px solid;
        background: #fafafa;
    }
    
    .content-section.bg-blue { border-left-color: #3b82f6; }
    .content-section.bg-green { border-left-color: #10b981; }
    .content-section.bg-purple { border-left-color: #8b5cf6; }
    
    .sidebar-card {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border-radius: 0.75rem;
        overflow: hidden;
    }
    
    .location-badge, .budget-item {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 0.5rem;
        padding: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .attachment-item {
        background: #faf5ff;
        border: 1px solid #e9d5ff;
        border-radius: 0.5rem;
        padding: 0.75rem;
    }
</style>
@endsection

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Enhanced Header -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-4">
                <div>
                    <h1 class="h2 fw-bold text-dark mb-0">View Non-Travel Memo</h1>
                    <p class="text-muted mb-0">Review and manage memo details</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bx bx-arrow-back"></i>
                        <span>Back to List</span>
                    </a>
                    <a href="{{ route('non-travel.edit', $nonTravel) }}" class="btn btn-warning d-flex align-items-center gap-2">
                        <i class="bx bx-edit"></i>
                        <span>Edit Memo</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <!-- Status Alert -->
        <div class="alert alert-info border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
            <div class="d-flex align-items-center">
                <i class="bx bx-info-circle fs-4 text-blue-600 me-3"></i>
                <div>
                    <h6 class="mb-1 fw-semibold">Current Status</h6>
                    <span class="status-badge status-{{ strtolower($nonTravel->overall_status ?? 'draft') }}">
                        {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Main Details Card -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header gradient-header border-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i class="bx bx-file-blank text-primary fs-4"></i>
                                Memo Details
                            </h5>
                            <span class="status-badge status-{{ strtolower($nonTravel->overall_status ?? 'draft') }}">
                                {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Title -->
                        <h2 class="fw-bold text-dark mb-4 fs-3">{{ $nonTravel->activity_title }}</h2>

                        <!-- Meta Information Grid -->
                        <div class="row g-3 mb-5">
                            <div class="col-md-6">
                                <div class="meta-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                            <i class="bx bx-purchase-tag-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted fw-medium">Category</small>
                                            <p class="mb-0 fw-semibold">{{ $nonTravel->nonTravelMemoCategory->name ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="meta-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                            <i class="bx bx-id-card text-success"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted fw-medium">Staff Member</small>
                                            <p class="mb-0 fw-semibold">{{ $nonTravel->staff->name ?? 'Unknown' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="meta-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-info bg-opacity-10 rounded-circle p-2">
                                            <i class="bx bx-calendar text-info"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted fw-medium">Memo Date</small>
                                            <p class="mb-0 fw-semibold">
                                                {{ $nonTravel->memo_date ? $nonTravel->memo_date->format('F d, Y') : '-' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="meta-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                            <i class="bx bx-shield text-warning"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted fw-medium">Approval Level</small>
                                            <p class="mb-0 fw-semibold">{{ $nonTravel->approval_level ?? '-' }}</p>
                                            @php $workflowDef = $nonTravel->workflow_definition; @endphp
                                            @if($workflowDef)
                                                <small class="text-muted">Role: {{ $workflowDef->role }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5">

                        <!-- Memo Content Sections -->
                        <div class="mb-5">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <i class="bx bx-message-detail text-primary fs-4"></i>
                                <h4 class="mb-0 fw-bold">Memo Content</h4>
                            </div>

                            <!-- Background -->
                            <div class="card content-section bg-blue border-0 mb-4">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                                        <i class="bx bx-info-circle text-primary"></i>
                                        Background
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0 lh-lg text-dark">{!! nl2br(e($nonTravel->background)) !!}</p>
                                </div>
                            </div>

                            <!-- Request Remarks -->
                            <div class="card content-section bg-green border-0 mb-4">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                                        <i class="bx bx-message-detail text-success"></i>
                                        Request Remarks
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0 lh-lg text-dark">{!! nl2br(e($nonTravel->activity_request_remarks)) !!}</p>
                                </div>
                            </div>

                            <!-- Justification -->
                            <div class="card content-section bg-purple border-0">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                                        <i class="bx bx-check-shield" style="color: #8b5cf6;"></i>
                                        Justification
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0 lh-lg text-dark">{!! nl2br(e($nonTravel->justification)) !!}</p>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5">

                        <!-- Enhanced Budget Breakdown -->
                        <div class="mb-5">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <i class="bx bx-money text-success fs-4"></i>
                                <h4 class="mb-0 fw-bold">Budget Breakdown</h4>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover border rounded-3 overflow-hidden">
                                    <thead style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
                                        <tr>
                                            <th class="border-0 fw-bold">#</th>
                                            <th class="border-0 fw-bold">Description</th>
                                            <th class="border-0 fw-bold text-center">Quantity</th>
                                            <th class="border-0 fw-bold text-end">Unit Price</th>
                                            <th class="border-0 fw-bold text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $grandTotal = 0;
                                            $budgetBreakdown = is_string($nonTravel->budget_breakdown) ? json_decode($nonTravel->budget_breakdown, true) : $nonTravel->budget_breakdown;
                                            $budgetBreakdown = is_array($budgetBreakdown) ? $budgetBreakdown : [];
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
                                                    <tr class="border-bottom">
                                                        <td class="fw-medium">{{ $rowIndex++ }}</td>
                                                        <td>
                                                            <div>
                                                                <p class="mb-1 fw-medium">{{ $item['description'] ?? 'N/A' }}</p>
                                                                @if(isset($item['notes']) && !empty($item['notes']))
                                                                    <small class="text-muted">{{ $item['notes'] }}</small>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="text-center fw-medium">{{ $item['quantity'] ?? 1 }}</td>
                                                        <td class="text-end">₱{{ number_format($item['unit_cost'] ?? 0, 2) }}</td>
                                                        <td class="text-end fw-bold text-success">₱{{ number_format($itemTotal, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bx bx-info-circle me-2"></i>No budget items found
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                                        <tr>
                                            <th colspan="4" class="text-end border-0 fs-5">Grand Total:</th>
                                            <th class="text-end border-0 fs-4 text-success">₱{{ number_format($grandTotal, 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Enhanced Approval Actions -->
                        @if(can_take_action_generic($nonTravel))
                            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                                <div class="card-header bg-transparent border-0 py-3">
                                    <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                        <i class="bx bx-check-circle"></i>
                                        Approval Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="action" value="approved">
                                                <button type="submit" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-check"></i>
                                                    Approve
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-4">
                                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="action" value="rejected">
                                                <button type="submit" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-x"></i>
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-4">
                                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="action" value="returned">
                                                <button type="submit" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-undo"></i>
                                                    Return
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Enhanced Sidebar -->
            <div class="col-lg-4">
                <!-- Locations Card -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-map-pin text-primary"></i>
                            Locations
                        </h6>
                    </div>
                    <div class="card-body">
                        @php
                            $locations = is_array($nonTravel->location_id) ? $nonTravel->location_id : (is_string($nonTravel->location_id) ? json_decode($nonTravel->location_id, true) : []);
                        @endphp
                        @if(!empty($locations) && count($locations) > 0)
                            <div class="d-flex flex-column gap-2">
                                @foreach($locations as $locationId)
                                    @php
                                        $location = App\Models\Location::find($locationId);
                                    @endphp
                                    @if($location)
                                        <div class="location-badge">
                                            <i class="bx bx-map text-primary"></i>
                                            <span class="fw-medium">{{ $location->location_name }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0 text-center py-3">
                                <i class="bx bx-info-circle me-2"></i>No locations specified
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Budget Items Card -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-money-withdraw text-success"></i>
                            Budget Items
                        </h6>
                    </div>
                    <div class="card-body">
                        @if(!empty($nonTravel->budget_id) && is_array($nonTravel->budget_id))
                            <div class="d-flex flex-column gap-3">
                                @foreach($nonTravel->budget_id as $budgetId)
                                    @php
                                        $budget = App\Models\Budget::find($budgetId);
                                    @endphp
                                    @if($budget)
                                        <div class="budget-item" style="background: #f0fdf4; border-color: #bbf7d0;">
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-dollar-circle text-success"></i>
                                                    <span class="fw-medium">{{ $budget->description }}</span>
                                                </div>
                                                <span class="badge bg-success">₱{{ number_format($budget->amount, 2) }}</span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0 text-center py-3">
                                <i class="bx bx-info-circle me-2"></i>No budget items specified
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Attachments Card -->
                @php
                    $attachments = is_array($nonTravel->attachment) ? $nonTravel->attachment : (is_string($nonTravel->attachment) ? json_decode($nonTravel->attachment, true) : []);
                @endphp
                @if(!empty($attachments) && count($attachments) > 0)
                    <div class="card sidebar-card border-0 mb-4">
                        <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);">
                            <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i class="bx bx-paperclip" style="color: #8b5cf6;"></i>
                                Attachments
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-3">
                                @foreach($attachments as $index => $attachment)
                                    <div class="attachment-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx bx-file" style="color: #8b5cf6;"></i>
                                                <div>
                                                    <p class="mb-1 fw-medium">{{ $attachment['name'] ?? 'File #'.($index+1) }}</p>
                                                    <small class="text-muted">
                                                        {{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}
                                                    </small>
                                                </div>
                                            </div>
                                            <a href="{{ Storage::url($attachment['path']) }}" target="_blank" 
                                               class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                                <i class="bx bx-download"></i>
                                                <span>Download</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Approval Trail -->
                @include('partials.approval-trail', ['resource' => $nonTravel])

                <!-- Submit for Approval -->
                @if(function_exists('is_with_creator_generic') && is_with_creator_generic($nonTravel))
                    <div class="card sidebar-card border-0" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                                <i class="bx bx-send"></i>
                                Submit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Ready to submit this non-travel memo for approval?</p>
                            <form action="{{ route('non-travel.submit-for-approval', $nonTravel) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                    <i class="bx bx-send"></i>
                                    Submit for Approval
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection