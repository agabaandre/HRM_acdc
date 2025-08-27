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

    /* Summary table styles */
    .summary-table {
        background: white;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .summary-table .table {
        margin-bottom: 0;
    }
    
    .summary-table .table th {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: none;
        font-weight: 600;
        color: #374151;
        padding: 1rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .summary-table .table td {
        border: none;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
        vertical-align: middle;
    }
    
    .summary-table .table tr:last-child td {
        border-bottom: none;
    }
    
    .summary-table .table tr:hover {
        background-color: #f9fafb;
    }
    
    .field-label {
        font-weight: 600;
        color: #374151;
        min-width: 150px;
    }
    
    .field-value {
        color: #1f2937;
        font-weight: 500;
    }
    
    .field-value.null {
        color: #9ca3af;
        font-style: italic;
    }
    
    .status-indicator {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .status-draft { background: #f3f4f6; color: #6b7280; }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-approved { background: #d1fae5; color: #059669; }
    .status-rejected { background: #fee2e2; color: #dc2626; }
    .status-returned { background: #dbeafe; color: #2563eb; }
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

                    @if($nonTravel->overall_status === 'draft' && $nonTravel->staff_id == user_session('staff_id'))
                        <a href="{{ route('non-travel.edit', $nonTravel) }}" class="btn btn-warning d-flex align-items-center gap-2">
                            <i class="bx bx-edit"></i>
                            <span>Edit Memo</span>
                        </a>
                    @endif
                    <a href="{{ route('non-travel.status', $nonTravel) }}" class="btn btn-info d-flex align-items-center gap-2">
                        <i class="bx bx-info-circle"></i>
                        <span>Approval Status</span>
                    </a>
                    @if($nonTravel->overall_status === 'approved')
                        <a href="{{ route('non-travel.print', $nonTravel) }}" target="_blank" class="btn btn-primary d-flex align-items-center gap-2">
                            <i class="bx bx-printer"></i>
                            <span>Print PDF</span>
                        </a>
                    @endif
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

            // Calculate total budget
            $totalBudget = 0;
            if (!empty($budgetBreakdown)) {
                foreach ($budgetBreakdown as $codeId => $items) {
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $totalBudget += ($item['quantity'] ?? 1) * ($item['unit_cost'] ?? 0);
                        }
                    }
                }
            }

            // Get locations
            $locations = [];
            if (!empty($locationIds)) {
                $locations = \App\Models\Location::whereIn('id', $locationIds)->get();
            }

            // Get budget codes
            $budgetCodes = [];
            if (!empty($nonTravel->budget_id)) {
                $budgetIds = is_array($nonTravel->budget_id) ? $nonTravel->budget_id : json_decode($nonTravel->budget_id, true);
                if (is_array($budgetIds)) {
                    $budgetCodes = \App\Models\FundCode::with('funder')->whereIn('id', $budgetIds)->get();
                }
            }
        @endphp

        <!-- Summary Table -->
        <div class="summary-table mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-table me-2 text-primary"></i>Memo Summary
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <tbody>
                        <!-- Basic Information -->
                        <tr>
                            <td class="field-label">Memo ID</td>
                            <td class="field-value">#{{ $nonTravel->id }}</td>
                            <td class="field-label">Status</td>
                            <td class="field-value">
                                <span class="badge bg-{{ $nonTravel->overall_status === 'approved' ? 'success' : ($nonTravel->overall_status === 'pending' ? 'warning' : ($nonTravel->overall_status === 'rejected' ? 'danger' : 'secondary')) }} fs-6">
                                    {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                                </span>
                                @if($nonTravel->overall_status === 'pending')
                                    <a href="{{ route('non-travel.status', $nonTravel) }}" class="btn btn-sm btn-outline-info ms-2">
                                        <i class="bx bx-info-circle me-1"></i>View Status
                                    </a>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Activity Title</td>
                            <td class="field-value" colspan="3">{{ $nonTravel->activity_title ?? 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">Requestor</td>
                            <td class="field-value">
                                {{ $nonTravel->staff ? ($nonTravel->staff->fname . ' ' . $nonTravel->staff->lname) : 'Not assigned' }}
                            </td>
                            <td class="field-label">Division</td>
                            <td class="field-value">
                                {{ $nonTravel->division ? $nonTravel->division->division_name : 'Not assigned' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Memo Date</td>
                            <td class="field-value">
                                {{ $nonTravel->memo_date ? \Carbon\Carbon::parse($nonTravel->memo_date)->format('M d, Y') : 'Not set' }}
                            </td>
                            <td class="field-label">Category</td>
                            <td class="field-value">
                                {{ $nonTravel->nonTravelMemoCategory ? $nonTravel->nonTravelMemoCategory->name : 'Not categorized' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Activity Code</td>
                            <td class="field-value">
                                {{ $nonTravel->workplan_activity_code ?? 'Not specified' }}
                            </td>
                            <td class="field-label">Created Date</td>
                            <td class="field-value">
                                {{ $nonTravel->created_at ? $nonTravel->created_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                        </tr>
                        
                        <!-- Locations -->
                        <tr>
                            <td class="field-label">Locations</td>
                            <td class="field-value" colspan="3">
                                @if($locations->count() > 0)
                                    @foreach($locations as $location)
                                        <span class="badge bg-primary me-1">{{ $location->name }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No locations specified</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Budget Information -->
                        <tr>
                            <td class="field-label">Budget Codes</td>
                            <td class="field-value" colspan="3">
                                @if($budgetCodes->count() > 0)
                                    @foreach($budgetCodes as $budget)
                                        <span class="badge bg-success me-1">
                                            {{ $budget->code }} 
                                            @if($budget->funder)
                                                ({{ $budget->funder->name }})
                                            @endif
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No budget codes specified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Total Budget</td>
                            <td class="field-value fw-bold text-success">${{ number_format($totalBudget, 2) }}</td>
                            <td class="field-label">Budget Items</td>
                            <td class="field-value">
                                @php
                                    $itemCount = 0;
                                    if (!empty($budgetBreakdown)) {
                                        foreach ($budgetBreakdown as $codeId => $items) {
                                            if (is_array($items)) {
                                                $itemCount += count($items);
                                            }
                                        }
                                    }
                                @endphp
                                {{ $itemCount }} item(s)
                            </td>
                        </tr>
                        
                        <!-- Approval Information -->
                        <tr>
                            <td class="field-label">Approval Level</td>
                            <td class="field-value">
                                <span class="badge bg-primary">{{ $nonTravel->approval_level ?? 0 }}</span>
                                @if($nonTravel->workflow_definition)
                                    <br><small class="text-muted">{{ $nonTravel->workflow_definition->role ?? 'Role not specified' }}</small>
                                @endif
                            </td>
                            <td class="field-label">Current Approver</td>
                            <td class="field-value">
                                @if($nonTravel->current_actor)
                                    {{ $nonTravel->current_actor->fname . ' ' . $nonTravel->current_actor->lname }}
                                    @if($nonTravel->workflow_definition && $nonTravel->workflow_definition->is_division_specific)
                                        <br><small class="text-muted">Division Specific</small>
                                    @endif
                                @else
                                    <span class="text-muted">No approver assigned</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Attachments -->
                        <tr>
                            <td class="field-label">Attachments</td>
                            <td class="field-value" colspan="3">
                                @if(!empty($attachments) && count($attachments) > 0)
                                    {{ count($attachments) }} file(s) attached
                                @else
                                    <span class="text-muted">No attachments</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Workflow Information -->
                        <tr>
                            <td class="field-label">Workflow</td>
                            <td class="field-value">
                                @if($nonTravel->forward_workflow_id)
                                    <span class="badge bg-info">{{ $nonTravel->forwardWorkflow->workflow_name ?? 'Workflow #' . $nonTravel->forward_workflow_id }}</span>
                                @else
                                    <span class="text-muted">No workflow assigned</span>
                                @endif
                            </td>
                            <td class="field-label">Workflow Role</td>
                            <td class="field-value">
                                @if($nonTravel->workflow_definition)
                                    <span class="badge bg-secondary">{{ $nonTravel->workflow_definition->role ?? 'Not specified' }}</span>
                                    @if($nonTravel->workflow_definition->is_division_specific)
                                        <br><small class="text-muted">Division Specific</small>
                                    @endif
                                @else
                                    <span class="text-muted">No role assigned</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Last Updated -->
                        <tr>
                            <td class="field-label">Last Updated</td>
                            <td class="field-value">
                                {{ $nonTravel->updated_at ? $nonTravel->updated_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                            <td class="field-label">Status</td>
                            <td class="field-value">
                                <span class="badge bg-{{ $nonTravel->overall_status === 'approved' ? 'success' : ($nonTravel->overall_status === 'pending' ? 'warning' : ($nonTravel->overall_status === 'rejected' ? 'danger' : 'secondary')) }}">
                                    {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

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
                                <span class="memo-meta-value">{{ $nonTravel->nonTravelMemoCategory ? $nonTravel->nonTravelMemoCategory->name : 'Not categorized' }}</span>
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
                                                <td class="text-end">${{ number_format($item['unit_cost'] ?? 0, 2) }}</td>
                                                <td class="text-end">${{ number_format($itemTotal, 2) }}</td>
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
                                    <td class="text-end fw-bold">${{ number_format($grandTotal, 2) }}</td>
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
                                Approval Actions - Level {{ $nonTravel->approval_level ?? 0 }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Current Level:</strong> {{ $nonTravel->approval_level ?? 0 }}
                                @if($nonTravel->workflow_definition)
                                    - <strong>Role:</strong> {{ $nonTravel->workflow_definition->role ?? 'Not specified' }}
                                @endif
                            </div>
                            
                            <form action="{{ route('non-travel.update-status', $nonTravel) }}" method="POST" id="approvalForm">
                                @csrf
                                <input type="hidden" name="debug_approval" value="1">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Comments (Optional)</label>
                                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-check"></i>
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-undo"></i>
                                                Return
                                            </button>
                                            <button type="submit" name="action" value="rejected" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-x"></i>
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
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
                        @if($locations->count() > 0)
                            <div class="d-flex flex-column gap-2">
                                @foreach($locations as $location)
                                    <div class="location-badge">
                                        <i class="bx bx-map text-primary"></i>
                                        <span class="fw-medium">{{ $location->name }}</span>
                                    </div>
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
                        @if($budgetCodes->count() > 0)
                            <div class="d-flex flex-column gap-3">
                                @foreach($budgetCodes as $budget)
                                    <div class="budget-item" style="background: #f0fdf4; border-color: #bbf7d0;">
                                        <div class="d-flex justify-content-between align-items-center w-100">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx bx-dollar-circle text-success"></i>
                                                <span class="fw-medium">{{ $budget->code }} | {{ $budget->funder->name ?? 'No Funder' }}</span>
                                            </div>
                                            <span class="badge bg-success">${{ number_format($budget->budget_balance, 2) }}</span>
                                        </div>
                                    </div>
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

                <!-- Quick Approval Status -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-trending-up text-info"></i>
                            Approval Progress
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Current Level</span>
                                <span class="badge bg-primary fs-6">{{ $nonTravel->approval_level ?? 0 }}</span>
                            </div>
                            @if($nonTravel->workflow_definition)
                                <div class="mb-2">
                                    <small class="text-muted">Role:</small><br>
                                    <strong>{{ $nonTravel->workflow_definition->role ?? 'Not specified' }}</strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Division Specific:</small><br>
                                    <span class="badge bg-{{ $nonTravel->workflow_definition->is_division_specific ? 'info' : 'secondary' }}">
                                        {{ $nonTravel->workflow_definition->is_division_specific ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                            @endif
                            @if($nonTravel->current_actor)
                                <div class="mb-2">
                                    <small class="text-muted">Current Supervisor:</small><br>
                                    <strong class="text-primary">{{ $nonTravel->current_actor->fname . ' ' . $nonTravel->current_actor->lname }}</strong>
                                    @if($nonTravel->current_actor->job_name)
                                        <br><small class="text-muted">{{ $nonTravel->current_actor->job_name }}</small>
                                    @endif
                                    @if($nonTravel->current_actor->division_name)
                                        <br><small class="text-muted">{{ $nonTravel->current_actor->division_name }}</small>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('non-travel.status', $nonTravel) }}" class="btn btn-outline-info btn-sm w-100">
                            <i class="bx bx-info-circle me-1"></i>View Full Status
                        </a>
                    </div>
                </div>

                <!-- Approval Trail -->
                @include('partials.approval-trail', ['resource' => $nonTravel])

                <!-- Submit for Approval -->
                @if($nonTravel->overall_status === 'draft' && $nonTravel->staff_id == user_session('staff_id'))
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
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> Once submitted, you won't be able to edit this memo until it's returned for revision.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection