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
<button type="button" onclick="testNotification()" class="btn btn-warning btn-sm mb-3">Test Notification</button>

<?php if ($showApprovalBtns == 'show' || in_array('83', $permissions)) {
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
    const errors = validateCompetencies();
    console.log('Validation errors:', errors);
    
    if (errors.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Showing notification with errors');
      show_notification(errors.join('<br>'), 'error');
      return false;
    }
  });
  
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
    
    console.log('Final errors:', errors);
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
});
</script>