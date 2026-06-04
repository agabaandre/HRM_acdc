<?php
$session = $this->session->userdata('user');

if (!empty($ppa) && is_object($ppa)) {
  $readonly = '';
  $staff_id = $ppa->staff_id;
  $staff_contract_id = $ppa->staff_contract_id;
  $contract = Modules::run('performance/ppa_contract', $staff_contract_id);
  if (!is_object($contract) && !empty($ppa->staff_id)) {
    $contract = Modules::run('auth/contract_info', $ppa->staff_id);
  }
} else {
  $staff_id = $session->staff_id;
  $contract = Modules::run('auth/contract_info', $staff_id);
  $staff_contract_id = is_object($contract) ? $contract->staff_contract_id : '';
}

$contract_missing = !is_object($contract);
if ($contract_missing) {
  $contract = (object) [
    'staff_contract_id' => $staff_contract_id ?? '',
    'fname' => '',
    'lname' => '',
    'SAPNO' => '',
    'job_name' => '',
    'initiation_date' => '',
    'division_id' => null,
    'first_supervisor' => null,
    'second_supervisor' => null,
    'funder_id' => null,
    'contract_type_id' => null,
  ];
}

$permissions = $session->permissions;
$ppa_settings = ppa_settings();

//dd($ppa_settings);

