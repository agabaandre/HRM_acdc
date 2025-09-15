<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Request - {{ $serviceRequest->request_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007e33;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #007e33;
            margin: 0;
            font-size: 24px;
        }
        
        .header h2 {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 16px;
            font-weight: normal;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h3 {
            color: #007e33;
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }
        
        .info-value {
            display: table-cell;
            width: 70%;
            padding: 5px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-approved { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-returned { background: #dbeafe; color: #2563eb; }
        
        .budget-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .budget-table th,
        .budget-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .budget-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #007e33;
        }
        
        .budget-table .total-row {
            background-color: #e9f7ef;
            font-weight: bold;
        }
        
        .budget-section {
            margin-bottom: 20px;
        }
        
        .budget-section h4 {
            color: #007e33;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .cost-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 5px;
        }
        
        .cost-breakdown {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .specifications {
            margin-bottom: 20px;
        }
        
        .specifications ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .specifications li {
            margin-bottom: 5px;
        }
        
        .attachments {
            margin-bottom: 20px;
        }
        
        .attachment-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 5px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .text-success {
            color: #059669;
        }
        
        .text-primary {
            color: #007e33;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>SERVICE REQUEST</h1>
        <h2>{{ $serviceRequest->request_number ?? 'N/A' }}</h2>
        <div style="margin-top: 10px;">
            <span class="status-badge status-{{ $serviceRequest->overall_status ?? 'draft' }}">
                {{ strtoupper($serviceRequest->overall_status ?? 'draft') }}
            </span>
        </div>
    </div>

    <!-- Service Information -->
    <div class="info-section">
        <h3>Service Information</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Service Title:</div>
                <div class="info-value">{{ $serviceRequest->service_title ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Service Type:</div>
                <div class="info-value">{{ $serviceRequest->service_type ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Priority:</div>
                <div class="info-value">{{ $serviceRequest->priority ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Required By Date:</div>
                <div class="info-value">{{ $serviceRequest->required_by_date ? \Carbon\Carbon::parse($serviceRequest->required_by_date)->format('M d, Y') : 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Location:</div>
                <div class="info-value">{{ $serviceRequest->location ?? 'Not specified' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estimated Cost:</div>
                <div class="info-value font-bold text-success">${{ number_format($serviceRequest->estimated_cost ?? 0, 2) }}</div>
            </div>
        </div>
        
        @if($serviceRequest->description)
        <div class="info-row">
            <div class="info-label">Description:</div>
            <div class="info-value">{{ $serviceRequest->description }}</div>
        </div>
        @endif
        
        @if($serviceRequest->justification)
        <div class="info-row">
            <div class="info-label">Justification:</div>
            <div class="info-value">{{ $serviceRequest->justification }}</div>
        </div>
        @endif
    </div>

    <!-- Request Details -->
    <div class="info-section">
        <h3>Request Details</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Request Date:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($serviceRequest->request_date)->format('M d, Y') }}</div>
            </div>
            @if($serviceRequest->staff)
            <div class="info-row">
                <div class="info-label">Requested By:</div>
                <div class="info-value">{{ $serviceRequest->staff->fname }} {{ $serviceRequest->staff->lname }} ({{ $serviceRequest->staff->position ?? 'Staff' }})</div>
            </div>
            @endif
            @if($serviceRequest->division)
            <div class="info-row">
                <div class="info-label">Division:</div>
                <div class="info-value">{{ $serviceRequest->division->name ?? $serviceRequest->division->division_name }}</div>
            </div>
            @endif
            @if($serviceRequest->activity)
            <div class="info-row">
                <div class="info-label">Related Activity:</div>
                <div class="info-value">{{ $serviceRequest->activity->title ?? 'Untitled Activity' }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Budget Information -->
    @if($budgetBreakdown || $internalParticipantsCost || $externalParticipantsCost || $otherCosts)
    <div class="info-section">
        <h3>Budget Information</h3>
        
        <!-- Original Budget Breakdown -->
        @if($budgetBreakdown && is_array($budgetBreakdown))
        <div class="budget-section">
            <h4>Original Activity Budget Breakdown</h4>
            @foreach($budgetBreakdown as $fundCode => $items)
                @if($fundCode !== 'grand_total' && is_array($items))
                <div style="margin-bottom: 15px;">
                    <div class="font-bold text-primary">Fund Code: {{ $fundCode }}</div>
                    @foreach($items as $item)
                    <div class="cost-item">
                        <div class="cost-breakdown">
                            <span class="font-bold">{{ $item['cost'] ?? 'N/A' }}</span>
                            <span class="text-success font-bold">${{ number_format(($item['unit_cost'] ?? 0) * ($item['units'] ?? 0) * ($item['days'] ?? 0), 2) }}</span>
                        </div>
                        <div style="font-size: 11px; color: #666;">
                            Unit: ${{ number_format($item['unit_cost'] ?? 0, 2) }} | 
                            Units: {{ $item['units'] ?? 0 }} | 
                            Days: {{ $item['days'] ?? 0 }}
                        </div>
                        @if(isset($item['description']) && $item['description'])
                        <div style="font-size: 11px; color: #666; margin-top: 3px;">
                            {{ $item['description'] }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            @endforeach
            
            @if(isset($budgetBreakdown['grand_total']))
            <div class="cost-item" style="background: #e9f7ef; border-color: #007e33;">
                <div class="text-center font-bold text-primary">
                    Total Activity Budget: ${{ number_format($budgetBreakdown['grand_total'], 2) }}
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Budget Summary -->
        @if($serviceRequest->original_total_budget || $serviceRequest->new_total_budget)
        <div class="budget-section">
            <h4>Budget Summary</h4>
            <table class="budget-table">
                <tr>
                    <td class="font-bold">Original Budget:</td>
                    <td class="text-right font-bold text-primary">${{ number_format($serviceRequest->original_total_budget ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td class="font-bold">New Total Budget:</td>
                    <td class="text-right font-bold text-success">${{ number_format($serviceRequest->new_total_budget ?? 0, 2) }}</td>
                </tr>
            </table>
        </div>
        @endif

        <!-- Internal Participants Cost -->
        @if($internalParticipantsCost && is_array($internalParticipantsCost))
        <div class="budget-section">
            <h4>Internal Participants Cost</h4>
            <table class="budget-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Cost Type</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($internalParticipantsCost as $participant)
                    <tr>
                        <td>
                            @if(isset($participant['staff_id']))
                                @php
                                    $staff = \App\Models\Staff::find($participant['staff_id']);
                                @endphp
                                {{ $staff ? $staff->fname . ' ' . $staff->lname : 'Unknown Staff' }}
                                @if($staff)
                                <br><small style="color: #666;">{{ $staff->position ?? 'Staff' }}</small>
                                @endif
                            @else
                                Unknown Staff
                            @endif
                        </td>
                        <td>{{ $participant['cost_type'] ?? 'N/A' }}</td>
                        <td class="text-right font-bold text-success">${{ number_format($participant['total'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- External Participants Cost -->
        @if($externalParticipantsCost && is_array($externalParticipantsCost))
        <div class="budget-section">
            <h4>External Participants Cost</h4>
            <table class="budget-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Cost Type</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($externalParticipantsCost as $participant)
                    <tr>
                        <td class="font-bold">{{ $participant['name'] ?? 'N/A' }}</td>
                        <td><small style="color: #666;">{{ $participant['email'] ?? 'N/A' }}</small></td>
                        <td>{{ $participant['cost_type'] ?? 'N/A' }}</td>
                        <td class="text-right font-bold text-success">${{ number_format($participant['total'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Other Costs -->
        @if($otherCosts && is_array($otherCosts))
        <div class="budget-section">
            <h4>Other Costs</h4>
            <table class="budget-table">
                <thead>
                    <tr>
                        <th>Cost Type</th>
                        <th>Unit Cost</th>
                        <th>Days</th>
                        <th>Description</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($otherCosts as $cost)
                    <tr>
                        <td class="font-bold">{{ $cost['cost_type'] ?? 'N/A' }}</td>
                        <td>${{ number_format($cost['unit_cost'] ?? 0, 2) }}</td>
                        <td>{{ $cost['days'] ?? 0 }}</td>
                        <td>{{ $cost['description'] ?? 'N/A' }}</td>
                        <td class="text-right font-bold text-success">${{ number_format(($cost['unit_cost'] ?? 0) * ($cost['days'] ?? 0), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    <!-- Specifications -->
    @if($specifications && count($specifications) > 0)
    <div class="info-section">
        <h3>Specifications</h3>
        <div class="specifications">
            <ul>
                @foreach($specifications as $spec)
                <li>{{ $spec }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <!-- Attachments -->
    @if($attachments && count($attachments) > 0)
    <div class="info-section">
        <h3>Attachments</h3>
        <div class="attachments">
            @foreach($attachments as $attachment)
            <div class="attachment-item">
                <div class="font-bold">{{ $attachment['name'] ?? 'Unknown File' }}</div>
                <div style="font-size: 11px; color: #666;">{{ $attachment['size'] ?? 'Unknown size' }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Remarks -->
    @if($serviceRequest->remarks)
    <div class="info-section">
        <h3>Remarks</h3>
        <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px;">
            {{ $serviceRequest->remarks }}
        </div>
    </div>
    @endif

    <!-- Approval Trail -->
    @if($serviceRequest->serviceRequestApprovalTrails && $serviceRequest->serviceRequestApprovalTrails->count() > 0)
    <div class="info-section">
        <h3>Approval Trail</h3>
        <table class="budget-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Comments</th>
                    <th>By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($serviceRequest->serviceRequestApprovalTrails as $trail)
                <tr>
                    <td class="font-bold">{{ ucfirst($trail->action) }}</td>
                    <td>{{ $trail->comments }}</td>
                    <td>
                        @if($trail->staff)
                        {{ $trail->staff->fname }} {{ $trail->staff->lname }}
                        @else
                        System
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($trail->created_at)->format('M d, Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>Generated on {{ \Carbon\Carbon::now()->format('M d, Y H:i:s') }}</p>
        <p>Service Request #{{ $serviceRequest->request_number ?? 'N/A' }}</p>
    </div>
</body>
</html>

