<?php
$session = $this->session->userdata('user');

if (!empty($ppa)) {
  $readonly = '';
  $staff_id = $ppa->staff_id;
  $staff_contract_id = $ppa->staff_contract_id;
  $contract = Modules::run('performance/ppa_contract', $staff_contract_id);
} else {
  $staff_id = $session->staff_id;
  $contract = Modules::run('auth/contract_info', $staff_id);
  $staff_contract_id = $contract->staff_contract_id;
}

$permissions = $session->permissions;
$ppa_settings = ppa_settings();

//dd($ppa_settings);

$readonly = '';
if (!isset($ppa) || empty($ppa)) {
    $readonly = '';
} else {
    $status = (int) @$ppa->draft_status;
    $isDraft = $status === 1;
    $isSubmitted = $status === 0;
    $isApproved = $status === 2;
    $isOwner = isset($ppa->staff_id) && $session->staff_id == $ppa->staff_id;
    $isSupervisor = in_array($session->staff_id, [(int) @$ppa->midterm_supervisor_1, (int) @$ppa->midterm_supervisor_2]);

    if (
        ($isApproved) ||
        ($isSubmitted && !$isSupervisor) ||
        ($isDraft && !$isOwner)
    ) {
        $readonly = 'readonly disabled';
    }
}


$midreadonly = '';

if (!isset($midppa) || empty($midppa)) {
    // New Midterm entry, allow editing
    $midreadonly = '';
} else {
    $mid_status = (int) @$midppa->midterm_draft_status;

    $isMidDraft = $mid_status === 1;
    $isMidSubmitted = $mid_status === 0;
    $isMidApproved = $mid_status === 2;

    $isOwner = isset($midppa->staff_id) && $session->staff_id == $midppa->staff_id;
    $isSupervisor = in_array(
        (int) $session->staff_id,
        [
            (int) @$midppa->midterm_supervisor_1,
            (int) @$midppa->midterm_supervisor_2
        ]
    );

    if (
        $isMidApproved ||
        ($isMidSubmitted && !$isSupervisor) ||
        ($isMidDraft && !$isOwner)
    ) {
        $midreadonly = 'readonly disabled';
    }
}
$midterm_exists = $this->per_mdl->ismidterm_available($ppa->entry_id);


// ✅ FIXED: define before usage
$showApprovalBtns = show_midterm_approval_action(@$midppa, @$approval_trail, $session);
//dd($showApprovalBtns);

$selected_skills = is_string($ppa->required_skills ?? null) ? json_decode($ppa->required_skills, true) : ($ppa->required_skills ?? []);
$objectives_raw = $ppa->objectives ?? [];
$objectives = json_decode(json_encode($objectives_raw), true);
if (!is_array($objectives)) $objectives = [];

$this->load->view('ppa_tabs');

// ✅ SAFE to use here now
if ($showApprovalBtns != 'show') echo $showApprovalBtns;
?>

<?php echo form_open_multipart(base_url('performance/midterm/save_ppa'), ['id' => 'staff_ppa']); ?>
<input type="hidden" name="staff_id" value="<?= $staff_id ?>">
<input type="hidden" name="entry_id" value="<?= $ppa->entry_id ?>">
<input type="hidden" name="staff_contract_id" value="<?= $staff_contract_id ?>">

<!-- SECTION A: STAFF DETAILS -->
<?php $this->load->view('performance/midterm/midterm_section_a', compact('contract', 'ppa','midreadonly')); ?>
<hr>

<!-- SECTION B: PERFORMANCE OBJECTIVES WITH APPRAISAL -->
<?php $this->load->view('performance/midterm/midterm_section_b', compact('objectives', 'readonly','midreadonly','isSupervisor')); ?>
<hr>

<!-- SECTION C: APPRAISER'S COMMENTS -->
<?php $this->load->view('performance/midterm/midterm_section_c', compact('ppa', 'readonly','midreadonly','isSupervisor')); ?>
<hr>

<!-- SECTION D: AU COMPETENCIES -->
<?php $this->load->view('performance/midterm/midterm_section_d', compact('ppa', 'readonly','midreadonly')); ?>
<hr>

