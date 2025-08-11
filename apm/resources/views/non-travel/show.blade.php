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

    /* Matrix-style metadata */
    .memo-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1.5rem;
        font-size: 0.92rem;
        line-height: 1.1;
        margin-bottom: 0.5rem;
    }
    .memo-meta-item {
        display: flex;
        align-items: center;
        min-width: 120px;
        margin-bottom: 0;
    }
    .memo-meta-item i {
        font-size: 1rem;
        margin-right: 0.3rem;
        color: #007bff;
    }
    .memo-meta-label {
        color: #888;
        font-size: 0.85em;
        margin-right: 0.2em;
    }
    .memo-meta-value {
        font-weight: 500;
    }

    .approval-level-badge {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
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
                    <a href="{{ route('non-travel.print', $nonTravel) }}" target="_blank" class="btn btn-primary d-flex align-items-center gap-2">
                        <i class="bx bx-printer"></i>
                        <span>Print PDF</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
    
        @php
            // Decode JSON fields if they are strings
            $budgetBreakdown = is_string($nonTravel->budget_breakdown) 
                ? json_decode($nonTravel->budget_breakdown, true) 
                : $nonTravel->budget_breakdown;

            $locationIds = is_string($nonTravel->location_id) 
                ? json_decode($nonTravel->location_id, true) 
                : $nonTravel->location_id;

            $attachments = is_string($nonTravel->attachment) 
                ? json_decode($nonTravel->attachment, true) 
                : $nonTravel->attachment;

            // Ensure variables are arrays
            $budgetBreakdown = is_array($budgetBreakdown) ? $budgetBreakdown : [];
            $locationIds = is_array($locationIds) ? $locationIds : [];
            $attachments = is_array($attachments) ? $attachments : [];
        @endphp

        <div class="row">
            <div class="col-lg-8">
                <!-- Enhanced Memo Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="bx bx-info-circle me-2 text-primary"></i>Memo Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="memo-meta-row">
                            <div class="memo-meta-item">
                                <i class="bx bx-calendar-alt"></i>
                                <span class="memo-meta-label">Memo Date:</span>
                                <span class="memo-meta-value">{{ $nonTravel->memo_date ? \Carbon\Carbon::parse($nonTravel->memo_date)->format('M d, Y') : 'Not set' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-user"></i>
                                <span class="memo-meta-label">Requestor:</span>
                                <span class="memo-meta-value">{{ $nonTravel->staff ? ($nonTravel->staff->fname . ' ' . $nonTravel->staff->lname) : 'Not assigned' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-category"></i>
                                <span class="memo-meta-label">Category:</span>
                                <span class="memo-meta-value">{{ $nonTravel->nonTravelMemoCategory ? $nonTravel->nonTravelMemoCategory->category_name : 'Not categorized' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-code-alt"></i>
                                <span class="memo-meta-label">Activity Code:</span>
                                <span class="memo-meta-value">{{ $nonTravel->workplan_activity_code ?? 'Not specified' }}</span>
                            </div>
                        </div>
                        
                        @if($nonTravel->overall_status !== 'approved')
                            <div class="mt-3 p-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 0.5rem; border: 1px solid #bfdbfe;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bx bx-user-check text-blue-600"></i>
                                    <span class="fw-semibold text-blue-900">Current Approval Level</span>
                                </div>
                                <div class="memo-meta-row">
                                    <div class="memo-meta-item">
                                        <i class="bx bx-badge-check"></i>
                                        <span class="memo-meta-value">{{ $nonTravel->workflow_definition ? $nonTravel->workflow_definition->role : 'Not Assigned' }}</span>
                                    </div>
                                    <div class="memo-meta-item">
                                        <i class="bx bx-user"></i>
                                        <span class="memo-meta-value">{{ $nonTravel->current_actor ? ($nonTravel->current_actor->fname . ' ' . $nonTravel->current_actor->lname) : 'No Approver Assigned' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Activity Title -->
                <div class="card content-section bg-blue border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                            <i class="bx bx-bullseye text-primary"></i>
                            Activity Title
                        </h6>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-0 fw-bold text-dark">{{ $nonTravel->activity_title ?? 'No title provided' }}</h5>
                    </div>
                </div>

                <!-- Content Sections -->
                <div class="mb-5">
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
                                                <td class="text-end">₱{{ number_format($itemTotal, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No budget breakdown available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Grand Total</td>
                                    <td class="text-end fw-bold">₱{{ number_format($grandTotal, 2) }}</td>
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
                                            <span class="fw-medium">{{ $location->name }}</span>
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
                @php
                    $budgetIds = is_array($nonTravel->budget_id) 
                        ? $nonTravel->budget_id 
                        : (is_string($nonTravel->budget_id) ? json_decode($nonTravel->budget_id, true) : []);
                @endphp

                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-money-withdraw text-success"></i>
                            Budget Items
                        </h6>
                    </div>
                    <div class="card-body">
                        @if(!empty($budgetIds))
                            <div class="d-flex flex-column gap-3">
                                @foreach($budgetIds as $budgetId)
                                    @php
                                        $budget = App\Models\FundCode::find($budgetId);
                                    @endphp
                                    @if($budget)
                                        <div class="budget-item" style="background: #f0fdf4; border-color: #bbf7d0;">
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bx bx-dollar-circle text-success"></i>
                                                    <span class="fw-medium">{{ $budget->code }} | {{ $budget->funder->name ?? 'No Funder' }}</span>
                                                </div>
                                                <span class="badge bg-success">₱{{ number_format($budget->budget_balance, 2) }}</span>
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
                    $attachments = is_array($nonTravel->attachment) 
                        ? $nonTravel->attachment 
                        : (is_string($nonTravel->attachment) ? json_decode($nonTravel->attachment, true) : []);
                    $attachments = is_array($attachments) ? $attachments : [];
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