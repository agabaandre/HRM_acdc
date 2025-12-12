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
    $isSupervisor = in_array($session->staff_id, [(int) @$ppa->endterm_supervisor_1, (int) @$ppa->endterm_supervisor_2]);

    if (
        ($isApproved) ||
        ($isSubmitted && !$isSupervisor) ||
        ($isDraft && !$isOwner)
    ) {
        $readonly = 'readonly disabled';
    }
}


$endreadonly = '';

if (!isset($endppa) || empty($endppa)) {
    // New Endterm entry, allow editing
    $endreadonly = '';
} else {
    $end_status = (int) @$endppa->endterm_draft_status;

    $isEndDraft = $end_status === 1;
    $isEndSubmitted = $end_status === 0;
    $isEndApproved = $end_status === 2;

    $isOwner = isset($endppa->staff_id) && $session->staff_id == $endppa->staff_id;
    $isSupervisor = in_array(
        (int) $session->staff_id,
        [
            (int) @$endppa->endterm_supervisor_1,
            (int) @$endppa->endterm_supervisor_2
        ]
    );

    // Check if endterm was returned - if so, allow owner to resubmit
    $isReturned = false;
    if (!empty($approval_trail) && is_array($approval_trail)) {
        // Approval trail is ordered by most recent first (from model)
        $lastAction = reset($approval_trail); // Get first element safely
        if ($lastAction && isset($lastAction->action) && $lastAction->action === 'Returned') {
            $isReturned = true;
        }
    }

    if (
        $isEndApproved ||
        ($isEndSubmitted && !$isSupervisor && !($isReturned && $isOwner)) ||
        ($isEndDraft && !$isOwner)
    ) {
        $endreadonly = 'readonly disabled';
    }
}
$endterm_exists = $this->per_mdl->isendterm_available($ppa->entry_id);


// ✅ FIXED: define before usage
// For now, use a simple check - you can create show_endterm_approval_action later
$showApprovalBtns = 'show'; // Simplified for now
//dd($showApprovalBtns);

$selected_skills = is_string($ppa->required_skills ?? null) ? json_decode($ppa->required_skills, true) : ($ppa->required_skills ?? []);

// Get objectives: endterm first, then midterm, then original PPA
$objectives = [];
if (!empty($ppa->endterm_objectives)) {
    $objectives_raw = $ppa->endterm_objectives;
    $objectives = is_string($objectives_raw) ? json_decode($objectives_raw, true) : (is_array($objectives_raw) ? $objectives_raw : []);
} elseif (!empty($ppa->midterm_objectives)) {
    // Fallback to midterm objectives if endterm objectives are empty
    $objectives_raw = $ppa->midterm_objectives;
    $objectives = is_string($objectives_raw) ? json_decode($objectives_raw, true) : (is_array($objectives_raw) ? $objectives_raw : []);
} else {
    // Fallback to original PPA objectives
$objectives_raw = $ppa->objectives ?? [];
$objectives = json_decode(json_encode($objectives_raw), true);
}
if (!is_array($objectives)) $objectives = [];

$this->load->view('ppa_tabs');

// ✅ SAFE to use here now
if ($showApprovalBtns != 'show') echo $showApprovalBtns;

// Print Buttons - Show only when endterm is actually approved (draft_status = 2) and not returned
// If draft_status == 2, it means the endterm is fully approved regardless of supervisor fields
$canPrint = false;
if (!empty($ppa) && !empty($endppa)) {
    // Check if endterm was returned (most recent action is "Returned")
    $isReturned = false;
    if (!empty($approval_trail) && is_array($approval_trail)) {
        $most_recent_action = reset($approval_trail);
        if ($most_recent_action && isset($most_recent_action->action) && $most_recent_action->action === 'Returned') {
            $isReturned = true;
        }
    }
    
    // Only show print buttons if endterm is actually approved (draft_status = 2) and not returned
    $isEndApproved = isset($endppa->endterm_draft_status) && (int)$endppa->endterm_draft_status === 2;
    
    if ($isEndApproved && !$isReturned) {
        $canPrint = true;
    }
}
?>
<?php if ($canPrint): ?>
    <div class="mb-3">
        <a href="<?= base_url('performance/endterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id) ?>" 
           class="btn btn-dark btn-sm me-2" target="_blank">
            <i class="fa fa-print"></i> Print Endterm without Approval Trail
        </a>
        <a href="<?= base_url('performance/endterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id . '/1') ?>" 
           class="btn btn-dark btn-sm" target="_blank">
            <i class="fa fa-print"></i> Print Endterm With Approval Trail
        </a>
    </div>
<?php endif; ?>