<!-- SECTION E: PDP PROGRESS REVIEW -->
<?php $this->load->view('performance/midterm/midterm_section_e', compact('ppa', 'skills', 'readonly', 'selected_skills','midreadonly')); ?>
<hr>

<!-- SECTION F: SIGN OFF -->
<?php $this->load->view('performance/midterm/midterm_section_f', compact('ppa', 'ppa_settings', 'session', 'readonly','midreadonly')); ?>

<?php echo form_close(); ?>

<!-- Temporary test button -->
<!-- <button type="button" onclick="testNotification()" class="btn btn-warning btn-sm mb-3">Test Notification</button> -->

<style>
.required-indicator {
  font-weight: bold;
  font-size: 1.2em;
  margin-right: 5px;
}

.is-invalid {
  border-color: #dc3545 !important;
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

.is-valid {
  border-color: #28a745 !important;
  box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}

.validation-summary {
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 0.25rem;
  padding: 0.75rem 1.25rem;
  margin-bottom: 1rem;
  color: #721c24;
}

.validation-summary ul {
  margin-bottom: 0;
  padding-left: 1.5rem;
}

.validation-summary li {
  margin-bottom: 0.25rem;
}
</style>

<?php if ($showApprovalBtns == 'show' || in_array('83', $permissions)) {
    $this->load->view('performance/partials/approval_buttons', compact('ppa', 'ppa_settings', 'session', 'approval_trail','midreadonly','midterm_exists','permissions'));
} ?>

<!-- Approval Trail -->
<?php $this->load->view('performance/partials/approval_trail', compact('ppa','session', 'approval_trail','midreadonly')); ?>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Find the approval form (adjust selector if needed)
  const approvalForm = document.querySelector('form[id^="approvalForm_midterm_"]');
  if (!approvalForm) return;

  approvalForm.addEventListener('submit', function(e) {
    const actionInput = approvalForm.querySelector('input[name="action"]');
    if (actionInput && actionInput.value === 'approve') {
      // The main validation is now handled by the comprehensive validateForm() function
      // This prevents the default submission to allow the main form validation to run first
      e.preventDefault();
      
      // Trigger the main form validation
      const mainForm = document.getElementById('staff_ppa');
      if (mainForm) {
        // Create a submit event to trigger validation
        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
        mainForm.dispatchEvent(submitEvent);
      }
    }
  });
});
</script>
<script>
function toggleTrainingSection(show) {
  const section = document.getElementById('training-section');
  if (section) section.style.display = show ? 'block' : 'none';
}

function submitReturnAction(entryId) {
  const form = document.getElementById('approvalForm_' + entryId);
  if (form) {
    form.querySelector('#approval_action').value = 'return';
    form.submit();
  }
}
</script>
<script>
  function show_notification(message, msgtype) {
    Lobibox.notify(msgtype, {
      pauseDelayOnHover: true,
      continueDelayOnInactiveTab: false,
      position: 'top right',
      icon: 'bx bx-check-circle',
      msg: message
    });
  }
  
  // Test notification function
  function testNotification() {
    show_notification('Test notification - this should appear!', 'error');
  }
