<!-- Service Request Modal -->
<div class="modal fade" id="createServiceRequestModal" tabindex="-1" aria-labelledby="createServiceRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #119A48; color: white; border-radius: 0.75rem 0.75rem 0 0;">
                <h5 class="modal-title fw-bold text-white" id="createServiceRequestModalLabel">
                    <i class="fas fa-tools me-2"></i>Create Service Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="serviceRequestForm">
                    @csrf
                    <input type="hidden" id="sourceType" name="sourceType">
                    <input type="hidden" id="sourceId" name="sourceId">
                    
                    <!-- Source Information Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="fas fa-info-circle me-2"></i>Source Information
                            </h6>
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-tag me-1 text-success"></i>Source ID
                                            </label>
                                            <p class="form-control-plaintext" id="sourceIdDisplay">-</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-building me-1 text-success"></i>Division
                                            </label>
                                            <p class="form-control-plaintext" id="sourceDivision">-</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-heading me-1 text-success"></i>Title
                                            </label>
                                            <p class="form-control-plaintext" id="sourceTitle">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Request Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="fas fa-edit me-2"></i>Service Request Details
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label for="title" class="form-label fw-semibold">
                                <i class="fas fa-heading me-1 text-success"></i>Service Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="service_type" class="form-label fw-semibold">
                                <i class="fas fa-cogs me-1 text-success"></i>Service Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="it">IT</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="procurement">Procurement</option>
                                <option value="travel">Travel</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label fw-semibold">
                                <i class="fas fa-exclamation-triangle me-1 text-success"></i>Priority <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="">Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="required_by_date" class="form-label fw-semibold">
                                <i class="fas fa-calendar me-1 text-success"></i>Required By Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="required_by_date" name="required_by_date" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">
                                <i class="fas fa-align-left me-1 text-success"></i>Description <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control summernote" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="justification" class="form-label fw-semibold">
                                <i class="fas fa-clipboard-list me-1 text-success"></i>Justification <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control summernote" id="justification" name="justification" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="location" class="form-label fw-semibold">
                                <i class="fas fa-map-marker-alt me-1 text-success"></i>Location
                            </label>
                            <input type="text" class="form-control" id="location" name="location">
                        </div>
                    </div>

                    <!-- Original Budget Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Original Budget Breakdown
                            </h6>
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div id="originalBudgetDisplay">
                                        <p class="text-muted">Loading original budget...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Budget Breakdown Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-success fw-bold mb-3">
                                <i class="fas fa-calculator me-2"></i>New Budget Breakdown
                            </h6>
                        </div>
                    </div>

                    <!-- Internal Participants Cost Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="fas fa-users me-2"></i>Individual Costs (Internal Participants)
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="addInternalParticipant">
                                                <i class="fas fa-plus me-1"></i>Add Participant
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="internalParticipantsContainer">
                                        <!-- Internal participants will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- External Participants Cost Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="fas fa-user-plus me-2"></i>Individual Costs (External Participants)
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="addExternalParticipant">
                                                <i class="fas fa-plus me-1"></i>Add Participant
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="externalParticipantsContainer">
                                        <!-- External participants will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Costs Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="fas fa-receipt me-2"></i>Other Costs
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="addOtherCost">
                                                <i class="fas fa-plus me-1"></i>Add Cost Item
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="otherCostsContainer">
                                        <!-- Other costs will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Budget Summary Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fas fa-calculator me-2"></i>Budget Summary
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Original Budget</label>
                                            <p class="form-control-plaintext fw-bold" id="originalBudgetTotal">$0.00</p>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">New Budget</label>
                                            <p class="form-control-plaintext fw-bold" id="newBudgetTotal">$0.00</p>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Budget Difference</label>
                                            <p class="form-control-plaintext fw-bold" id="budgetDifference">$0.00</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="submitServiceRequest">
                    <i class="fas fa-paper-plane me-1"></i>Submit Service Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden inputs for budget data -->
<input type="hidden" id="originalTotalBudget" name="original_total_budget">
<input type="hidden" id="newTotalBudget" name="new_total_budget">