<?php echo form_open_multipart(base_url('performance/endterm/save_ppa'), ['id' => 'staff_ppa']); ?>
<input type="hidden" name="staff_id" value="<?= $staff_id ?>">
<input type="hidden" name="entry_id" value="<?= $ppa->entry_id ?>">
<input type="hidden" name="staff_contract_id" value="<?= $staff_contract_id ?>">

<!-- SECTION A: STAFF DETAILS -->
<?php 
// Check if endterm was returned - needed for supervisor editing
$isReturnedForResubmit = false;
if (!empty($approval_trail) && is_array($approval_trail)) {
    $lastAction = reset($approval_trail);
    if ($lastAction && isset($lastAction->action) && $lastAction->action === 'Returned') {
        $isOwner = isset($ppa->staff_id) && $session->staff_id == $ppa->staff_id;
        $isReturnedForResubmit = $isOwner;
    }
}
$this->load->view('performance/endterm/endterm_section_a', compact('contract', 'ppa','endreadonly', 'isReturnedForResubmit', 'session')); ?>
<hr>

<!-- SECTION B: PERFORMANCE OBJECTIVES WITH APPRAISAL -->
<?php $this->load->view('performance/endterm/endterm_section_b', compact('objectives', 'readonly','endreadonly','isSupervisor', 'ppa')); ?>
<hr>

<!-- SECTION C: APPRAISER'S COMMENTS -->
<?php $this->load->view('performance/endterm/endterm_section_c', compact('ppa', 'readonly','endreadonly','isSupervisor')); ?>
<hr>

<!-- SECTION D: AU COMPETENCIES -->
<?php $this->load->view('performance/endterm/endterm_section_d', compact('ppa', 'readonly','endreadonly')); ?>
<hr>

<!-- SECTION E: PDP PROGRESS REVIEW -->
<?php $this->load->view('performance/endterm/endterm_section_e', compact('ppa', 'skills', 'readonly', 'selected_skills','endreadonly')); ?>
<hr>

<!-- SECTION F: SIGN OFF -->
<?php $this->load->view('performance/endterm/endterm_section_f', compact('ppa', 'ppa_settings', 'session', 'readonly','endreadonly', 'approval_trail')); ?>

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
  //d($showApprovalBtns);
    $this->load->view('performance/partials/approval_buttons_endterm', compact('ppa', 'ppa_settings', 'session', 'approval_trail','endreadonly','endterm_exists','permissions'));
} ?>

