<h4 class="mt-4">C. Appraiser's Comments</h4>

<div class="mb-3">
  <label for="endterm_achievements" class="form-label fw-semibold">
    1. What has been achieved in relation to the Performance Objectives?
    <?php if($isSupervisor && !$endreadonly): ?>
      <span class="text-danger">*</span>
    <?php endif; ?>
  </label>
  <textarea name="endterm_achievements" id="endterm_achievements" class="form-control endterm-achievements-field" rows="4" <?= $endreadonly ?>  <?php if(!$isSupervisor){echo 'readonly';}?>><?= htmlspecialchars(trim($ppa->endterm_achievements ?? '')) ?></textarea>
  <div class="field-error-message text-danger small mt-1" style="display: none;"></div>
</div>

<div class="mb-3">
  <label for="endterm_non_achievements" class="form-label fw-semibold">
    2. Specify non-achievements in relation to Performance Objectives
  </label>
  <textarea name="endterm_non_achievements" id="endterm_non_achievements" class="form-control" rows="4" <?= $endreadonly ?> <?php if(!$isSupervisor){echo 'readonly';}?>><?= htmlspecialchars(trim($ppa->endterm_non_achievements ?? '')) ?></textarea>
</div>

