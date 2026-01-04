<?php
/**
 * Reusable modal for changing supervisors in PPA, Midterm, and Endterm
 * 
 * @param $ppa - PPA entry object
 * @param $type - 'ppa', 'midterm', or 'endterm'
 * @param $current_supervisor_1 - Current first supervisor ID
 * @param $current_supervisor_2 - Current second supervisor ID (optional)
 */

// Check if user has permission 83 (allow_return_ppa)
$session = $this->session->userdata('user');
$permissions = $session->permissions ?? [];
$has_permission = in_array('83', $permissions);

// Determine current supervisors based on type
$supervisor_1_field = '';
$supervisor_2_field = '';
$modal_id = '';

if ($type === 'ppa') {
    $supervisor_1_field = 'supervisor_id';
    $supervisor_2_field = 'supervisor2_id';
    $modal_id = 'changePpaSupervisorModal';
    // Get supervisors from PPA entry, not from contract
    $current_supervisor_1 = !empty($ppa->supervisor_id) ? (int)$ppa->supervisor_id : null;
    $current_supervisor_2 = !empty($ppa->supervisor2_id) ? (int)$ppa->supervisor2_id : null;
} elseif ($type === 'midterm') {
    $supervisor_1_field = 'midterm_supervisor_1';
    $supervisor_2_field = 'midterm_supervisor_2';
    $modal_id = 'changeMidtermSupervisorModal';
    // Get supervisors from PPA entry (midterm fields), not from contract
    $current_supervisor_1 = !empty($ppa->midterm_supervisor_1) ? (int)$ppa->midterm_supervisor_1 : null;
    $current_supervisor_2 = !empty($ppa->midterm_supervisor_2) ? (int)$ppa->midterm_supervisor_2 : null;
} elseif ($type === 'endterm') {
    $supervisor_1_field = 'endterm_supervisor_1';
    $supervisor_2_field = 'endterm_supervisor_2';
    $modal_id = 'changeEndtermSupervisorModal';
    // Get supervisors from PPA entry (endterm fields), not from contract
    $current_supervisor_1 = !empty($ppa->endterm_supervisor_1) ? (int)$ppa->endterm_supervisor_1 : null;
    $current_supervisor_2 = !empty($ppa->endterm_supervisor_2) ? (int)$ppa->endterm_supervisor_2 : null;
}

// Get active staff for dropdown (exclude Expired and Separated)
// Get latest contract for each staff
$subquery = $this->db->select('MAX(staff_contract_id)', false)
    ->from('staff_contracts')
    ->group_by('staff_id')
    ->get_compiled_select();

$this->db->select('s.staff_id, CONCAT(s.fname, " ", s.lname) AS staff_name', false);
$this->db->from('staff s');
$this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
$this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
$this->db->where_in('sc.status_id', [1, 2]); // Active (1) or Due (2), exclude Expired (3) and Separated (4)
$this->db->group_by('s.staff_id');
$this->db->order_by('s.fname', 'ASC');
$this->db->order_by('s.lname', 'ASC');
$active_staff = $this->db->get()->result();

// Ensure current supervisors are included even if they're expired/separated
$current_supervisor_ids = [];
if (!empty($current_supervisor_1)) $current_supervisor_ids[] = $current_supervisor_1;
if (!empty($current_supervisor_2)) $current_supervisor_ids[] = $current_supervisor_2;
$current_supervisor_ids = array_unique($current_supervisor_ids);

// Get current supervisor names if they're not in active_staff
$active_staff_ids = array_column($active_staff, 'staff_id');
$missing_supervisor_ids = array_diff($current_supervisor_ids, $active_staff_ids);

if (!empty($missing_supervisor_ids)) {
    $this->db->select('s.staff_id, CONCAT(s.fname, " ", s.lname) AS staff_name', false);
    $this->db->from('staff s');
    $this->db->where_in('s.staff_id', $missing_supervisor_ids);
    $missing_supervisors = $this->db->get()->result();
    // Merge with active_staff
    $active_staff = array_merge($active_staff, $missing_supervisors);
}