<!-- Approval Trail -->
<?php $this->load->view('performance/partials/approval_trail_endterm', compact('ppa','session', 'approval_trail','endreadonly')); ?>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Find the approval form (adjust selector if needed)
  const approvalForm = document.querySelector('form[id^="approvalForm_endterm_"]');
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
    
    // Validate endterm section (only for supervisors during approval)
    const endtermErrors = validateEndtermSection();
    errors.push(...endtermErrors);
    
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
  
  function validateEndtermSection() {
    const errors = [];
    const fieldErrors = [];
    
    // Always validate if approval form exists
    const approvalForm = document.querySelector('form[id^="approvalForm_endterm_"]');
    if (!approvalForm) {
      console.log('No approval form found, skipping endterm validation');
      return errors;
    }
    
    console.log('=== VALIDATING ENDTERM SECTION ===');
    console.log('Approval form found, running validation');
    
    // Clear previous field errors
    $('.field-error-message').remove();
    $('.objective-rating').removeClass('is-invalid');
    $('textarea[name*="[self_appraisal]"]').removeClass('is-invalid');
    
    // Validate staff self-appraisal fields
    const selfAppraisals = document.querySelectorAll('textarea[name*="[self_appraisal]"]');
    let selfAppraisalFilled = true;
    let emptySelfAppraisals = [];
    
    selfAppraisals.forEach(function(textarea, index) {
      if (textarea.value.trim() === '') {
        selfAppraisalFilled = false;
        emptySelfAppraisals.push(index + 1);
        console.log(`Self-appraisal ${index + 1} is empty`);
        
        // Add visual error indicator
        $(textarea).addClass('is-invalid');
        
        // Add error message below field
        if (!$(textarea).next('.field-error-message').length) {
          $(textarea).after('<div class="field-error-message text-danger small mt-1">Please fill in the Staff Self Appraisal</div>');
        }
      } else {
        $(textarea).removeClass('is-invalid');
        $(textarea).next('.field-error-message').remove();
      }
    });
    
    if (!selfAppraisalFilled) {
      const errorMsg = `Please ensure all Staff Self Appraisal fields are filled. Missing: ${emptySelfAppraisals.join(', ')}`;
      errors.push(errorMsg);
      fieldErrors.push(errorMsg);
    }
    
    // Validate appraiser rating fields
    const appraiserRatings = document.querySelectorAll('select[name*="[appraiser_rating]"].objective-rating');
    let ratingsFilled = true;
    let emptyRatings = [];
    
    appraiserRatings.forEach(function(select, index) {
      if (select.value === '') {
        ratingsFilled = false;
        emptyRatings.push(index + 1);
        console.log(`Appraiser rating ${index + 1} is not selected`);
        
        // Add visual error indicator
        $(select).addClass('is-invalid');
        
        // Add error message below field
        if (!$(select).next('.field-error-message').length) {
          $(select).after('<div class="field-error-message text-danger small mt-1">Please select an Appraiser\'s Rating</div>');
        }
      } else {
        $(select).removeClass('is-invalid');
        $(select).next('.field-error-message').remove();
      }
    });
    
    if (!ratingsFilled) {
      const errorMsg = `Please ensure all Appraiser's Rating fields are selected. Missing: ${emptyRatings.join(', ')}`;
      errors.push(errorMsg);
      fieldErrors.push(errorMsg);
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
      const errorMsg = `Total weight must equal 100%. Current total: ${totalWeight}%`;
      errors.push(errorMsg);
      fieldErrors.push(errorMsg);
    }
    
    // Validate achievements field (question 1) - required for supervisors
    const achievementsField = document.getElementById('endterm_achievements');
    if (achievementsField && !achievementsField.readOnly && !achievementsField.disabled) {
      // Field is editable, so user is a supervisor
      if (achievementsField.value.trim() === '') {
        const errorMsg = 'Please fill in "What has been achieved in relation to the Performance Objectives?"';
        errors.push(errorMsg);
        fieldErrors.push(errorMsg);
        
        // Add visual error indicator
        $(achievementsField).addClass('is-invalid');
        
        // Add error message below field
        const errorMsgElement = $(achievementsField).siblings('.field-error-message');
        if (errorMsgElement.length) {
          errorMsgElement.text(errorMsg).show();
        } else {
          $(achievementsField).after(`<div class="field-error-message text-danger small mt-1">${errorMsg}</div>`);
        }
      } else {
        $(achievementsField).removeClass('is-invalid');
        $(achievementsField).siblings('.field-error-message').hide();
      }
    }
    
    // Validate discussion confirmation checkbox (only for first supervisor on approve)
    const actionInput = approvalForm.querySelector('input[name="action"]');
    const discussionCheckbox = approvalForm.querySelector('input[name="discussion_confirmation"]');
    if (actionInput && actionInput.value === 'approve' && discussionCheckbox) {
      if (!discussionCheckbox.checked) {
        const errorMsg = 'Please confirm that you have formally discussed the results of this review with the staff member';
        errors.push(errorMsg);
        fieldErrors.push(errorMsg);
        
        // Add visual error indicator
        $(discussionCheckbox).addClass('is-invalid');
        
        // Add error message
        const checkboxLabel = $(discussionCheckbox).closest('.form-check');
        if (!checkboxLabel.find('.field-error-message').length) {
          checkboxLabel.append('<div class="field-error-message text-danger small mt-1">This confirmation is required</div>');
        }
      } else {
        $(discussionCheckbox).removeClass('is-invalid');
        $(discussionCheckbox).closest('.form-check').find('.field-error-message').remove();
      }
    }
    
    console.log('Endterm validation complete. Errors:', errors);
    console.log('Field errors:', fieldErrors);
    return errors;
  }
  
  function validateCompetencies() {
    const errors = [];
    const competencyGroups = {};
    const competencyFieldErrors = [];
    
    // Clear previous competency errors
    $('.competency-error-message').remove();
    $('.competency-table tr').removeClass('table-danger');
    
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
          competencyName: $(this).closest('tr').find('td:first strong').text().trim(),
          row: $(this).closest('tr'),
          isRequired: category !== 'leadership'
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
      console.log(`Checking competency ${group.competencyId}: isRequired=${group.isRequired}, checked=${group.checked}`);
      
      // Validate non-leadership competencies as required (always)
      if (group.isRequired && !group.checked) {
        const errorMsg = `Please rate Competency ${group.competencyId} (${getCategoryName(group.category)})`;
        errors.push(errorMsg);
        competencyFieldErrors.push(errorMsg);
        console.log(`Found missing competency: ${errorMsg}`);
        
        // Highlight the row and add error message
        group.row.addClass('table-danger');
        if (!group.row.next('.competency-error-message').length) {
          group.row.after('<tr class="competency-error-message"><td colspan="6" class="text-danger small bg-light"><strong>Error:</strong> ' + errorMsg + '</td></tr>');
          console.log(`Added error message for competency ${group.competencyId}`);
        }
      } else {
        group.row.removeClass('table-danger');
        group.row.next('.competency-error-message').remove();
      }
    });
    
    // Display competency errors summary above competencies section
    const competencyHeading = $('h4:contains("D. Competencies")');
    if (competencyFieldErrors.length > 0) {
      // Remove existing summary
      $('.competency-validation-summary').remove();
      // Add summary right after the heading
      const summaryHtml = '<div class="competency-validation-summary alert alert-danger mt-3 mb-3"><strong>Please fix the following competency errors:</strong><ul class="mb-0 mt-2">' + 
        competencyFieldErrors.map(err => '<li>' + err + '</li>').join('') + 
        '</ul></div>';
      competencyHeading.after(summaryHtml);
    } else {
      $('.competency-validation-summary').remove();
    }
    
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
  
  // Enhanced approval form submission with validation
  $(document).on('submit', 'form[id^="approvalForm_endterm_"]', function(e) {
    const actionInput = $(this).find('input[name="action"]');
    if (actionInput.length && actionInput.val() === 'approve') {
      // Prevent default submission
      e.preventDefault();
      e.stopPropagation();
      
      console.log('=== APPROVAL FORM SUBMIT INTERCEPTED ===');
      
      // Clear previous errors first
      $('.field-error-message').remove();
      $('.is-invalid').removeClass('is-invalid');
      $('.competency-error-message').remove();
      $('.competency-table tr').removeClass('table-danger');
      $('input[name="discussion_confirmation"]').removeClass('is-invalid');
      $('#endterm_achievements').removeClass('is-invalid');
      
      // Run validation
      const errors = validateForm();
      console.log('Total validation errors found:', errors.length);
      console.log('Error details:', errors);
      
      // Also check for invalid fields
      const invalidFields = $('.is-invalid').length;
      console.log('Invalid fields count:', invalidFields);
      
      if (errors.length > 0 || invalidFields > 0) {
        // Show validation errors
        if (errors.length > 0) {
          displayValidationSummary(errors);
        }
        show_notification('Please correct the validation errors before proceeding.', 'error');
        
        // Scroll to first error
        const firstError = $('.is-invalid').first();
        if (firstError.length) {
          $('html, body').animate({
            scrollTop: firstError.offset().top - 100
          }, 500);
        } else {
          // Scroll to validation summary
          const summary = $('.validation-summary, .competency-validation-summary').first();
          if (summary.length) {
            $('html, body').animate({
              scrollTop: summary.offset().top - 100
            }, 500);
          }
        }
        return false;
      } else {
        // Validation passed, save form and proceed with approval
        console.log('Validation passed, saving form and proceeding with approval');
      saveMainFormData();
      }
    }
  });
  
  // Add real-time validation feedback
  $(document).on('input change', 'textarea[name*="[self_appraisal"], select[name*="[appraiser_rating"]', function() {
    validateFieldInRealTime(this);
  });
  
  // Add real-time validation for achievements field (question 1) - only for supervisors
  $(document).on('input blur', '#endterm_achievements', function() {
    validateFieldInRealTime(this);
  });
  
  // Add real-time validation for competency radios
  $(document).on('change', '.competency-radio', function() {
    const name = $(this).attr('name');
    const row = $(this).closest('tr');
    
    // Remove error styling if a selection is made
    if ($(this).is(':checked')) {
      row.removeClass('table-danger');
      row.next('.competency-error-message').remove();
      
      // Re-validate competencies to update summary
      const approvalForm = document.querySelector('form[id^="approvalForm_endterm_"]');
      if (approvalForm) {
        validateCompetencies();
      }
    }
  });
  
  function validateFieldInRealTime(field) {
    const fieldType = field.tagName.toLowerCase();
    const fieldId = field.id || '';
    const isRequired = fieldType === 'select' ? field.value === '' : field.value.trim() === '';
    
    // Remove existing validation classes
    $(field).removeClass('is-valid is-invalid');
    
    // Special handling for achievements field
    if (fieldId === 'endterm_achievements') {
      // Only validate if field is editable (supervisor)
      if (!field.readOnly && !field.disabled) {
        if (isRequired) {
          $(field).addClass('is-invalid');
          const errorMsgElement = $(field).siblings('.field-error-message');
          if (errorMsgElement.length) {
            errorMsgElement.text('Please fill in "What has been achieved in relation to the Performance Objectives?"').show();
          } else {
            $(field).after('<div class="field-error-message text-danger small mt-1">Please fill in "What has been achieved in relation to the Performance Objectives?"</div>');
          }
        } else {
          $(field).addClass('is-valid');
          $(field).siblings('.field-error-message').hide();
        }
      }
      return;
    }
    
    // Standard validation for other fields
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
                const approvalForm = document.querySelector('form[id^="approvalForm_endterm_"]');
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
            const approvalForm = document.querySelector('form[id^="approvalForm_endterm_"]');
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