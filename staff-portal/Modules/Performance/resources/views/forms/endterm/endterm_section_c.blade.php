<h4 class="mt-4">C. Appraiser’s Comments</h4>
<div class="mb-3">
  <label class="form-label fw-semibold">1. What has been achieved in relation to the Performance Objectives?</label>
  <textarea wire:model="endtermAchievements" class="form-control" rows="4" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
</div>
<div class="mb-3">
  <label class="form-label fw-semibold">2. Specify non-achievements in relation to Performance Objectives</label>
  <textarea wire:model="endtermNonAchievements" class="form-control" rows="4" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
</div>
