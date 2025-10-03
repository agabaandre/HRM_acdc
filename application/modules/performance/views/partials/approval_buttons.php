<?php
$status = ((intval(@$ppa_settings->allow_supervisor_return) === 1) && in_array('83', $permissions));
$isSupervisor = in_array($session->staff_id, [(int) @$ppa->midterm_supervisor_1, (int) @$ppa->midterm_supervisor_2]);

// $hasMidtermObjectives = false;
// if (!empty($ppa->midterm_objectives)) {
//   $decoded = is_string($ppa->midterm_objectives)
//     ? json_decode($ppa->midterm_objectives, true)
//     : (is_array($ppa->midterm_objectives) ? $ppa->midterm_objectives : []);
//   $hasMidtermObjectives = is_array($decoded) && count($decoded) > 0;
// }
// ?>

<?php echo form_open('performance/midterm/approve_ppa/' . $ppa->entry_id, [
  'method' => 'post',
  'id'     => 'approvalForm_midterm_' . $ppa->entry_id
]); ?>

<?php if ((intval($ppa_settings->allow_employee_comments) === 1) || $status): ?>
  <div class="mb-3">
    <label for="comments" class="form-label fw-semibold">Comments for Approval/Return</label>
    <textarea id="comments" name="comments" class="form-control" rows="3" required></textarea>
  </div>
<?php endif; ?>

<input type="hidden" name="action" id="approval_action_<?= $ppa->entry_id ?>" value="">

<div class="text-center">
  <?php if (
    $midterm_exists &&
    ((int)@$ppa->midterm_draft_status !== 2) &&
    $isSupervisor):
  ?>
    <button type="submit" class="btn btn-success px-5 me-2"
            onclick="document.getElementById('approval_action_<?= $ppa->entry_id ?>').value = 'approve';">
      Approve
    </button>
  <?php endif; ?>

  <?php if ($midterm_exists && $status): ?>
    <button type="button" class="btn btn-danger px-5" data-bs-toggle="modal"
            data-bs-target="#confirmReturnModal_midterm_<?= $ppa->entry_id ?>">
      Return
    </button>
  <?php endif; ?>
</div>

</form>

<!-- Return Confirmation Modal -->
<div class="modal fade" id="confirmReturnModal_midterm_<?= $ppa->entry_id ?>" tabindex="-1"
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
          Are you sure you want to return this Midterm Review for revision?
        </p>
        <p class="text-muted">
          Please include clear comments explaining the reason for return.
        </p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger px-4" onclick="submitReturnActionMidterm('<?= $ppa->entry_id ?>')">
          <i class="fas fa-reply me-1"></i> Yes, Return
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function submitReturnActionMidterm(entryId) {
  // Use the correct, unique ID for the hidden input and form
  var actionInput = document.getElementById('approval_action_' + entryId);
  var form = document.getElementById('approvalForm_midterm_' + entryId);
  if (actionInput && form) {
    actionInput.value = 'return';
    form.submit();
  }
}
</script>