$readonly = '';
if (!isset($ppa) || empty($ppa) || !is_object($ppa)) {
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

/** True when logged-in user is 1st or 2nd midterm supervisor (for preview button). */
$isMidtermApprover = false;
if (!empty($ppa) && is_object($ppa)) {
    $isMidtermApprover = in_array((int) $session->staff_id, [
        (int) ($ppa->midterm_supervisor_1 ?? 0),
        (int) ($ppa->midterm_supervisor_2 ?? 0),
    ], true);
}

// ✅ FIXED: define before usage
$showApprovalBtns = show_midterm_approval_action(@$midppa, @$approval_trail, $session);
//dd($showApprovalBtns);

$selected_skills = is_string($ppa->required_skills ?? null) ? json_decode($ppa->required_skills, true) : ($ppa->required_skills ?? []);
$objectives_raw = $ppa->objectives ?? [];
$objectives = json_decode(json_encode($objectives_raw), true);
if (!is_array($objectives)) $objectives = [];

$this->load->view('ppa_tabs');
$this->load->view('performance/partials/mte_rich_text_assets', ['wrap_midterm_training' => true]);

// ✅ SAFE to use here now
// helper approval buttons hidden per request
// if ($showApprovalBtns != 'show') echo $showApprovalBtns;
?>

<?php if (!empty($contract_missing)): ?>
  <div class="alert alert-warning" role="alert">
    <strong>No staff contract on file.</strong> We could not load your active contract. Please contact HR.
  </div>
<?php endif; ?>

<?php if (!empty($ppa) && !empty($ppa->entry_id) && !empty($midterm_exists)): ?>
  <div class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="<?= base_url('performance/midterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id) ?>"
         class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
        <i class="fa fa-print"></i> Print (No Trail)
      </a>
      <a href="<?= base_url('performance/midterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id . '/1') ?>"
         class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
        <i class="fa fa-print"></i> Print (With Trail)
      </a>
      <button type="button" class="btn btn-outline-success btn-sm" id="shareMidtermBtn">
        <i class="fa fa-share-alt"></i> Share
      </button>
      <?php
        $mailSubjectMidterm = trim(staff_name($ppa->staff_id) . ' Midterm ' . str_replace('-', ' ', (string)($ppa->performance_period ?? '')) . ' ' . (((int)($midppa->midterm_draft_status ?? -1) === 2) ? 'Final' : 'Draft'));
        $mailBodyMidterm = 'Please follow the link to view my Midterm ' . ((((int)($midppa->midterm_draft_status ?? -1) === 2) ? 'Final' : 'Draft')) . ".\n\n" . base_url('performance/midterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id);
      ?>
      <a class="btn btn-outline-success btn-sm" id="mailMidtermBtn" href="mailto:?subject=<?= rawurlencode($mailSubjectMidterm) ?>&body=<?= rawurlencode($mailBodyMidterm) ?>">
        <i class="fa fa-envelope"></i> Mail
      </a>
      <?php if (!empty($isMidtermApprover)): ?>
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#midtermApproverPreviewModal" id="midtermApproverPreviewBtn">
        <i class="fa fa-eye"></i> Preview
      </button>
      <?php endif; ?>
    </div>
    <small class="text-muted d-block mt-1">Watermark reflects midterm status: draft (1) or pending approval (0).</small>
  </div>
<?php endif; ?>

<?php if (!empty($ppa) && !empty($ppa->entry_id) && !empty($midterm_exists)): ?>
<script>
  (function() {
    const shareBtn = document.getElementById('shareMidtermBtn');
    if (!shareBtn) return;

    const employeeName = <?= json_encode(trim((string) staff_name($ppa->staff_id))) ?>;
    const financialYear = <?= json_encode(str_replace('-', ' ', (string) ($ppa->performance_period ?? ''))) ?>;
    const shareUrl = <?= json_encode(base_url('performance/midterm/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id)) ?>;
    const isApproved = <?= ((int) (@$midppa->midterm_draft_status ?? -1) === 2) ? 'true' : 'false' ?>;
    const versionLabel = isApproved ? 'Final' : 'Draft';
    const subject = `${employeeName} Midterm ${financialYear} ${versionLabel}`.trim();
    const body = `Please follow the link to view my Midterm ${versionLabel}.\n\n${shareUrl}`;
    const mailtoLink = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

    shareBtn.addEventListener('click', async function () {
      if (navigator.share) {
        try {
          await navigator.share({
            title: subject,
            text: body,
            url: shareUrl
          });
          return;
        } catch (err) {
          // User cancelled share dialog or share failed; fallback to email compose.
        }
      }
      window.location.href = mailtoLink;
    });

  })();
</script>
<?php endif; ?>

<?php echo form_open_multipart(base_url('performance/midterm/save_ppa'), ['id' => 'staff_ppa']); ?>
<input type="hidden" name="staff_id" value="<?= $staff_id ?>">
<input type="hidden" name="entry_id" value="<?= $ppa->entry_id ?>">
<input type="hidden" name="staff_contract_id" value="<?= $staff_contract_id ?>">

<div id="midtermPreviewSource">
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
</div><!-- /#midtermPreviewSource — excludes SECTION F (sign-off) and approval UI -->

<!-- SECTION F: SIGN OFF -->
<?php $this->load->view('performance/midterm/midterm_section_f', compact('ppa', 'ppa_settings', 'session', 'readonly','midreadonly')); ?>

<?php echo form_close(); ?>

<?php if (!empty($isMidtermApprover)): ?>
<div class="modal fade" id="midtermApproverPreviewModal" tabindex="-1" aria-labelledby="midtermApproverPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="midtermApproverPreviewModalLabel">Midterm review — preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3 midterm-preview-modal-body" id="midtermPreviewModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<style>
  #midtermApproverPreviewModal .preview-readonly-text { white-space: pre-wrap; word-break: break-word; min-height: 1.2em; }
</style>
<script>
(function () {
  function flattenPreviewToText(root) {
    root.querySelectorAll('input[type="hidden"]').forEach(function (n) { n.remove(); });
    root.querySelectorAll('textarea').forEach(function (el) {
      var d = document.createElement('div');
      d.className = el.classList.contains('ppa-summernote') ? 'preview-readonly-text ppa-html-preview' : 'preview-readonly-text';
      if (el.classList.contains('ppa-summernote')) {
        d.innerHTML = (el.value || '').trim() ? el.value : '<span class="text-muted">—</span>';
      } else {
        d.textContent = el.value || '';
      }
      el.parentNode.replaceChild(d, el);
    });
    root.querySelectorAll('select').forEach(function (el) {
      var d = document.createElement('div');
      d.className = 'preview-readonly-text';
      var t = [];
      if (el.selectedOptions) for (var i = 0; i < el.selectedOptions.length; i++) t.push(el.selectedOptions[i].text);
      d.textContent = t.length ? t.join(', ') : '—';
      el.parentNode.replaceChild(d, el);
    });
    root.querySelectorAll('input').forEach(function (el) {
      var type = (el.type || 'text').toLowerCase();
      if (type === 'hidden') return;
      if (type === 'button' || type === 'submit' || type === 'reset') { el.remove(); return; }
      if (type === 'checkbox') {
        var s = document.createElement('span');
        s.className = 'preview-readonly-text';
        s.textContent = el.checked ? 'Yes' : '—';
        el.parentNode.replaceChild(s, el);
        return;
      }
      if (type === 'radio') {
        if (el.checked) {
          var s = document.createElement('span');
          s.className = 'preview-readonly-text';
          var lab = el.nextElementSibling;
          s.textContent = (lab && lab.tagName === 'LABEL') ? lab.textContent.trim() : (el.value || '');
          el.parentNode.replaceChild(s, el);
          if (lab && lab.tagName === 'LABEL') lab.remove();
        } else {
          var lab2 = el.nextElementSibling;
          el.remove();
          if (lab2 && lab2.tagName === 'LABEL') lab2.remove();
        }
        return;
      }
      var s = document.createElement('span');
      s.className = 'preview-readonly-text';
      s.textContent = el.value || '—';
      el.parentNode.replaceChild(s, el);
    });
    root.querySelectorAll('button').forEach(function (el) { el.remove(); });
  }

  var modalEl = document.getElementById('midtermApproverPreviewModal');
  var srcEl = document.getElementById('midtermPreviewSource');
  var bodyEl = document.getElementById('midtermPreviewModalBody');
  if (!modalEl || !srcEl || !bodyEl) return;

  modalEl.addEventListener('show.bs.modal', function () {
    if (typeof window.syncMteSummernoteToTextareas === 'function') {
      window.syncMteSummernoteToTextareas();
    }
    bodyEl.innerHTML = '';
    var hint = document.createElement('p');
    hint.className = 'text-muted small mb-2';
    hint.textContent = 'Sections A–E only. Sign-off and approval actions are not shown here.';
    bodyEl.appendChild(hint);
    var clone = srcEl.cloneNode(true);
    clone.removeAttribute('id');
    clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
    flattenPreviewToText(clone);
    bodyEl.appendChild(clone);
  });
})();
</script>
<?php endif; ?>

<!-- Set performance period years for JavaScript validation -->
<script>
  // Extract years from performance period (e.g., "January-2025-to-December-2025" -> [2025])
  <?php if (!empty($ppa->performance_period)): ?>
    const performancePeriod = '<?= $ppa->performance_period ?>';
    const yearMatches = performancePeriod.match(/\d{4}/g);
    window.performancePeriodYears = yearMatches ? [...new Set(yearMatches.map(y => parseInt(y)))] : [];
  <?php else: ?>
    window.performancePeriodYears = [];
  <?php endif; ?>
</script>

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

<?php if (($showApprovalBtns == 'show' || in_array('83', $permissions))) {
    $this->load->view('performance/partials/approval_buttons', compact('ppa', 'ppa_settings', 'session', 'approval_trail','midreadonly','midterm_exists','permissions'));
} ?>

<!-- Approval Trail -->
<?php $this->load->view('performance/partials/approval_trail', compact('ppa','session', 'approval_trail','midreadonly')); ?>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
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
    if (typeof window.syncMteSummernoteToTextareas === 'function') {
      window.syncMteSummernoteToTextareas();
    }

    // Always validate, not just on approval
    const errors = validateForm();
    console.log('Validation errors:', errors);
    
    if (errors.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Showing validation summary with errors');
      displayValidationSummary(errors);
      show_notification('Please correct the validation errors before proceeding.', 'error');
      
      // Scroll to first error
      const firstError = $('.is-invalid').first();
      if (firstError.length) {
        $('html, body').animate({
          scrollTop: firstError.offset().top - 100
        }, 500);
      }
      
      return false;
    } else {
      // Clear any existing validation summary if validation passes
      $('.validation-summary').remove();
      $('.competency-validation-summary').remove();
    }
  });
  
  function validateForm() {
    console.log('=== validateForm() called ===');
    const errors = [];
    
    // Validate competencies
    console.log('Calling validateCompetencies()...');
    const competencyErrors = validateCompetencies();
    console.log('Competency errors returned:', competencyErrors.length, competencyErrors);
    errors.push(...competencyErrors);
    
    // Validate midterm section (only for supervisors during approval)
    console.log('Calling validateMidtermSection()...');
    const midtermErrors = validateMidtermSection();
    console.log('Midterm errors returned:', midtermErrors.length, midtermErrors);
    errors.push(...midtermErrors);
    
    console.log('Total errors in validateForm:', errors.length);
    console.log('All errors:', errors);
    return errors;
  }
  
  // Display validation errors in a summary format
  function displayValidationSummary(errors) {
    // Remove any existing validation summary
    $('.validation-summary').remove();
    
    if (errors.length === 0) return;
    
    const summaryHtml = `
      <div class="validation-summary alert alert-danger">
        <strong>Please correct the following errors before proceeding:</strong>
        <ul class="mb-0 mt-2">
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
    console.log('=== validateMidtermSection() called ===');
    const errors = [];
    const fieldErrors = [];
    
    // Clear previous field errors
    $('.field-error-message').remove();
    $('.is-invalid').removeClass('is-invalid');
    
    // Check if this is a supervisor approval action
    const approvalForm = document.querySelector('form[id^="approvalForm_midterm_"]');
    console.log('Approval form found:', approvalForm !== null);
    const actionInput = approvalForm ? approvalForm.querySelector('input[name="action"]') : null;
    const isApprovalAction = actionInput && actionInput.value === 'approve';
    
    // Always validate if approval form exists (supervisor is trying to approve)
    const shouldValidate = approvalForm !== null;
    
    console.log('Validating midterm section. Should validate:', shouldValidate, 'Is approval action:', isApprovalAction);
    
    // Validate staff self-appraisal fields (always validate if approval form exists)
    const selfAppraisals = document.querySelectorAll('textarea[name*="[self_appraisal]"]');
    console.log('Found self-appraisal fields:', selfAppraisals.length);
    let selfAppraisalFilled = true;
    
    if (shouldValidate) {
    selfAppraisals.forEach(function(textarea, index) {
        const $textarea = $(textarea);
        const isEmpty = typeof window.mteRichTextAreaEmpty === 'function'
          ? window.mteRichTextAreaEmpty(textarea)
          : textarea.value.trim() === '';
        console.log(`Self-appraisal ${index + 1}: isEmpty=${isEmpty}, value="${textarea.value}"`);
        if (isEmpty) {
        selfAppraisalFilled = false;
          $textarea.addClass('is-invalid');
          // Add error message below the field
          if (!$textarea.next('.field-error-message').length) {
            $textarea.after('<div class="field-error-message text-danger small mt-1">This field is required</div>');
            console.log(`Added error message to self-appraisal ${index + 1}`);
          }
        } else {
          $textarea.removeClass('is-invalid');
          $textarea.next('.field-error-message').remove();
      }
    });
    
      console.log('Self-appraisal filled:', selfAppraisalFilled);
    if (!selfAppraisalFilled) {
        errors.push('Please ensure all Staff Self Appraisal fields are filled');
      }
    } else {
      console.log('Skipping self-appraisal validation (shouldValidate=false)');
    }
    
    // Validate appraiser rating fields (always validate if approval form exists)
    const appraiserRatings = document.querySelectorAll('select[name*="[appraiser_rating]"]');
    console.log('Found appraiser rating fields:', appraiserRatings.length);
    let ratingsFilled = true;
    
    if (shouldValidate) {
    appraiserRatings.forEach(function(select, index) {
        const $select = $(select);
        const isEmpty = select.value === '';
        console.log(`Appraiser rating ${index + 1}: isEmpty=${isEmpty}, value="${select.value}"`);
        // All appraiser ratings are required when supervisor is approving
        if (isEmpty) {
        ratingsFilled = false;
          $select.addClass('is-invalid');
          // Add error message below the field
          if (!$select.next('.field-error-message').length) {
            $select.after('<div class="field-error-message text-danger small mt-1">Please select a rating</div>');
            console.log(`Added error message to appraiser rating ${index + 1}`);
          }
        } else {
          $select.removeClass('is-invalid');
          $select.next('.field-error-message').remove();
      }
    });
    
      console.log('Ratings filled:', ratingsFilled);
    if (!ratingsFilled) {
        errors.push('Please ensure all Appraiser\'s Rating fields are filled');
      }
    } else {
      console.log('Skipping appraiser rating validation (shouldValidate=false)');
    }
    
    // Validate that total weight equals 100%
    const weightInputs = document.querySelectorAll('input[name*="[weight]"]');
    let totalWeight = 0;
    let hasWeights = false;
    const weightRow = weightInputs.length > 0 ? $(weightInputs[0]).closest('tr') : null;
    
    weightInputs.forEach(function(input) {
      const weight = parseFloat(input.value) || 0;
      if (weight > 0) {
        totalWeight += weight;
        hasWeights = true;
      }
    });
    
    if (hasWeights && Math.abs(totalWeight - 100) > 0.01) {
      errors.push(`Total weight must equal 100%. Current total: ${totalWeight}%`);
      // Add error message below the weight column header or after the table
      if (weightRow && !$('#objectives-table-body').next('.field-error-message').length) {
        $('#objectives-table-body').after('<div class="field-error-message text-danger small mt-2 mb-2"><strong>Error:</strong> Total weight must equal 100%. Current total: ' + totalWeight + '%</div>');
      }
    } else {
      $('#objectives-table-body').next('.field-error-message').remove();
    }
    
    console.log('Midterm validation complete. Errors:', errors);
    return errors;
  }
  
  function validateCompetencies() {
    console.log('=== validateCompetencies() called ===');
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
          isRequired: $(this).attr('required') !== undefined || category !== 'leadership'
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
  $(document).on('submit', 'form[id^="approvalForm_midterm_"]', function(e) {
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
      
      // Run validation (do not use global $('.is-invalid') — other page UI may use that class)
      const errors = validateForm();
      console.log('Total validation errors found:', errors.length);
      console.log('Error details:', errors);
      
      if (errors.length > 0) {
        // Show validation errors
        if (errors.length > 0) {
          displayValidationSummary(errors);
        }
        show_notification('Please correct the validation errors before proceeding.', 'error');
        
        // Scroll to first error in this form / main PPA
        const firstError = $('#staff_ppa').find('.is-invalid').add('form[id^="approvalForm_midterm_"] .is-invalid').first();
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
  
  // Add real-time validation for competency radios
  $(document).on('change', '.competency-radio', function() {
    const name = $(this).attr('name');
    const category = $(this).data('category');
    const row = $(this).closest('tr');
    
    // If a radio is checked, remove error from that competency
    if ($(this).is(':checked') && category !== 'leadership') {
      row.removeClass('table-danger');
      row.next('.competency-error-message').remove();
      
      // Check if all competency errors are resolved
      if ($('.competency-error-message').length === 0) {
        $('.competency-validation-summary').remove();
      }
    }
  });
  
  function validateFieldInRealTime(field) {
    const fieldType = field.tagName.toLowerCase();
    const isRequired = fieldType === 'select' ? field.value === '' : field.value.trim() === '';
    
    // Remove existing validation classes and messages
    $(field).removeClass('is-valid is-invalid');
    $(field).next('.invalid-feedback, .field-error-message').remove();
    
    if (isRequired) {
      $(field).addClass('is-invalid');
      // Add validation message below the field
        const message = fieldType === 'select' ? 'Please select a rating' : 'This field is required';
      $(field).after(`<div class="field-error-message text-danger small mt-1">${message}</div>`);
    } else {
      $(field).addClass('is-valid');
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
    if (typeof window.syncMteSummernoteToTextareas === 'function') {
      window.syncMteSummernoteToTextareas();
    }

    // Create a temporary form data object
    const formData = new FormData(mainForm);
    
    // Add a flag to indicate this is a save operation
    formData.append('save_on_approval', '1');
    
    // Send AJAX request to save the form
    $.ajax({
      url: mainForm.action,
      type: 'POST',
      data: formData,
      dataType: 'text',
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