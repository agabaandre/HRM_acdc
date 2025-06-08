<h4 class="mt-4">C. Appraiser’s Comments</h4>

<div class="mb-3">
  <label for="midterm_achievements" class="form-label fw-semibold">
    1. What has been achieved in relation to the Performance Objectives?
  </label>
  <textarea name="midterm_achievements" id="midterm_achievements" class="form-control" rows="4" <?= $midreadonly ?>>
    <?= htmlspecialchars($ppa->midterm_achievements ?? '') ?>
  </textarea>
</div>

<div class="mb-3">
  <label for="midterm_non_achievements" class="form-label fw-semibold">
    2. Specify non-achievements in relation to Performance Objectives
  </label>
  <textarea name="midterm_non_achievements" id="midterm_non_achievements" class="form-control" rows="4" <?= $midreadonly ?>>
    <?= htmlspecialchars($ppa->midterm_non_achievements ?? '') ?>
  </textarea>
</div>
