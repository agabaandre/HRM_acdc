<!-- Approval Workflow Overview -->

@push('styles')
<style>
/* Approval Workflow Container - Horizontal Row Layout */
.approval-workflow-container {
    display: flex !important;
    align-items: flex-start !important;
    gap: 15px !important;
    overflow-x: auto !important;
    padding: 10px 0 !important;
    margin-bottom: 20px !important;
    min-width: 100% !important;
    flex-wrap: nowrap !important;
    flex-direction: row !important;
}

.approval-step-wrapper {
    display: flex !important;
    align-items: center !important;
    flex-shrink: 0 !important;
    flex-direction: row !important;
}

/* Approval Step Card */
.approval-step-card {
    background: #f8f9fa !important;
    border: 2px solid #e9ecef !important;
    border-radius: 12px !important;
    padding: 15px !important;
    width: 240px !important;
    min-width: 240px !important;
    max-width: 240px !important;
    min-height: 260px !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    flex-shrink: 0 !important;
    display: block !important;
}

.approval-step-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.approval-step-card.current-step {
    border-color: #28a745;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

/* Step Header */
.step-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.step-order .badge {
    font-size: 14px;
    padding: 6px 12px;
    font-weight: 600;
}

.step-status .badge {
    font-size: 12px;
    padding: 4px 8px;
}

/* Step Content */
.step-content {
    height: calc(100% - 50px);
    display: flex;
    flex-direction: column;
}

.step-role {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
    font-size: 16px;
}

.step-badges {
    margin-bottom: 15px;
}

.step-badges .badge {
    font-size: 11px;
    padding: 4px 8px;
    margin-right: 5px;
}

/* Approvers Section */
.approvers-section {
    flex: 1;
    max-height: 180px;
    overflow-y: auto;
}

.approvers-title {
    color: #6c757d;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.approver-item {
    background: rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: all 0.2s ease;
}

.approver-item:hover {
    background: rgba(0,0,0,0.08);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.approver-info {
    flex: 1;
    margin-right: 10px;
}

.approver-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 13px;
    margin-bottom: 2px;
}

.approver-details {
    color: #6c757d;
    font-size: 11px;
    margin-bottom: 2px;
}

.approver-email {
    color: #6c757d;
    font-size: 10px;
}

.approver-type .badge {
    font-size: 10px;
    padding: 3px 6px;
}

.no-approvers {
    color: #6c757d;
    font-size: 12px;
    text-align: center;
    padding: 20px;
    font-style: italic;
}

/* Connecting Arrow */
.approval-arrow {
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 5px;
    box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.approval-arrow:hover {
    transform: translateY(-2px) scale(1.1);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.approval-arrow i {
    font-size: 16px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

/* Scrollbar Styling */
.approvers-section::-webkit-scrollbar {
    width: 4px;
}

.approvers-section::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
    border-radius: 2px;
}

.approvers-section::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.3);
    border-radius: 2px;
}

.approvers-section::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.5);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .approval-step-card {
        width: 220px;
        min-width: 220px;
        max-width: 220px;
    }
}

@media (max-width: 992px) {
    .approval-step-card {
        width: 200px;
        min-width: 200px;
        max-width: 200px;
    }
}

@media (max-width: 768px) {
    .approval-workflow-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .approval-step-wrapper {
        flex-direction: column;
    }
    
    .approval-arrow {
        transform: rotate(90deg);
        margin: 10px 0;
    }
    
    .approval-step-card {
        min-width: 100%;
        max-width: 100%;
    }
}

/* Badge Styling */
.badge {
    font-weight: 500;
}

/* Force horizontal layout - Override any Bootstrap conflicts */
.approval-workflow-container * {
    box-sizing: border-box !important;
}

.approval-workflow-container .row {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
}

.approval-workflow-container .col,
.approval-workflow-container .col-md-6,
.approval-workflow-container .col-lg-4,
.approval-workflow-container .col-xl-3 {
    display: block !important;
    flex: none !important;
    width: auto !important;
    max-width: none !important;
}
</style>
@endpush

