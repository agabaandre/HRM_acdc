<h4 class="mt-4">F. Staff Submission / Sign Off</h4>

<table class="table table-borderless">
  <tr>
    <td colspan="4" class="text-center">

      <?php if (!$midreadonly): ?>
        <?php if (intval($ppa_settings->allow_employee_comments) === 1): ?>
          <br>
          <label class="fw-semibold">Comments for Submission</label>
          <textarea name="midterm_comments" class="form-control ppa-summernote" rows="3" placeholder="Enter your comments..."><?= $ppa->midterm_comments ?? '' ?></textarea>
          <br>
        <?php endif; ?>

        <?php
          $isOwner = @$ppa->staff_id == $session->staff_id;
          $isSupervisor = in_array($session->staff_id, [@$ppa->midterm_supervisor_1, @$ppa->midterm_supervisor_2]);

          $hasMidtermObjectives = false;
          if (!empty($ppa->midterm_objectives)) {
            $decoded = is_string($ppa->midterm_objectives)
              ? json_decode($ppa->midterm_objectives, true)
              : (is_array($ppa->midterm_objectives) ? $ppa->midterm_objectives : []);
            $hasMidtermObjectives = is_array($decoded) && count($decoded) > 0;
          }
        ?>

        <br>

        <?php if (!$hasMidtermObjectives || $isOwner): ?>
          <!-- Staff owns or creating -->
          <button type="submit" name="midterm_submit_action" value="draft" class="btn btn-warning px-5 me-2">
            <i class="fas fa-save me-1"></i> Save Draft
          </button>
          <button type="submit" name="midterm_submit_action" value="submit" class="btn btn-success px-5">
            <i class="fas fa-paper-plane me-1"></i> Submit
          </button>
        <?php endif; ?>

        <br><br>
      <?php endif; ?>

    </td>
  </tr>
</table>