</script>
<script>
$(document).ready(function() {
  // Attach validation to the main form
  $('#staff_ppa').on('submit', function(e) {
    console.log('Form submission intercepted');
    const errors = validateForm();
    console.log('Validation errors:', errors);
    
    if (errors.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Showing validation summary with errors');
      displayValidationSummary(errors);
      show_notification('Please correct the validation errors before proceeding.', 'error');
      return false;
    } else {
      // Clear any existing validation summary if validation passes
      $('.validation-summary').remove();
    }
  });
  
  function validateForm() {
    const errors = [];
    
    // Validate competencies
    const competencyErrors = validateCompetencies();
    errors.push(...competencyErrors);
    
    // Validate midterm section (only for supervisors during approval)
    const midtermErrors = validateMidtermSection();
    errors.push(...midtermErrors);
    
    return errors;
  }
  
  // Display validation errors in a summary format
  function displayValidationSummary(errors) {
    // Remove any existing validation summary
    $('.validation-summary').remove();
    
    if (errors.length === 0) return;
    
    const summaryHtml = `
      <div class="validation-summary">
        <strong>Please correct the following errors before proceeding:</strong>
        <ul>
          ${errors.map(error => `<li>${error}</li>`).join('')}
        </ul>
      </div>
    `;
    
    // Insert the summary at the top of the form
    const mainForm = document.getElementById('staff_ppa');
    if (mainForm) {
      $(mainForm).prepend(summaryHtml);
      
      // Scroll to the top of the form to show the summary
      $('html, body').animate({
        scrollTop: $(mainForm).offset().top - 20
      }, 500);
    }
  }
  
  function validateMidtermSection() {
    const errors = [];
    
    // Check if this is a supervisor approval action
    const approvalForm = document.querySelector('form[id^="approvalForm_midterm_"]');
    if (!approvalForm) return errors;
    
    const actionInput = approvalForm.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'approve') return errors;
    
    console.log('Validating midterm section for approval');
    
    // Validate staff self-appraisal fields
    const selfAppraisals = document.querySelectorAll('textarea[name*="[self_appraisal]"]');
    let selfAppraisalFilled = true;
    
    selfAppraisals.forEach(function(textarea, index) {
      if (textarea.value.trim() === '') {
        selfAppraisalFilled = false;
        console.log(`Self-appraisal ${index + 1} is empty`);
      }
    });
    
    if (!selfAppraisalFilled) {
      errors.push('Please ensure all Staff Self Appraisal fields are filled before approval');
    }
    
    // Validate appraiser rating fields
    const appraiserRatings = document.querySelectorAll('select[name*="[appraiser_rating]"]');
    let ratingsFilled = true;
    
    appraiserRatings.forEach(function(select, index) {
      if (select.value === '') {
        ratingsFilled = false;
        console.log(`Appraiser rating ${index + 1} is not selected`);
      }
    });
    
    if (!ratingsFilled) {
      errors.push('Please ensure all Appraiser\'s Rating fields are filled before approval');
    }
    
    // Validate that total weight equals 100%
    const weightInputs = document.querySelectorAll('input[name*="[weight]"]');
    let totalWeight = 0;
    let hasWeights = false;
    
    weightInputs.forEach(function(input) {
      const weight = parseFloat(input.value) || 0;
      if (weight > 0) {
        totalWeight += weight;
        hasWeights = true;
      }
    });
    
    if (hasWeights && Math.abs(totalWeight - 100) > 0.01) {
      errors.push(`Total weight must equal 100%. Current total: ${totalWeight}%`);
    }
    
    console.log('Midterm validation complete. Errors:', errors);
    return errors;
  }
  
  function validateCompetencies() {
    const errors = [];
    const competencyGroups = {};
    
    console.log('Starting competency validation');
    console.log('Found competency radios:', $('.competency-radio').length);
    
    // Group radio buttons by competency
    $('.competency-radio').each(function() {
      const name = $(this).attr('name');
      const category = $(this).data('category');
      
      console.log('Processing radio:', name, 'category:', category);
      
      if (!competencyGroups[name]) {
        competencyGroups[name] = {
          category: category,
          checked: false,
          competencyId: $(this).data('competency-id'),
          competencyName: $(this).closest('tr').find('td:first strong').text().trim()
        };
      }
      
      if ($(this).is(':checked')) {
        competencyGroups[name].checked = true;
      }
    });
    
    console.log('Competency groups:', competencyGroups);
    
    // Check each competency group
    Object.keys(competencyGroups).forEach(function(name) {
      const group = competencyGroups[name];
      
      // Only validate non-leadership competencies as required
      if (group.category !== 'leadership' && !group.checked) {
        errors.push(`Please rate Competency ${group.competencyId} (${getCategoryName(group.category)})`);
      }
    });
    
    console.log('Final competency errors:', errors);
    return errors;
  }
  
  function getCategoryName(category) {
    const categoryNames = {
      'values': 'AU Values',
      'core': 'Core Competencies', 
      'functional': 'Functional Competencies',
      'leadership': 'Leadership Competencies'
    };
    return categoryNames[category] || category;
  }
  
  // Enhanced approval form submission with form saving
  $(document).on('submit', 'form[id^="approvalForm_midterm_"]', function(e) {
    const actionInput = this.querySelector('input[name="action"]');
    if (actionInput && actionInput.value === 'approve') {
      // Save the main form data first
      saveMainFormData();
    }
  });
  
  // Add real-time validation feedback
  $(document).on('input change', 'textarea[name*="[self_appraisal"], select[name*="[appraiser_rating"]', function() {
    validateFieldInRealTime(this);
  });
  
  function validateFieldInRealTime(field) {
    const fieldType = field.tagName.toLowerCase();
    const isRequired = fieldType === 'select' ? field.value === '' : field.value.trim() === '';
    
    // Remove existing validation classes
    $(field).removeClass('is-valid is-invalid');
    
    if (isRequired) {
      $(field).addClass('is-invalid');
      // Add validation message if it doesn't exist
      if (!$(field).next('.invalid-feedback').length) {
        const message = fieldType === 'select' ? 'Please select a rating' : 'This field is required';
        $(field).after(`<div class="invalid-feedback">${message}</div>`);
      }
    } else {
      $(field).addClass('is-valid');
      // Remove validation message
      $(field).next('.invalid-feedback').remove();
    }
  }
  
  // Add visual indicators for required fields
  function highlightRequiredFields() {
    const selfAppraisals = document.querySelectorAll('textarea[name*="[self_appraisal]"]');
    const appraiserRatings = document.querySelectorAll('select[name*="[appraiser_rating]"]');
    
    // Add required indicators
    selfAppraisals.forEach(function(field) {
      if (!$(field).prev('.required-indicator').length) {
        $(field).before('<span class="required-indicator text-danger">*</span> ');
      }
    });
    
    appraiserRatings.forEach(function(field) {
      if (!$(field).prev('.required-indicator').length) {
        $(field).before('<span class="required-indicator text-danger">*</span> ');
      }
    });
  }
  
  // Initialize required field indicators
  $(document).ready(function() {
    highlightRequiredFields();
  });
  
  function saveMainFormData() {
    const mainForm = document.getElementById('staff_ppa');
    if (!mainForm) return;
    
    // Create a temporary form data object
    const formData = new FormData(mainForm);
    
    // Add a flag to indicate this is a save operation
    formData.append('save_on_approval', '1');
    
    // Send AJAX request to save the form
    $.ajax({
      url: mainForm.action,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        console.log('Form saved successfully before approval');
        
        // Show confirmation dialog before proceeding with approval
        if (typeof Lobibox !== 'undefined') {
          Lobibox.confirm({
            msg: '<b>Form data has been saved successfully.</b><br><br>Please review the following before final approval:<br><ul>' +
                 '<li>All Staff Self Appraisal fields are filled</li>' +
                 '<li>All Appraiser\'s Rating fields are selected</li>' +
                 '<li>Total weight equals 100%</li>' +
                 '<li>All required competencies are rated</li></ul><br>' +
                 'Do you want to proceed with the approval?',
            title: 'Confirm Approval',
            callback: function($this, type) {
              if (type === 'yes') {
                // Continue with the approval process
                const approvalForm = document.querySelector('form[id^="approvalForm_midterm_"]');
                if (approvalForm) {
                  // Remove the temporary flag and submit the approval form
                  const tempFlag = approvalForm.querySelector('input[name="save_on_approval"]');
                  if (tempFlag) tempFlag.remove();
                  approvalForm.submit();
                }
              } else {
                show_notification('Approval cancelled. You can make further adjustments if needed.', 'info');
              }
            }
          });
        } else {
          // Fallback for when Lobibox is not available
          if (confirm('Form data has been saved successfully. Do you want to proceed with the approval?')) {
            const approvalForm = document.querySelector('form[id^="approvalForm_midterm_"]');
            if (approvalForm) {
              const tempFlag = approvalForm.querySelector('input[name="save_on_approval"]');
              if (tempFlag) tempFlag.remove();
              approvalForm.submit();
            }
          } else {
            show_notification('Approval cancelled. You can make further adjustments if needed.', 'info');
          }
        }
      },
      error: function(xhr, status, error) {
        console.error('Error saving form before approval:', error);
        show_notification('Error saving form data before approval. Please try again.', 'error');
      }
    });
  }
});
</script>