@if(!empty($approvalOrderMap))
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-header bg-white border-bottom">
            <h6 class="mb-0 text-success">
                <i class="fas fa-layer-group me-2"></i>Approval Workflow Overview
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="approval-workflow-container" style="display: grid !important; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important; gap: 20px !important; max-height: 600px !important; overflow-y: auto !important;">
                @php
                    // Filter out levels with no approvers and renumber the order
                    $levelsWithApprovers = collect($approvalOrderMap)->filter(function($level) {
                        return count($level['approvers']) > 0;
                    })->values();
                    
                    $renumberedLevels = $levelsWithApprovers->map(function($level, $index) {
                        $level['display_order'] = $index + 1;
                        return $level;
                    });
                @endphp
                
                @foreach($renumberedLevels as $index => $level)
                    <div class="approval-step-wrapper" style="position: relative !important;">
                        <div class="approval-step-card {{ $level['is_current_level'] ? 'current-step' : '' }}" 
                             style="background: {{ $level['is_completed'] ? '#d1fae5' : ($level['is_pending'] ? '#fef3c7' : '#f8f9fa') }}; width: 100% !important; min-height: 300px !important; display: block !important; border-radius: 12px !important; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important; border: 1px solid #e9ecef !important; transition: all 0.3s ease !important; margin: 2px !important; cursor: pointer !important;" 
                             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" 
                             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'">
                            <div class="step-header" style="padding: 20px 20px 15px 20px !important; border-bottom: 1px solid rgba(0,0,0,0.1) !important; margin-bottom: 15px !important;">
                                <div class="step-order" style="margin-bottom: 10px !important;">
                                    <span class="badge bg-{{ $level['is_completed'] ? 'success' : ($level['is_pending'] ? 'warning' : 'secondary') }}" style="font-size: 14px !important; padding: 8px 12px !important; font-weight: 600 !important; border-radius: 20px !important;">
                                        Step {{ $level['display_order'] }}
                                    </span>
                                </div>
                                <div class="step-status">
                                    @if($level['is_current_level'])
                                        <span class="badge bg-success" style="font-size: 12px !important; padding: 6px 10px !important; border-radius: 15px !important;">Current</span>
                                    @elseif($level['is_completed'])
                                        <span class="badge bg-success" style="font-size: 12px !important; padding: 6px 10px !important; border-radius: 15px !important;">✓ Completed</span>
                                    @elseif($level['is_pending'])
                                        <span class="badge bg-warning" style="font-size: 12px !important; padding: 6px 10px !important; border-radius: 15px !important;">⏳ Pending</span>
                                    @else
                                        <span class="badge bg-secondary" style="font-size: 12px !important; padding: 6px 10px !important; border-radius: 15px !important;">⏸ Future</span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="step-content" style="padding: 0 20px 20px 20px !important; flex: 1 !important; display: flex !important; flex-direction: column !important;">
                                <h6 class="step-role" style="font-weight: 700 !important; color: #2c3e50 !important; margin-bottom: 15px !important; font-size: 18px !important;">{{ $level['role'] ?? 'Role' }}</h6>
                                
                                @if($level['fund_type'])
                                    <div class="step-badges" style="margin-bottom: 15px !important;">
                                        <span class="badge bg-info" style="font-size: 11px !important; padding: 6px 10px !important; border-radius: 12px !important;">
                                            @if($level['fund_type'] == 1)
                                                Intramural
                                            @elseif($level['fund_type'] == 2)
                                                Extramural
                                            @elseif($level['fund_type'] == 3)
                                                External Source
                                            @endif
                                        </span>
                                    </div>
                                @endif
                                
                                @if($level['is_division_specific'])
                                    <div class="step-badges" style="margin-bottom: 15px !important;">
                                        <span class="badge bg-primary" style="font-size: 11px !important; padding: 6px 10px !important; border-radius: 12px !important;">
                                            <i class="fas fa-building me-1"></i>Division Specific
                                        </span>
                                    </div>
                                @endif
                                
                                <div class="approvers-section" style="flex: 1 !important; max-height: 200px !important; overflow-y: auto !important;">
                                    <h6 class="approvers-title" style="color: #6c757d !important; font-size: 13px !important; font-weight: 600 !important; margin-bottom: 12px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important;">Approvers ({{ count($level['approvers']) }})</h6>
                                    @if(count($level['approvers']) > 0)
                                        @foreach($level['approvers'] as $approver)
                                            <div class="approver-item" style="background: rgba(0,0,0,0.05) !important; border: 1px solid rgba(0,0,0,0.1) !important; border-radius: 8px !important; padding: 12px !important; margin-bottom: 8px !important; display: flex !important; justify-content: space-between !important; align-items: flex-start !important; transition: all 0.2s ease !important;">
                                                <div class="approver-info" style="flex: 1 !important; margin-right: 10px !important;">
                                                    <div class="approver-name" style="font-weight: 600 !important; color: #2c3e50 !important; font-size: 13px !important; margin-bottom: 4px !important;">{{ $approver['name'] }}</div>
                                                    <div class="approver-details" style="color: #6c757d !important; font-size: 11px !important; margin-bottom: 2px !important;">{{ $approver['job_title'] }}</div>
                                                    <div class="approver-email" style="color: #6c757d !important; font-size: 10px !important;">{{ $approver['email'] }}</div>
                                                </div>
                                                <div class="approver-type">
                                                    @if($approver['is_oic'])
                                                        <span class="badge bg-warning" style="font-size: 10px !important; padding: 4px 8px !important; border-radius: 10px !important;">OIC</span>
                                                    @else
                                                        <span class="badge bg-{{ $approver['type'] == 'division_specific' ? 'primary' : 'secondary' }}" style="font-size: 10px !important; padding: 4px 8px !important; border-radius: 10px !important;">
                                                            {{ $approver['type'] == 'division_specific' ? 'Division' : ucfirst($approver['type']) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="no-approvers" style="color: #6c757d !important; font-size: 12px !important; text-align: center !important; padding: 20px !important; font-style: italic !important;">
                                            <i class="fas fa-user-slash me-1"></i>No approvers assigned
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Summary information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info border-0">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="fw-bold text-success">{{ $renumberedLevels->where('is_completed', true)->count() }}</div>
                                <div class="text-muted small">Completed</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fw-bold text-warning">{{ $renumberedLevels->where('is_pending', true)->count() }}</div>
                                <div class="text-muted small">Pending</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fw-bold text-secondary">{{ $renumberedLevels->where('is_future', true)->count() }}</div>
                                <div class="text-muted small">Future</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fw-bold text-primary">{{ $renumberedLevels->count() }}</div>
                                <div class="text-muted small">Total Levels</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
