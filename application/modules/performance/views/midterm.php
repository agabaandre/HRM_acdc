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
    $isSupervisor = in_array($session->staff_id, [(int) @$ppa->supervisor_id, (int) @$ppa->supervisor2_id]);

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
            (int) @$midppa->supervisor_id,
            (int) @$midppa->supervisor2_id
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



// ✅ FIXED: define before usage
$showApprovalBtns = show_midterm_approval_action(@$ppa, @$approval_trail, $session);

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
<?php $this->load->view('performance/midterm/midterm_section_b', compact('objectives', 'readonly','midreadonly')); ?>
<hr>

<!-- SECTION C: APPRAISER'S COMMENTS -->
<?php $this->load->view('performance/midterm/midterm_section_c', compact('ppa', 'readonly','midreadonly')); ?>
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

<?php if ($showApprovalBtns === 'show' || in_array('83', $permissions)) {
    $this->load->view('performance/partials/approval_buttons', compact('ppa', 'ppa_settings', 'session', 'approval_trail','midreadonly'));
} ?>

<!-- Approval Trail -->
<?php $this->load->view('performance/partials/approval_trail', compact('ppa','session', 'approval_trail','midreadonly')); ?>

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
