@extends('layouts.app')

@section('title', 'Edit Workflow Definition')

@section('header', 'Edit Workflow Definition')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-info">
        <i class="bx bx-show"></i> View Workflow
    </a>
    <a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-list-ul"></i> All Workflows
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Edit Workflow Definition</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <form action="{{ route('workflows.update-definition', [$workflow->id, $definition->id]) }}" method="POST" id="editDefinitionForm">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-edit me-2"></i>Definition Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role" class="form-label">
                                        <i class="bx bx-user me-1"></i>Role
                                    </label>
                                    <input type="text" class="form-control @error('role') is-invalid @enderror"
                                        id="role" name="role"
                                        value="{{ old('role', $definition->role) }}" 
                                        placeholder="Enter role name" required>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="approval_order" class="form-label">
                                        <i class="bx bx-sort me-1"></i>Approval Order
                                    </label>
                                    <input type="number" class="form-control @error('approval_order') is-invalid @enderror" 
                                        id="approval_order" name="approval_order"
                                        value="{{ old('approval_order', $definition->approval_order) }}"
                                        placeholder="Enter approval order" min="1" required>
                                    @error('approval_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="fund_type" class="form-label">
                                        <i class="bx bx-money me-1"></i>Fund Type
                                    </label>
                                    <select class="form-select @error('fund_type') is-invalid @enderror" id="fund_type" name="fund_type">
                                        <option value="">Select Fund Type</option>
                                        @foreach($fundTypes as $fundType)
                                            <option value="{{ $fundType->id }}" {{ old('fund_type', $definition->fund_type) == $fundType->id ? 'selected' : '' }}>{{ $fundType->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('fund_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="category" class="form-label">
                                        <i class="bx bx-category me-1"></i>Category
                                    </label>
                                    <input type="text" class="form-control @error('category') is-invalid @enderror" 
                                        id="category" name="category"
                                        value="{{ old('category', $definition->category) }}"
                                        placeholder="Enter category" maxlength="20">
                                    @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Category for routing (e.g., program, support)</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="memo_print_section" class="form-label">
                                        <i class="bx bx-file me-1"></i>Memo Print Section
                                    </label>
                                    <select class="form-select @error('memo_print_section') is-invalid @enderror" id="memo_print_section" name="memo_print_section">
                                        <option value="through" {{ old('memo_print_section', $definition->memo_print_section ?? 'through') == 'through' ? 'selected' : '' }}>Through</option>
                                        <option value="to" {{ old('memo_print_section', $definition->memo_print_section) == 'to' ? 'selected' : '' }}>To</option>
                                        <option value="from" {{ old('memo_print_section', $definition->memo_print_section) == 'from' ? 'selected' : '' }}>From</option>
                                        <option value="others" {{ old('memo_print_section', $definition->memo_print_section) == 'others' ? 'selected' : '' }}>Others</option>
                                    </select>
                                    @error('memo_print_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="print_order" class="form-label">
                                        <i class="bx bx-printer me-1"></i>Print Order
                                    </label>
                                    <input type="number" class="form-control @error('print_order') is-invalid @enderror" 
                                        id="print_order" name="print_order"
                                        value="{{ old('print_order', $definition->print_order) }}"
                                        placeholder="Enter print order" min="1">
                                    @error('print_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Order in which this step appears in memo printing</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="divisions" class="form-label">
                                        <i class="bx bx-buildings me-1"></i>Divisions
                                    </label>
                                    @php
                                        $selectedDivisions = is_array($definition->divisions) ? $definition->divisions : (is_string($definition->divisions) ? json_decode($definition->divisions, true) : []);
                                        $selectedDivisions = $selectedDivisions ?: [];
                                    @endphp
                                    <select class="form-select select2 @error('divisions') is-invalid @enderror" id="divisions" name="divisions[]" multiple>
                                        @foreach($divisions as $division)
                                            <option value="{{ $division->id }}" {{ in_array($division->id, old('divisions', $selectedDivisions)) ? 'selected' : '' }}>{{ $division->division_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('divisions')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Select one or more divisions for this workflow definition</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="allowed_funders" class="form-label">
                                        <i class="bx bx-money me-1"></i>Allowed Funders
                                    </label>
                                    @php
                                        $selectedFunders = is_array($definition->allowed_funders) ? $definition->allowed_funders : (is_string($definition->allowed_funders) ? json_decode($definition->allowed_funders, true) : []);
                                        $selectedFunders = $selectedFunders ?: [];
                                    @endphp
                                    <select class="form-select select2 @error('allowed_funders') is-invalid @enderror" id="allowed_funders" name="allowed_funders[]" multiple>
                                        @foreach($funders as $funder)
                                            <option value="{{ $funder->id }}" {{ in_array($funder->id, old('allowed_funders', $selectedFunders)) ? 'selected' : '' }}>{{ $funder->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('allowed_funders')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Select funders allowed for this workflow definition</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="division_reference_column" class="form-label">
                                        <i class="bx bx-link me-1"></i>Division Reference Column
                                    </label>
                                    <select class="form-select @error('division_reference_column') is-invalid @enderror" id="division_reference_column" name="division_reference_column">
                                        <option value="">Select Reference Column</option>
                                        <option value="division_head" {{ old('division_reference_column', $definition->division_reference_column) == 'division_head' ? 'selected' : '' }}>Division Head</option>
                                        <option value="finance_officer" {{ old('division_reference_column', $definition->division_reference_column) == 'finance_officer' ? 'selected' : '' }}>Finance Officer</option>
                                        <option value="director_id" {{ old('division_reference_column', $definition->division_reference_column) == 'director_id' ? 'selected' : '' }}>Director</option>
                                        <option value="focal_person" {{ old('division_reference_column', $definition->division_reference_column) == 'focal_person' ? 'selected' : '' }}>Focal Person</option>
                                        <option value="admin_assistant" {{ old('division_reference_column', $definition->division_reference_column) == 'admin_assistant' ? 'selected' : '' }}>Admin Assistant</option>
                                    </select>
                                    @error('division_reference_column')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Column in divisions table to reference for division-specific approvers</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" 
                                            value="1" {{ old('is_enabled', $definition->is_enabled) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_enabled">
                                            <i class="bx bx-power-off me-1"></i>Enabled
                                        </label>
                                    </div>
                                    <small class="text-muted">Enable or disable this definition</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_division_specific" name="is_division_specific" 
                                            value="1" {{ old('is_division_specific', $definition->is_division_specific) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_division_specific">
                                            <i class="bx bx-buildings me-1"></i>Division Specific
                                        </label>
                                    </div>
                                    <small class="text-muted">Check if this definition is specific to a division</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="triggers_category_check" name="triggers_category_check" 
                                            value="1" {{ old('triggers_category_check', $definition->triggers_category_check) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="triggers_category_check">
                                            <i class="bx bx-check-circle me-1"></i>Triggers Category Check
                                        </label>
                                    </div>
                                    <small class="text-muted">Check if this definition triggers category-based routing</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Definition Info</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">ID:</div>
                                <div class="col-7">
                                    <span class="badge bg-primary">{{ $definition->id }}</span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Workflow:</div>
                                <div class="col-7">
                                    <small>{{ $workflow->workflow_name }}</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Status:</div>
                                <div class="col-7">
                                    @if($definition->is_enabled)
                                        <span class="badge bg-success">Enabled</span>
                                    @else
                                        <span class="badge bg-danger">Disabled</span>
                                    @endif
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Fund Type:</div>
                                <div class="col-7">
                                    <span class="badge bg-info">{{ ucfirst($definition->fund_type ?? 'Not Set') }}</span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Division Specific:</div>
                                <div class="col-7">
                                    @if($definition->is_division_specific)
                                        <span class="badge bg-warning">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button type="button" class="btn btn-outline-warning me-auto" id="syncApprovalOrderBtn" title="Update existing approval trails to use the new approval order">
                    <i class="bx bx-transfer me-1"></i>Sync approval order with existing trails
                </button>
                <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary me-2">
                    <i class="bx bx-x me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i>Update Definition
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Sync approval order modal: enter previous/new order, then sync with progress --}}
<div class="modal fade" id="syncApprovalOrderModal" tabindex="-1" aria-labelledby="syncApprovalOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="syncApprovalOrderModalLabel">
                    <i class="bx bx-transfer me-2"></i>Sync approval order with existing data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="syncModalCloseBtn"></button>
            </div>
            <div class="modal-body">
                <div id="syncApprovalOrderForm">
                    <p class="text-muted small mb-3">Records in <strong>activity_approval_trails</strong> and <strong>approval_trails</strong> with <code>forward_workflow_id = {{ $workflow->id }}</code> will be updated from the previous order to the new order.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="syncPreviousOrder" class="form-label">Previous approval order</label>
                            <input type="number" class="form-control" id="syncPreviousOrder" name="sync_previous_order" min="1" placeholder="e.g. 2">
                        </div>
                        <div class="col-md-6">
                            <label for="syncNewOrderInput" class="form-label">New approval order</label>
                            <input type="number" class="form-control" id="syncNewOrderInput" name="sync_new_order" min="1" placeholder="e.g. 4">
                        </div>
                    </div>
                    <div id="syncOrderValidation" class="text-danger small mt-1 d-none"></div>
                </div>
                <div id="syncApprovalOrderProgress" class="d-none mt-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span id="syncProgressLabel">Syncing...</span>
                        <span id="syncProgressPct">0%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="syncProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div id="syncApprovalOrderResult" class="d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="syncModalCancelBtn">Cancel</button>
                <button type="button" class="btn btn-warning" id="syncApprovalOrderConfirmBtn">
                    <i class="bx bx-transfer me-1"></i>Sync
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container {
        width: 100% !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#divisions, #allowed_funders').select2({
            placeholder: 'Select options',
            allowClear: true,
            width: '100%'
        });

        var syncModal = new bootstrap.Modal(document.getElementById('syncApprovalOrderModal'));
        var syncInProgress = false;

        function resetSyncModal() {
            $('#syncApprovalOrderForm').removeClass('d-none');
            $('#syncPreviousOrder, #syncNewOrderInput').val('').prop('disabled', false);
            $('#syncOrderValidation').addClass('d-none').text('');
            $('#syncApprovalOrderProgress').addClass('d-none');
            $('#syncProgressBar').css('width', '0%').attr('aria-valuenow', 0);
            $('#syncProgressPct').text('0%');
            $('#syncProgressLabel').text('Syncing...');
            $('#syncApprovalOrderResult').addClass('d-none').empty();
            $('#syncApprovalOrderConfirmBtn').prop('disabled', false).html('<i class="bx bx-transfer me-1"></i>Sync');
            $('#syncModalCloseBtn, #syncModalCancelBtn').prop('disabled', false);
            syncInProgress = false;
        }

        $('#syncApprovalOrderModal').on('show.bs.modal', function() {
            resetSyncModal();
        });

        $('#syncApprovalOrderBtn').on('click', function() {
            syncModal.show();
        });

        function setProgress(pct, label) {
            pct = Math.min(100, Math.max(0, pct));
            $('#syncProgressBar').css('width', pct + '%').attr('aria-valuenow', pct);
            $('#syncProgressPct').text(pct + '%');
            if (label) $('#syncProgressLabel').text(label);
        }

        $('#syncApprovalOrderConfirmBtn').on('click', function() {
            var previousOrder = ($('#syncPreviousOrder').val() || '').trim();
            var newOrder = ($('#syncNewOrderInput').val() || '').trim();
            var $validation = $('#syncOrderValidation');

            if (!previousOrder || !newOrder) {
                $validation.removeClass('d-none').text('Please enter both previous and new approval order.');
                $('#syncPreviousOrder, #syncNewOrderInput').addClass('is-invalid');
                return;
            }
            var prev = parseInt(previousOrder, 10);
            var n = parseInt(newOrder, 10);
            if (isNaN(prev) || isNaN(n) || prev < 1 || n < 1) {
                $validation.removeClass('d-none').text('Both values must be positive integers.');
                $('#syncPreviousOrder, #syncNewOrderInput').addClass('is-invalid');
                return;
            }
            if (prev === n) {
                $validation.removeClass('d-none').text('Previous and new approval order must be different.');
                $('#syncPreviousOrder, #syncNewOrderInput').addClass('is-invalid');
                return;
            }

            $('#syncPreviousOrder, #syncNewOrderInput').removeClass('is-invalid');
            $validation.addClass('d-none');

            var btn = $('#syncApprovalOrderConfirmBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Syncing...');
            $('#syncModalCloseBtn, #syncModalCancelBtn').prop('disabled', true);
            syncInProgress = true;

            $('#syncApprovalOrderForm').addClass('d-none');
            $('#syncApprovalOrderProgress').removeClass('d-none');
            $('#syncApprovalOrderResult').addClass('d-none').empty();
            setProgress(10, 'Starting sync...');

            // Animate progress while request is in flight (async)
            var progressInterval = setInterval(function() {
                var w = parseInt($('#syncProgressBar').attr('aria-valuenow') || 0, 10);
                if (w < 70) setProgress(w + 8, 'Syncing approval trails...');
            }, 200);

            $.ajax({
                url: '{{ route("workflows.sync-approval-order", [$workflow->id, $definition->id]) }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    old_approval_order: previousOrder,
                    new_approval_order: newOrder
                },
                success: function(res) {
                    clearInterval(progressInterval);
                    setProgress(100, 'Complete');
                    $('#syncProgressBar').removeClass('progress-bar-animated');
                    syncInProgress = false;
                    $('#syncModalCloseBtn, #syncModalCancelBtn').prop('disabled', false);

                    var $result = $('#syncApprovalOrderResult').removeClass('d-none');
                    $result.html('<div class="alert alert-success mb-0"><i class="bx bx-check-circle me-2"></i>' + (res.message || 'Sync completed successfully.') + '</div>');
                    if (typeof show_notification === 'function') {
                        show_notification(res.message || 'Sync completed.', 'success');
                    }
                    btn.prop('disabled', true).html('<i class="bx bx-check me-1"></i>Done');
                },
                error: function(xhr) {
                    clearInterval(progressInterval);
                    setProgress(0, 'Error');
                    $('#syncProgressBar').removeClass('progress-bar-animated').addClass('bg-danger');
                    syncInProgress = false;
                    $('#syncModalCloseBtn, #syncModalCancelBtn').prop('disabled', false);
                    btn.prop('disabled', false).html('<i class="bx bx-transfer me-1"></i>Sync');

                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed. Please try again.';
                    var $result = $('#syncApprovalOrderResult').removeClass('d-none');
                    $result.html('<div class="alert alert-danger mb-0"><i class="bx bx-error-circle me-2"></i>' + msg + '</div>');
                    if (typeof show_notification === 'function') {
                        show_notification(msg, 'error');
                    }
                }
            });
        });
    });
</script>
@endpush