// Only show if user has permission 83 AND PPA is not approved (check appropriate draft_status based on type)
$show_modal = false;
if ($has_permission) {
    if ($type === 'ppa') {
        $show_modal = isset($ppa->draft_status) && (int)$ppa->draft_status !== 2;
    } elseif ($type === 'midterm') {
        // For midterm, check both PPA draft_status and midterm_draft_status
        $show_modal = isset($ppa->draft_status) && (int)$ppa->draft_status !== 2;
    } elseif ($type === 'endterm') {
        // For endterm, check both PPA draft_status and endterm_draft_status
        $show_modal = $ppa->overall_end_term_status !='Approved';
    }
}
?>

<?php if ($show_modal): ?>
<!-- Change Supervisor Modal -->
<div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" aria-labelledby="<?= $modal_id ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="<?= $modal_id ?>Label">Change <?= ucfirst($type) ?> Supervisors</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="changeSupervisorForm_<?= $type ?>" method="post" onsubmit="return false;">
                <div class="modal-body">
                    <input type="hidden" name="entry_id" id="entry_id_<?= $type ?>" value="<?= htmlspecialchars($ppa->entry_id ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" id="type_<?= $type ?>" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="mb-3">
                        <label for="supervisor_1_<?= $type ?>" class="form-label">First Supervisor <span class="text-danger">*</span></label>
                        <select name="supervisor_1" id="supervisor_1_<?= $type ?>" class="form-control select2" required>
                            <option value="">-- Select First Supervisor --</option>
                            <?php if (!empty($active_staff)): ?>
                                <?php foreach ($active_staff as $staff): ?>
                                    <option value="<?= (int)$staff->staff_id ?>" <?= ($current_supervisor_1 && (int)$current_supervisor_1 === (int)$staff->staff_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($staff->staff_name, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($type =='endterm'): ?>
                    <div class="mb-3">
                        <label for="supervisor_2_<?= $type ?>" class="form-label">Second Supervisor (Optional)</label>
                        <select name="supervisor_2" id="supervisor_2_<?= $type ?>" class="form-control select2">
                            <option value="">-- Select Second Supervisor (Optional) --</option>
                            <?php if (!empty($active_staff)): ?>
                                <?php foreach ($active_staff as $staff): ?>
                                    <option value="<?= (int)$staff->staff_id ?>" <?= ($current_supervisor_2 && (int)$current_supervisor_2 === (int)$staff->staff_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($staff->staff_name, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <small><i class="fa fa-info-circle"></i> This will update supervisors in both the PPA entry and the staff contract.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="submitSupervisorBtn_<?= $type ?>" class="btn btn-primary">Update Supervisors</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Button to trigger modal -->
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>">
    <i class="fa fa-edit"></i> Change Supervisors
</button>

<script>
$(document).ready(function() {
    // Initialize Select2 when modal is shown
    $('#<?= $modal_id ?>').on('shown.bs.modal', function() {
        var supervisor1Select = $('#supervisor_1_<?= $type ?>');
        var supervisor2Select = $('#supervisor_2_<?= $type ?>');
        
        // Initialize Select2
        supervisor1Select.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#<?= $modal_id ?>')
        });
        
        supervisor2Select.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#<?= $modal_id ?>')
        });
        
        // Ensure selected values are set after Select2 initialization
        <?php if (!empty($current_supervisor_1)): ?>
        supervisor1Select.val('<?= (int)$current_supervisor_1 ?>').trigger('change');
        <?php endif; ?>
        
        <?php if (!empty($current_supervisor_2)): ?>
        supervisor2Select.val('<?= (int)$current_supervisor_2 ?>').trigger('change');
        <?php endif; ?>
    });
    
    // Destroy Select2 when modal is hidden to prevent conflicts
    $('#<?= $modal_id ?>').on('hidden.bs.modal', function() {
        $('#supervisor_1_<?= $type ?>, #supervisor_2_<?= $type ?>').select2('destroy');
    });
    
    // Handle form submission via AJAX - use button click instead of form submit
    $(document).off('click', '#submitSupervisorBtn_<?= $type ?>').on('click', '#submitSupervisorBtn_<?= $type ?>', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var form = $('#changeSupervisorForm_<?= $type ?>');
        var submitBtn = $(this);
        var originalText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');
        
        // Get form values - use serializeArray which works with Select2
        var formArray = form.serializeArray();
        var formDataObj = {};
        
        // Convert array to object
        $.each(formArray, function(i, field) {
            formDataObj[field.name] = field.value;
        });
        
        // Get entry_id for validation
        var entryId = formDataObj.entry_id;
        if (!entryId || entryId.trim() === '') {
            submitBtn.prop('disabled', false).html(originalText);
            var errorMsg = 'Entry ID is missing. Please refresh the page and try again.';
            if (typeof show_notification !== 'undefined') {
                show_notification(errorMsg, 'error');
            } else if (typeof Lobibox !== 'undefined') {
                Lobibox.notify('error', {
                    pauseDelayOnHover: true,
                    continueDelayOnInactiveTab: false,
                    position: 'top right',
                    icon: 'bx bx-error-circle',
                    msg: errorMsg
                });
            } else {
                alert(errorMsg);
            }
            return false;
        }
        
        // Validate required fields
        if (!formDataObj.supervisor_1) {
            submitBtn.prop('disabled', false).html(originalText);
            var errorMsg = 'Please select a first supervisor';
            if (typeof show_notification !== 'undefined') {
                show_notification(errorMsg, 'error');
            } else if (typeof Lobibox !== 'undefined') {
                Lobibox.notify('error', {
                    pauseDelayOnHover: true,
                    continueDelayOnInactiveTab: false,
                    position: 'top right',
                    icon: 'bx bx-error-circle',
                    msg: errorMsg
                });
            } else {
                alert(errorMsg);
            }
            return false;
        }
        
        // Add CSRF token
        var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?= $this->security->get_csrf_hash(); ?>';
        formDataObj[csrfName] = csrfHash;
        
        // Debug: Log values
        console.log('Form values:', formDataObj);
        
        // Submit via AJAX
        $.ajax({
            url: '<?= base_url('performance/update_supervisors') ?>',
            type: 'POST',
            data: formDataObj,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                // Update CSRF token if provided
                if (response.new_csrf_hash) {
                    // Update any CSRF token inputs on the page
                    $('input[name="' + csrfName + '"]').val(response.new_csrf_hash);
                }
                
                if (response.success) {
                    // Show success notification
                    if (typeof show_notification !== 'undefined') {
                        show_notification(response.message, 'success');
                    } else if (typeof Lobibox !== 'undefined') {
                        Lobibox.notify('success', {
                            pauseDelayOnHover: true,
                            continueDelayOnInactiveTab: false,
                            position: 'top right',
                            icon: 'bx bx-check-circle',
                            msg: response.message
                        });
                    }
                    
                    // Close modal
                    $('#<?= $modal_id ?>').modal('hide');
                    
                    // Reload page after a short delay to show updated data
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    // Show error notification
                    if (typeof show_notification !== 'undefined') {
                        show_notification(response.message || 'An error occurred', 'error');
                    } else if (typeof Lobibox !== 'undefined') {
                        Lobibox.notify('error', {
                            pauseDelayOnHover: true,
                            continueDelayOnInactiveTab: false,
                            position: 'top right',
                            icon: 'bx bx-error-circle',
                            msg: response.message || 'An error occurred'
                        });
                    }
                    
                    // Re-enable submit button
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'An error occurred while updating supervisors';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    } catch(e) {
                        // If not JSON, use default message
                    }
                }
                
                // Show error notification
                if (typeof show_notification !== 'undefined') {
                    show_notification(errorMsg, 'error');
                } else if (typeof Lobibox !== 'undefined') {
                    Lobibox.notify('error', {
                        pauseDelayOnHover: true,
                        continueDelayOnInactiveTab: false,
                        position: 'top right',
                        icon: 'bx bx-error-circle',
                        msg: errorMsg
                    });
                }
                
                // Re-enable submit button
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
        
        return false;
    });
    
    // Also prevent form submission as backup
    $(document).off('submit', '#changeSupervisorForm_<?= $type ?>').on('submit', '#changeSupervisorForm_<?= $type ?>', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
});
</script>
<?php endif; ?>

