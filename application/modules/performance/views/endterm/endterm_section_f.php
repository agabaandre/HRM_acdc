<h4 class="mt-4">F. Staff Submission / Sign Off</h4>

<table class="table table-borderless">
  <tr>
    <td colspan="4" class="text-center">

      <?php if (!$endreadonly): ?>
        <!-- Always allow comments for endterm staff -->
        <br>
        <label class="fw-semibold">Comments for Submission</label>
        <textarea name="endterm_comments" class="form-control" rows="3" placeholder="Enter your comments..."><?= htmlspecialchars($ppa->endterm_comments ?? '') ?></textarea>
        <br>

        <?php
          $isOwner = @$ppa->staff_id == $session->staff_id;
          $isSupervisor = in_array($session->staff_id, [@$ppa->endterm_supervisor_1, @$ppa->endterm_supervisor_2]);

          $hasEndtermObjectives = false;
          if (!empty($ppa->endterm_objectives)) {
            $decoded = is_string($ppa->endterm_objectives)
              ? json_decode($ppa->endterm_objectives, true)
              : (is_array($ppa->endterm_objectives) ? $ppa->endterm_objectives : []);
            $hasEndtermObjectives = is_array($decoded) && count($decoded) > 0;
          }

          // Check if endterm was returned
          $isReturned = false;
          if (!empty($approval_trail) && is_array($approval_trail)) {
            $lastAction = reset($approval_trail);
            if ($lastAction && isset($lastAction->action) && $lastAction->action === 'Returned') {
              $isReturned = true;
            }
          }
        ?>

        <br>

        <?php if (!$hasEndtermObjectives || $isOwner): ?>
          <!-- Staff owns or creating -->
          <button type="submit" name="endterm_submit_action" value="draft" class="btn btn-warning px-5 me-2">
            Save Draft
          </button>
          <button type="submit" name="endterm_submit_action" value="submit" class="btn btn-success px-5">
            <?= $isReturned && $isOwner ? 'Resubmit' : 'Submit' ?>
          </button>
        <?php elseif ((int)@$ppa->endterm_draft_status !== 2 && $isSupervisor): ?>
          <!-- Supervisor editing before approval -->
          <button type="submit" name="endterm_submit_action" value="submit" class="btn btn-success px-5">
            Save Changes (If Any)
          </button>
        <?php endif; ?>

        <br><br>
      <?php endif; ?>

    </td>
  </tr>
</table>

<!-- Overall Rating Results Table -->
<?php
// Get endterm objectives for rating calculation
// First try endterm objectives, then midterm, then original PPA
$endterm_objectives = null;
if (!empty($ppa->endterm_objectives)) {
    $endterm_objectives = $ppa->endterm_objectives;
} elseif (!empty($ppa->midterm_objectives)) {
    // Fallback to midterm objectives if endterm objectives are empty
    $endterm_objectives = $ppa->midterm_objectives;
} elseif (!empty($ppa->objectives)) {
    // Fallback to original PPA objectives if both endterm and midterm are empty
    $endterm_objectives = $ppa->objectives;
}

// Calculate overall rating
$overall_rating = calculate_endterm_overall_rating($endterm_objectives);
?>

<?php if (!empty($endterm_objectives)): ?>
<div class="mt-4 mb-4">
  <h5 class="mb-3">Overall Performance Rating</h5>
  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead class="table-light">
        <tr>
          <th style="width: 30%;">Rating Category</th>
          <th style="width: 20%;">Score</th>
          <th style="width: 50%;">Description</th>
        </tr>
      </thead>
      <tbody>
        <tr class="<?= $overall_rating['category'] === 'outstanding' ? 'table-success' : ($overall_rating['category'] === 'satisfactory' ? 'table-info' : ($overall_rating['category'] === 'poor' ? 'table-danger' : 'table-secondary')) ?>">
          <td><strong><?= htmlspecialchars($overall_rating['label']) ?></strong></td>
          <td><strong><?= htmlspecialchars($overall_rating['score']) ?> / 100</strong></td>
          <td><?= htmlspecialchars($overall_rating['annotation']) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  
  <!-- Rating Scale Reference -->
  <div class="mt-3">
    <small class="text-muted">
      <strong>Rating Scale:</strong> 
      Outstanding Performance (80-100) | 
      Satisfactory Performance (51-79) | 
      Poor Performance (0-50) | 
      Not Rated – New in Position
    </small>
  </div>
</div>
<?php endif; ?>

