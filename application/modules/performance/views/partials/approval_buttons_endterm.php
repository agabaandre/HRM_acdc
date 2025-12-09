<?php
$status = ((intval(@$ppa_settings->allow_supervisor_return) === 1) && in_array('83', $permissions));

$isSupervisor = in_array($session->staff_id, [(int) @$ppa->endterm_supervisor_1, (int) @$ppa->endterm_supervisor_2]);
$isFirstSupervisor = ((int) $session->staff_id === (int) @$ppa->endterm_supervisor_1);
$isSecondSupervisor = ((int) $session->staff_id === (int) @$ppa->endterm_supervisor_2);
$isOwner = ((int) $session->staff_id === (int) @$ppa->staff_id);

// Check if first supervisor is the same as second supervisor, or if second supervisor is empty/0
// In these cases, first supervisor handles both approvals
$sameSupervisor = !empty($ppa->endterm_supervisor_1) && (
                  empty($ppa->endterm_supervisor_2) || 
                  (int)$ppa->endterm_supervisor_2 === 0 ||
                  ((int) $ppa->endterm_supervisor_1 === (int) $ppa->endterm_supervisor_2)
                );

// Check approval trail to determine current stage
$firstSupervisorApproved = false;
$staffConsented = !empty($ppa->endterm_staff_consent_at);
$secondSupervisorCanApprove = false;