<script>
$(document).ready(function() {
    let internalParticipants = [];
    let externalParticipants = [];
    let otherCosts = [];
    let costItems = [];
    let originalBudget = 0;
    let newBudget = 0;

    // Initialize Summernote
    if ($('.summernote').length > 0) {
        $('.summernote').summernote({
            height: 150,
            fontNames: ['Arial'],
            fontNamesIgnoreCheck: ['Arial'],
            defaultFontName: 'Arial',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }

    // Load cost items
    loadCostItems();

    // Handle button clicks to load source data
    $(document).on('click', '[data-bs-target="#createServiceRequestModal"]', function() {
        const sourceType = $(this).data('source-type');
        const sourceId = $(this).data('source-id');
        
        $('#sourceType').val(sourceType);
        $('#sourceId').val(sourceId);
        
        loadSourceData(sourceType, sourceId);
    });

    // Load source data
    function loadSourceData(sourceType, sourceId) {
        $.ajax({
            url: '{{ route("service-requests.get-source-data") }}',
            method: 'POST',
            data: {
                sourceType: sourceType,
                sourceId: sourceId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Populate source information
                    $('#sourceIdDisplay').text(response.sourceData.id);
                    $('#sourceDivision').text(response.sourceData.division_name);
                    $('#sourceTitle').text(response.sourceData.title);
                    
                    // Set form values
                    $('#title').val(response.sourceData.title);
                    $('#description').summernote('code', response.sourceData.description);
                    $('#location').val(response.sourceData.location);
                    
                    // Set budget data
                    originalBudget = parseFloat(response.originalTotalBudget) || 0;
                    $('#originalTotalBudget').val(originalBudget);
                    $('#originalBudgetTotal').text('$' + originalBudget.toFixed(2));
                    
                    // Display original budget breakdown
                    displayOriginalBudget(response.budgetBreakdown);
                    
                    // Load internal participants
                    internalParticipants = response.internalParticipants || [];
                    displayInternalParticipants();
                    
                    // Initialize external participants and other costs
                    externalParticipants = [];
                    otherCosts = [];
                    displayExternalParticipants();
                    displayOtherCosts();
                    
                    // Calculate totals
                    calculateTotals();
                } else {
                    showNotification('Error loading source data: ' + response.message, 'error');
                }
            },
            error: function(xhr) {
                showNotification('Error loading source data', 'error');
            }
        });
    }

    // Load cost items
    function loadCostItems() {
        $.ajax({
            url: '{{ route("service-requests.cost-items") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    costItems = response.costItems;
                }
            },
            error: function(xhr) {
                console.error('Error loading cost items');
            }
        });
    }

    // Display original budget breakdown
    function displayOriginalBudget(budgetBreakdown) {
        let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="table-light"><tr><th>Fund Code</th><th>Cost Item</th><th>Unit Cost</th><th>Units</th><th>Days</th><th>Total</th></tr></thead>';
        html += '<tbody>';
        
        if (budgetBreakdown && typeof budgetBreakdown === 'object') {
            Object.keys(budgetBreakdown).forEach(fundCode => {
                if (fundCode !== 'grand_total' && Array.isArray(budgetBreakdown[fundCode])) {
                    budgetBreakdown[fundCode].forEach(item => {
                        html += '<tr>';
                        html += '<td>' + fundCode + '</td>';
                        html += '<td>' + (item.cost || item.description || 'N/A') + '</td>';
                        html += '<td>$' + (parseFloat(item.unit_cost) || 0).toFixed(2) + '</td>';
                        html += '<td>' + (item.units || item.quantity || 0) + '</td>';
                        html += '<td>' + (item.days || 0) + '</td>';
                        html += '<td>$' + (parseFloat(item.total) || (parseFloat(item.unit_cost) || 0) * (parseFloat(item.units || item.quantity) || 0)).toFixed(2) + '</td>';
                        html += '</tr>';
                    });
                }
            });
            
            if (budgetBreakdown.grand_total) {
                html += '<tr class="table-success fw-bold">';
                html += '<td colspan="5">Grand Total</td>';
                html += '<td>$' + parseFloat(budgetBreakdown.grand_total).toFixed(2) + '</td>';
                html += '</tr>';
            }
        }
        
        html += '</tbody></table></div>';
        $('#originalBudgetDisplay').html(html);
    }

    // Display internal participants
    function displayInternalParticipants() {
        let html = '';
        
        if (internalParticipants.length === 0) {
            html = '<p class="text-muted">No internal participants available</p>';
        } else {
            internalParticipants.forEach((participant, index) => {
                html += '<div class="row mb-3 participant-row" data-index="' + index + '">';
                html += '<div class="col-md-3">';
                html += '<label class="form-label fw-semibold">Name</label>';
                html += '<select class="form-select participant-select" name="internal_participants_cost[' + index + '][participant_id]">';
                html += '<option value="' + participant.id + '">' + participant.name + '</option>';
                html += '</select>';
                html += '</div>';
                html += '<div class="col-md-2">';
                html += '<label class="form-label fw-semibold">Accommodation</label>';
                html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][accommodation]" step="0.01" min="0">';
                html += '</div>';
                html += '<div class="col-md-2">';
                html += '<label class="form-label fw-semibold">Transport</label>';
                html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][transport]" step="0.01" min="0">';
                html += '</div>';
                html += '<div class="col-md-2">';
                html += '<label class="form-label fw-semibold">Meals</label>';
                html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][meals]" step="0.01" min="0">';
                html += '</div>';
                html += '<div class="col-md-2">';
                html += '<label class="form-label fw-semibold">Total</label>';
                html += '<input type="number" class="form-control participant-total" readonly step="0.01">';
                html += '</div>';
                html += '<div class="col-md-1">';
                html += '<label class="form-label">&nbsp;</label>';
                html += '<button type="button" class="btn btn-sm btn-outline-danger remove-participant">';
                html += '<i class="fas fa-trash"></i>';
                html += '</button>';
                html += '</div>';
                html += '</div>';
            });
        }
        
        $('#internalParticipantsContainer').html(html);
        attachParticipantEventListeners();
    }

    // Display external participants
    function displayExternalParticipants() {
        let html = '';
        
        externalParticipants.forEach((participant, index) => {
            html += '<div class="row mb-3 participant-row" data-index="' + index + '">';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Name</label>';
            html += '<input type="text" class="form-control" name="external_participants_cost[' + index + '][name]" placeholder="Participant Name">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Email</label>';
            html += '<input type="email" class="form-control" name="external_participants_cost[' + index + '][email]" placeholder="Email">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Accommodation</label>';
            html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][accommodation]" step="0.01" min="0">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Transport</label>';
            html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][transport]" step="0.01" min="0">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Meals</label>';
            html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][meals]" step="0.01" min="0">';
            html += '</div>';
            html += '<div class="col-md-1">';
            html += '<label class="form-label fw-semibold">Total</label>';
            html += '<input type="number" class="form-control participant-total" readonly step="0.01">';
            html += '</div>';
            html += '<div class="col-md-1">';
            html += '<label class="form-label">&nbsp;</label>';
            html += '<button type="button" class="btn btn-sm btn-outline-danger remove-participant">';
            html += '<i class="fas fa-trash"></i>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
        });
        
        $('#externalParticipantsContainer').html(html);
        attachParticipantEventListeners();
    }

    // Display other costs
    function displayOtherCosts() {
        let html = '';
        
        otherCosts.forEach((cost, index) => {
            html += '<div class="row mb-3 cost-row" data-index="' + index + '">';
            html += '<div class="col-md-3">';
            html += '<label class="form-label fw-semibold">Cost Type</label>';
            html += '<select class="form-select" name="other_costs[' + index + '][cost_type]">';
            html += '<option value="">Select Cost Type</option>';
            costItems.forEach(item => {
                html += '<option value="' + item.id + '">' + item.name + '</option>';
            });
            html += '</select>';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">Unit Cost</label>';
            html += '<input type="number" class="form-control cost-input" name="other_costs[' + index + '][unit_cost]" step="0.01" min="0">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<label class="form-label fw-semibold">No of Days</label>';
            html += '<input type="number" class="form-control cost-input" name="other_costs[' + index + '][days]" min="1">';
            html += '</div>';
            html += '<div class="col-md-3">';
            html += '<label class="form-label fw-semibold">Description</label>';
            html += '<input type="text" class="form-control" name="other_costs[' + index + '][description]" placeholder="Description">';
            html += '</div>';
            html += '<div class="col-md-1">';
            html += '<label class="form-label fw-semibold">Total</label>';
            html += '<input type="number" class="form-control cost-total" readonly step="0.01">';
            html += '</div>';
            html += '<div class="col-md-1">';
            html += '<label class="form-label">&nbsp;</label>';
            html += '<button type="button" class="btn btn-sm btn-outline-danger remove-cost">';
            html += '<i class="fas fa-trash"></i>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
        });
        
        $('#otherCostsContainer').html(html);
        attachCostEventListeners();
    }

    // Attach event listeners for participants
    function attachParticipantEventListeners() {
        $('.cost-input').off('input').on('input', function() {
            calculateParticipantTotal($(this).closest('.participant-row'));
            calculateTotals();
        });
        
        $('.remove-participant').off('click').on('click', function() {
            $(this).closest('.participant-row').remove();
            calculateTotals();
        });
    }

    // Attach event listeners for costs
    function attachCostEventListeners() {
        $('.cost-input').off('input').on('input', function() {
            calculateCostTotal($(this).closest('.cost-row'));
            calculateTotals();
        });
        
        $('.remove-cost').off('click').on('click', function() {
            $(this).closest('.cost-row').remove();
            calculateTotals();
        });
    }

    // Calculate participant total
    function calculateParticipantTotal(row) {
        let total = 0;
        row.find('.cost-input').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        row.find('.participant-total').val(total.toFixed(2));
    }

    // Calculate cost total
    function calculateCostTotal(row) {
        let unitCost = parseFloat(row.find('input[name*="[unit_cost]"]').val()) || 0;
        let days = parseFloat(row.find('input[name*="[days]"]').val()) || 1;
        let total = unitCost * days;
        row.find('.cost-total').val(total.toFixed(2));
    }

    // Calculate all totals
    function calculateTotals() {
        let total = 0;
        
        // Internal participants total
        $('.participant-total').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        
        // External participants total
        $('.participant-total').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        
        // Other costs total
        $('.cost-total').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        
        newBudget = total;
        $('#newTotalBudget').val(newBudget);
        $('#newBudgetTotal').text('$' + newBudget.toFixed(2));
        
        // Calculate difference
        let difference = newBudget - originalBudget;
        $('#budgetDifference').text('$' + difference.toFixed(2));
        
        // Color code the difference
        let differenceElement = $('#budgetDifference');
        if (difference <= 0) {
            differenceElement.removeClass('text-danger').addClass('text-success');
        } else {
            differenceElement.removeClass('text-success').addClass('text-danger');
        }
    }

    // Add internal participant
    $('#addInternalParticipant').on('click', function() {
        if (internalParticipants.length === 0) {
            showNotification('No internal participants available from source', 'warning');
            return;
        }
        
        let index = $('.participant-row').length;
        let participant = internalParticipants[0]; // Use first available participant
        
        let html = '<div class="row mb-3 participant-row" data-index="' + index + '">';
        html += '<div class="col-md-3">';
        html += '<label class="form-label fw-semibold">Name</label>';
        html += '<select class="form-select participant-select" name="internal_participants_cost[' + index + '][participant_id]">';
        internalParticipants.forEach(p => {
            html += '<option value="' + p.id + '">' + p.name + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Accommodation</label>';
        html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][accommodation]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Transport</label>';
        html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][transport]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Meals</label>';
        html += '<input type="number" class="form-control cost-input" name="internal_participants_cost[' + index + '][meals]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Total</label>';
        html += '<input type="number" class="form-control participant-total" readonly step="0.01">';
        html += '</div>';
        html += '<div class="col-md-1">';
        html += '<label class="form-label">&nbsp;</label>';
        html += '<button type="button" class="btn btn-sm btn-outline-danger remove-participant">';
        html += '<i class="fas fa-trash"></i>';
        html += '</button>';
        html += '</div>';
        html += '</div>';
        
        $('#internalParticipantsContainer').append(html);
        attachParticipantEventListeners();
    });

    // Add external participant
    $('#addExternalParticipant').on('click', function() {
        let index = $('.participant-row').length;
        
        let html = '<div class="row mb-3 participant-row" data-index="' + index + '">';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Name</label>';
        html += '<input type="text" class="form-control" name="external_participants_cost[' + index + '][name]" placeholder="Participant Name">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Email</label>';
        html += '<input type="email" class="form-control" name="external_participants_cost[' + index + '][email]" placeholder="Email">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Accommodation</label>';
        html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][accommodation]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Transport</label>';
        html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][transport]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Meals</label>';
        html += '<input type="number" class="form-control cost-input" name="external_participants_cost[' + index + '][meals]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-1">';
        html += '<label class="form-label fw-semibold">Total</label>';
        html += '<input type="number" class="form-control participant-total" readonly step="0.01">';
        html += '</div>';
        html += '<div class="col-md-1">';
        html += '<label class="form-label">&nbsp;</label>';
        html += '<button type="button" class="btn btn-sm btn-outline-danger remove-participant">';
        html += '<i class="fas fa-trash"></i>';
        html += '</button>';
        html += '</div>';
        html += '</div>';
        
        $('#externalParticipantsContainer').append(html);
        attachParticipantEventListeners();
    });

    // Add other cost
    $('#addOtherCost').on('click', function() {
        let index = $('.cost-row').length;
        
        let html = '<div class="row mb-3 cost-row" data-index="' + index + '">';
        html += '<div class="col-md-3">';
        html += '<label class="form-label fw-semibold">Cost Type</label>';
        html += '<select class="form-select" name="other_costs[' + index + '][cost_type]">';
        html += '<option value="">Select Cost Type</option>';
        costItems.forEach(item => {
            html += '<option value="' + item.id + '">' + item.name + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">Unit Cost</label>';
        html += '<input type="number" class="form-control cost-input" name="other_costs[' + index + '][unit_cost]" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="col-md-2">';
        html += '<label class="form-label fw-semibold">No of Days</label>';
        html += '<input type="number" class="form-control cost-input" name="other_costs[' + index + '][days]" min="1">';
        html += '</div>';
        html += '<div class="col-md-3">';
        html += '<label class="form-label fw-semibold">Description</label>';
        html += '<input type="text" class="form-control" name="other_costs[' + index + '][description]" placeholder="Description">';
        html += '</div>';
        html += '<div class="col-md-1">';
        html += '<label class="form-label fw-semibold">Total</label>';
        html += '<input type="number" class="form-control cost-total" readonly step="0.01">';
        html += '</div>';
        html += '<div class="col-md-1">';
        html += '<label class="form-label">&nbsp;</label>';
        html += '<button type="button" class="btn btn-sm btn-outline-danger remove-cost">';
        html += '<i class="fas fa-trash"></i>';
        html += '</button>';
        html += '</div>';
        html += '</div>';
        
        $('#otherCostsContainer').append(html);
        attachCostEventListeners();
    });

    // Submit service request
    $('#submitServiceRequest').on('click', function() {
        let formData = new FormData($('#serviceRequestForm')[0]);
        
        // Add budget data
        formData.append('original_total_budget', originalBudget);
        formData.append('new_total_budget', newBudget);
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Submitting...');
        
        $.ajax({
            url: '{{ route("service-requests.store-from-modal") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    $('#createServiceRequestModal').modal('hide');
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    }
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function(xhr) {
                let message = 'Error creating service request';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showNotification(message, 'error');
            },
            complete: function() {
                $('#submitServiceRequest').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>Submit Service Request');
            }
        });
    });
});
</script>
