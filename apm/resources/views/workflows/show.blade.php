@extends('layouts.app')

@section('title', 'Workflow Details')

@section('header', 'Workflow Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.index') }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> Back to Workflows
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-git-branch me-2 text-primary"></i>{{ $workflow->workflow_name }}</h5>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Workflow Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">ID:</div>
                            <div class="col-md-9">
                                <span class="badge bg-primary">{{ $workflow->id }}</span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Name:</div>
                            <div class="col-md-9">{{ $workflow->workflow_name }}</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Description:</div>
                            <div class="col-md-9">{{ $workflow->Description }}</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Status:</div>
                            <div class="col-md-9">
                                @if($workflow->is_active)
                                    <span class="badge bg-success"><i class="bx bx-check-circle me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-danger"><i class="bx bx-x-circle me-1"></i>Inactive</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Created:</div>
                            <div class="col-md-9">
                                <i class="bx bx-calendar me-1"></i>N/A
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Updated:</div>
                            <div class="col-md-9">
                                <i class="bx bx-calendar me-1"></i>N/A
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bx bx-stats me-2"></i>Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Definitions:</span>
                                    <span class="badge bg-info">{{ $workflowDefinitions->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Enabled:</span>
                                    <span class="badge bg-success">{{ $workflowDefinitions->where('is_enabled', 1)->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Disabled:</span>
                                    <span class="badge bg-warning">{{ $workflowDefinitions->where('is_enabled', 0)->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-list-check me-2 text-primary"></i>Workflow Definitions</h5>
                <div>
                    <a href="{{ route('workflows.approvers', $workflow->id) }}"
                        class="btn btn-success btn-sm me-2">
                        <i class="bx bx-user-check me-1"></i> Approvers
                    </a>
                    <a href="{{ route('workflows.assign-staff', $workflow->id) }}"
                        class="btn btn-info btn-sm me-2">
                        <i class="bx bx-user-plus me-1"></i> Assign Staff
                    </a>
                    <a href="{{ route('workflows.add-definition', $workflow->id) }}"
                        class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Definition
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Approval Order</th>
                                <th>Role</th>
                                <th>Fund Type</th>
                                <th>Divisions</th>
                                <th>Allowed Funders</th>
                                <th>Division Specific</th>
                                <th>Print Order</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workflowDefinitions->sortBy('approval_order') as $definition)
                                <tr>
                                    <td>{{ $definition->approval_order }}</td>
                                    <td>{{ $definition->role }}</td>
                                    <td>
                                        @if($definition->fund_type)
                                            @php
                                                $fundType = \App\Models\FundType::find($definition->fund_type);
                                            @endphp
                                            <span class="badge bg-info">{{ $fundType ? $fundType->name : 'N/A' }}</span>
                                        @else
                                            <span class="badge bg-secondary">Not Set</span>
                                        @endif
                                    </td>
                                    <td style="max-width: 200px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                        @php
                                            $definitionDivisions = is_array($definition->divisions) ? $definition->divisions : (is_string($definition->divisions) ? json_decode($definition->divisions, true) : []);
                                            $definitionDivisions = $definitionDivisions ?: [];
                                        @endphp
                                        @if(!empty($definitionDivisions))
                                            @foreach($definitionDivisions as $divId)
                                                @if(isset($divisions[$divId]))
                                                    <span class="badge bg-secondary mb-1" style="display: inline-block; word-wrap: break-word; white-space: normal;">{{ $divisions[$divId]->division_name }}</span>
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-muted">All Divisions</span>
                                        @endif
                                    </td>
                                    <td style="max-width: 200px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                        @php
                                            $definitionFunders = is_array($definition->allowed_funders) ? $definition->allowed_funders : (is_string($definition->allowed_funders) ? json_decode($definition->allowed_funders, true) : []);
                                            $definitionFunders = $definitionFunders ?: [];
                                        @endphp
                                        @if(!empty($definitionFunders))
                                            @foreach($definitionFunders as $funderId)
                                                @php $funderId = (int) $funderId; @endphp
                                                @if(isset($funders[$funderId]))
                                                    <span class="badge bg-primary mb-1" style="display: inline-block; word-wrap: break-word; white-space: normal;">{{ $funders[$funderId]->name }}</span>
                                                @endif
                                            @endforeach
                                        @else
                                            <span class="text-muted">All Funders</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($definition->is_division_specific)
                                            <span class="badge bg-warning">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>{{ $definition->print_order ?? 'N/A' }}</td>
                                    <td>
                                        @if($definition->is_enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('workflows.edit-definition', [$workflow->id, $definition->id]) }}" 
                                               class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Definition">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <form action="{{ route('workflows.copy-definition', [$workflow->id, $definition->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Copy this definition as a new one? Approvers and conditions will be copied.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Copy as new definition">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger delete-definition-btn" 
                                                    data-bs-toggle="tooltip" title="Delete Definition"
                                                    data-definition-id="{{ $definition->id }}"
                                                    data-definition-role="{{ $definition->role }}"
                                                    data-definition-approval-order="{{ $definition->approval_order }}">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-list-x fs-1"></i>
                                            <p class="mt-2">No workflow definitions found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Definition Confirmation Modal -->
<div class="modal fade" id="deleteDefinitionModal" tabindex="-1" aria-labelledby="deleteDefinitionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDefinitionModalLabel">
                    <i class="bx bx-trash me-2"></i>Delete Workflow Definition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bx bx-error-circle me-2"></i>
                    <strong>Warning!</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete the workflow definition <strong id="definition-role-name"></strong>?</p>
                <p class="text-muted mb-3">Approvers and approval conditions linked to this definition can be reassigned to another definition, or removed with it.</p>
                <form id="delete-definition-form" method="POST" class="d-inline" action="">
                    @csrf
                    @method('DELETE')
                    <div id="delete-map-to-wrapper" class="mb-0">
                        <label for="delete-map-to-definition" class="form-label fw-semibold">Map existing data (approvers, conditions) to:</label>
                        <select name="map_to_definition_id" id="delete-map-to-definition" class="form-select">
                            <option value="">— Do not reassign (remove with definition) —</option>
                            <!-- Options filled by JS when modal opens -->
                        </select>
                        <small class="text-muted d-block mt-1">By default, data is mapped to the next level above (order +1) if it exists, otherwise to the previous level (order −1). Change it to reassign elsewhere, or choose "Do not reassign" to remove approvers and conditions with this definition.</small>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-danger" id="confirm-delete-btn">
                    <i class="bx bx-trash me-1"></i>Delete Definition
                </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.workflowDefinitionsList = @json($workflowDefinitions->sortBy('approval_order')->map(function($d) { return ['id' => $d->id, 'role' => $d->role, 'approval_order' => $d->approval_order]; })->values());
</script>
<script>
    $(document).ready(function() {
        console.log('Workflow show page loaded');
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle workflow definition delete buttons
        $(document).on('click', '.delete-definition-btn', function(e) {
            e.preventDefault();
            console.log('Delete definition button clicked');
            
            var definitionId = parseInt($(this).data('definition-id'), 10);
            var definitionRole = $(this).data('definition-role');
            var currentOrder = parseInt($(this).data('definition-approval-order'), 10) || 0;
            var allDefs = window.workflowDefinitionsList || [];
            var otherDefs = allDefs.filter(function(d) { return d.id !== definitionId; }).sort(function(a, b) { return a.approval_order - b.approval_order; });
            
            // Check if modal exists
            if ($('#deleteDefinitionModal').length === 0) {
                console.error('Delete modal not found!');
                return;
            }
            
            // Update modal content
            $('#definition-role-name').text('"' + definitionRole + '"');
            var actionUrl = '{{ route("workflows.delete-definition", [$workflow->id, ":id"]) }}'.replace(':id', definitionId);
            $('#delete-definition-form').attr('action', actionUrl);
            
            // Default: current level +1 (next higher) if exists, else current level -1 (previous lower)
            var nextHigher = otherDefs.find(function(d) { return d.approval_order === currentOrder + 1; });
            var prevLower = otherDefs.find(function(d) { return d.approval_order === currentOrder - 1; });
            var defaultMapTo = (nextHigher ? nextHigher.id : (prevLower ? prevLower.id : (otherDefs[0] ? otherDefs[0].id : '')));
            
            // Populate "Map existing data to" dropdown (other definitions only, sorted by order)
            var $select = $('#delete-map-to-definition');
            $select.find('option:not(:first)').remove();
            otherDefs.forEach(function(d) {
                var label = 'Order ' + d.approval_order + ': ' + d.role;
                var opt = $('<option></option>').val(d.id).text(label);
                $select.append(opt);
            });
            if (otherDefs.length === 0) {
                $select.prop('disabled', true);
            } else {
                $select.prop('disabled', false);
                $select.val(defaultMapTo ? String(defaultMapTo) : '');
            }
            
            // Initialize and show modal
            var modal = new bootstrap.Modal(document.getElementById('deleteDefinitionModal'));
            modal.show();
        });
        
        // Handle form submission in the modal
        $('#delete-definition-form').on('submit', function(e) {
            console.log('Delete form submitted');
            var actionUrl = $(this).attr('action');
            console.log('Submitting to:', actionUrl);
            
            // Validate action URL
            if (!actionUrl || actionUrl === '') {
                e.preventDefault();
                alert('Error: No action URL set for delete form');
                console.error('No action URL set for delete form');
                return false;
            }
            
            // Show loading state
            $('#confirm-delete-btn').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Deleting...');
            
            // Let the form submit normally
            return true;
        });
        
        // Handle delete button click in modal
        $('#confirm-delete-btn').on('click', function(e) {
            console.log('Confirm delete button clicked');
            var form = $(this).closest('form');
            var actionUrl = form.attr('action');
            console.log('Form action URL:', actionUrl);
            
            if (!actionUrl || actionUrl === '') {
                e.preventDefault();
                alert('Error: Cannot delete - no action URL set');
                return false;
            }
        });
    });
</script>
@endpush
@endsection