// Check the most recent action by first supervisor (not just any approval)
// If they returned after approving, or if anyone returned after their approval, the approval is invalidated
if (!empty($approval_trail)) {
    $firstSupervisorActions = [];
    // Collect all actions by first supervisor with their IDs for sorting
    foreach ($approval_trail as $trail) {
        if ($trail->staff_id == $ppa->endterm_supervisor_1) {
            $firstSupervisorActions[] = [
                'id' => $trail->id ?? 0,
                'action' => $trail->action,
                'created_at' => $trail->created_at ?? ''
            ];
        }
    }
    // Sort by ID descending (most recent first) or by created_at if ID not available
    if (!empty($firstSupervisorActions)) {
        usort($firstSupervisorActions, function($a, $b) {
            if (isset($a['id']) && isset($b['id'])) {
                return $b['id'] - $a['id']; // Descending order
            }
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        // First supervisor has approved only if their most recent action is "Approved" (not "Returned")
        $firstSupervisorLastAction = $firstSupervisorActions[0]['action'];
        if ($firstSupervisorLastAction === 'Approved') {
            // Check if there's a "Returned" action (by anyone) after the first supervisor's approval
            $firstSupervisorApprovalId = $firstSupervisorActions[0]['id'] ?? 0;
            $hasReturnAfterApproval = false;
            
            foreach ($approval_trail as $trail) {
                // If there's a "Returned" action with a higher ID than the approval, it invalidates the approval
                if ($trail->action === 'Returned' && 
                    (isset($trail->id) && $trail->id > $firstSupervisorApprovalId)) {
                    $hasReturnAfterApproval = true;
                    break;
                }
            }
            
            // First supervisor is considered approved only if there's no return after their approval
            if (!$hasReturnAfterApproval) {
                $firstSupervisorApproved = true;
            }
        }
    }
}

// Second supervisor can approve only if first supervisor approved AND staff consented
// But NOT if first supervisor is the same as second supervisor (they already approved)
// Also NOT if second supervisor is empty/0 (no second supervisor exists)
if ($firstSupervisorApproved && $staffConsented && 
    !empty($ppa->endterm_supervisor_2) && 
    (int)$ppa->endterm_supervisor_2 !== 0 && 
    !$sameSupervisor) {
    $secondSupervisorCanApprove = true;
}

// Show staff consent form if first supervisor approved but staff hasn't consented
$showStaffConsent = $firstSupervisorApproved && !$staffConsented && $isOwner && $endterm_exists && ((int)@$ppa->endterm_draft_status !== 2);
?>

<?php if ($showStaffConsent): ?>
  <!-- Staff Consent Form -->
  <?php echo form_open('performance/endterm/staff_consent/' . $ppa->entry_id, [
    'method' => 'post',
    'id'     => 'staffConsentForm_endterm_' . $ppa->entry_id
  ]); ?>
  
  <!-- Always allow comments for endterm staff -->
  <div class="mb-3">
    <label for="comments_staff_consent_<?= $ppa->entry_id ?>" class="form-label fw-semibold">Comments</label>
    <textarea id="comments_staff_consent_<?= $ppa->entry_id ?>" name="comments" class="form-control" rows="3" placeholder="Enter your comments (optional)..."></textarea>
  </div>
  
  <div class="mb-3">
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="staff_discussion_confirmation_<?= $ppa->entry_id ?>" name="staff_discussion_confirmation" checked required>
      <label class="form-check-label" for="staff_discussion_confirmation_<?= $ppa->entry_id ?>">
        I hereby confirm that I formally discussed the results of this review with my supervisor
      </label>
    </div>
    
    <div class="form-check mb-2">
      <input class="form-check-input" type="radio" name="staff_rating_acceptance" id="accept_rating_<?= $ppa->entry_id ?>" value="1" checked required>
      <label class="form-check-label" for="accept_rating_<?= $ppa->entry_id ?>">
        I accept the overall rating assigned by my supervisor
      </label>
    </div>
    
    <div class="form-check mb-3">
      <input class="form-check-input" type="radio" name="staff_rating_acceptance" id="reject_rating_<?= $ppa->entry_id ?>" value="0" required>
      <label class="form-check-label" for="reject_rating_<?= $ppa->entry_id ?>">
        I reject the overall rating assigned by my supervisor
      </label>
    </div>
  </div>
  
  <div class="text-center">
    <button type="submit" class="btn btn-success px-5">
      Employee Consent
    </button>
  </div>
  
  <?php echo form_close(); ?>

<?php elseif ($isFirstSupervisor && $endterm_exists && ((int)@$ppa->endterm_draft_status !== 2) && !$firstSupervisorApproved && ((int)@$ppa->endterm_draft_status === 0 || (int)@$ppa->endterm_draft_status === 1)): ?>
  <!-- First Supervisor Approval Form -->
  <?php echo form_open('performance/endterm/approve_ppa/' . $ppa->entry_id, [
    'method' => 'post',
    'id'     => 'approvalForm_endterm_' . $ppa->entry_id
  ]); ?>

  <!-- Always allow comments for endterm supervisors -->
  <div class="mb-3">
    <label for="comments" class="form-label fw-semibold">Comments for Approval/Return</label>
    <textarea id="comments" name="comments" class="form-control" rows="3" required></textarea>
  </div>

  <div class="mb-3">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="discussion_confirmation_<?= $ppa->entry_id ?>" name="discussion_confirmation" checked required>
      <label class="form-check-label" for="discussion_confirmation_<?= $ppa->entry_id ?>">
        I hereby confirm that I formally discussed the results of this review with the staff member
      </label>
    </div>
  </div>

  <?php if ($sameSupervisor): ?>
    <!-- If first supervisor is the same as second supervisor, show agreement field -->
    <div class="mb-3">
      <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="supervisor2_agreement" id="agree_evaluation_first_<?= $ppa->entry_id ?>" value="1" checked required>
        <label class="form-check-label" for="agree_evaluation_first_<?= $ppa->entry_id ?>">
          I agree with the evaluation
        </label>
      </div>
      
      <div class="form-check mb-3">
        <input class="form-check-input" type="radio" name="supervisor2_agreement" id="disagree_evaluation_first_<?= $ppa->entry_id ?>" value="0" required>
        <label class="form-check-label" for="disagree_evaluation_first_<?= $ppa->entry_id ?>">
          I disagree with the evaluation
        </label>
      </div>
    </div>
  <?php endif; ?>

  <input type="hidden" name="action" id="approval_action_<?= $ppa->entry_id ?>" value="">

  <div class="text-center">
    <button type="submit" class="btn btn-success px-5 me-2"
            onclick="document.getElementById('approval_action_<?= $ppa->entry_id ?>').value = 'approve';">
      Approve
    </button>

    <?php if ($status && ((int)@$ppa->endterm_draft_status !== 1)): ?>
      <button type="button" class="btn btn-danger px-5" data-bs-toggle="modal"
              data-bs-target="#confirmReturnModal_endterm_<?= $ppa->entry_id ?>">
        Return
      </button>
    <?php endif; ?>
  </div>

  <?php echo form_close(); ?>

<?php elseif ($isSecondSupervisor && $secondSupervisorCanApprove): ?>
  <!-- Second Supervisor Approval Form -->
  <?php echo form_open('performance/endterm/approve_ppa/' . $ppa->entry_id, [
    'method' => 'post',
    'id'     => 'approvalForm_endterm_' . $ppa->entry_id
  ]); ?>

  <!-- Always allow comments for endterm supervisors -->
  <div class="mb-3">
    <label for="comments" class="form-label fw-semibold">Comments for Approval/Return</label>
    <textarea id="comments" name="comments" class="form-control" rows="3" required></textarea>
  </div>

  <div class="mb-3">
    <div class="form-check mb-2">
      <input class="form-check-input" type="radio" name="supervisor2_agreement" id="agree_evaluation_<?= $ppa->entry_id ?>" value="1" checked required>
      <label class="form-check-label" for="agree_evaluation_<?= $ppa->entry_id ?>">
        I agree with the evaluation of the supervisor
      </label>
    </div>
    
    <div class="form-check mb-3">
      <input class="form-check-input" type="radio" name="supervisor2_agreement" id="disagree_evaluation_<?= $ppa->entry_id ?>" value="0" required>
      <label class="form-check-label" for="disagree_evaluation_<?= $ppa->entry_id ?>">
        I disagree with the evaluation of the supervisor
      </label>
    </div>
  </div>

  <input type="hidden" name="action" id="approval_action_<?= $ppa->entry_id ?>" value="">

  <div class="text-center">
    <button type="submit" class="btn btn-success px-5 me-2"
            onclick="document.getElementById('approval_action_<?= $ppa->entry_id ?>').value = 'approve';">
      Approve
    </button>

    <?php if ($status && ((int)@$ppa->endterm_draft_status !== 1)): ?>
      <button type="button" class="btn btn-danger px-5" data-bs-toggle="modal"
              data-bs-target="#confirmReturnModal_endterm_<?= $ppa->entry_id ?>">
        Return
      </button>
    <?php endif; ?>
  </div>

  <?php echo form_close(); ?>

<?php endif; ?>

<!-- Always show Return button if user has return permissions, even if approval form isn't showing -->
<?php if ($status && ((int)@$ppa->endterm_draft_status !== 1)): ?>
  <?php 
  // Check if we already have a form (to avoid duplicate forms)
  $hasApprovalForm = ($isFirstSupervisor && $endterm_exists && ((int)@$ppa->endterm_draft_status !== 2) && !$firstSupervisorApproved) || ($isSecondSupervisor && $secondSupervisorCanApprove);
  
  if (!$hasApprovalForm): 
    // Create a form just for returning if no approval form exists
  ?>
    <?php echo form_open('performance/endterm/approve_ppa/' . $ppa->entry_id, [
      'method' => 'post',
      'id'     => 'returnForm_endterm_' . $ppa->entry_id
    ]); ?>
    
    <!-- Always allow comments for endterm, but return button is still guarded by config -->
    <?php if ($status): ?>
      <div class="mb-3">
        <label for="comments_return_<?= $ppa->entry_id ?>" class="form-label fw-semibold">Comments for Return</label>
        <textarea id="comments_return_<?= $ppa->entry_id ?>" name="comments" class="form-control" rows="3" required></textarea>
      </div>
    <?php endif; ?>
    
    <input type="hidden" name="action" id="return_action_<?= $ppa->entry_id ?>" value="return">
    
    <div class="text-center">
      <button type="button" class="btn btn-danger px-5" data-bs-toggle="modal"
              data-bs-target="#confirmReturnModal_endterm_<?= $ppa->entry_id ?>">
        Return
      </button>
    </div>
    
    <?php echo form_close(); ?>
  <?php endif; ?>
<?php endif; ?>

<!-- Return Confirmation Modal -->
<div class="modal fade" id="confirmReturnModal_endterm_<?= $ppa->entry_id ?>" tabindex="-1"
     aria-labelledby="confirmReturnModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-3">
      <div class="modal-header bg-warning text-dark border-0">
        <h5 class="modal-title d-flex align-items-center" id="confirmReturnModalLabel">
          <i class="fas fa-exclamation-triangle me-2 fs-4 text-danger"></i> Confirm Return
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p class="fs-5 fw-semibold mb-3">
          Are you sure you want to return this Endterm Review for revision?
        </p>
        <p class="text-muted">
          Please include clear comments explaining the reason for return.
        </p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger px-4" onclick="submitReturnActionEndterm('<?= $ppa->entry_id ?>')">
          <i class="fas fa-reply me-1"></i> Yes, Return
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function submitReturnActionEndterm(entryId) {
  // Try to find the approval form first
  var actionInput = document.getElementById('approval_action_' + entryId);
  var form = document.getElementById('approvalForm_endterm_' + entryId);
  
  // If approval form exists, use it
  if (actionInput && form) {
    actionInput.value = 'return';
    form.submit();
  } else {
    // Otherwise, try the return form
    var returnActionInput = document.getElementById('return_action_' + entryId);
    var returnForm = document.getElementById('returnForm_endterm_' + entryId);
    if (returnActionInput && returnForm) {
      returnActionInput.value = 'return';
      returnForm.submit();
    }
  }
}
</script>
