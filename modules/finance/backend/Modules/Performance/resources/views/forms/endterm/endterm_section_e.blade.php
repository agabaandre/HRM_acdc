<h4 class="mt-4">E. Personal Development Plan – Progress Review</h4>
@if ($entry)
<div class="card mb-4">
  <div class="card-header bg-light"><strong>Original PDP Training Plan</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-bordered mb-0">
      <tr><th style="width:35%">Recommended?</th><td>{{ $entry->training_recommended ?? 'No' }}</td></tr>
    </table>
  </div>
</div>
@endif
<div class="mb-3">
  <label class="form-label fw-semibold">Comments on PDP progress</label>
  <textarea wire:model="endtermTrainingReview" class="form-control" rows="6" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
</div>
<div class="mb-3">
  <select wire:model="endtermRecommendedSkills" class="form-select" multiple @disabled(str_contains($endreadonly ?? '', 'readonly'))>
    @foreach ($skills as $skill)
      <option value="{{ $skill->id }}">{{ $skill->skill }}</option>
    @endforeach
  </select>
</div>
<textarea wire:model="endtermTrainingContributions" class="form-control mb-3" rows="3" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
<textarea wire:model="endtermRecommendedTrainings" class="form-control mb-3" rows="3" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
<textarea wire:model="endtermRecommendedTrainingsDetails" class="form-control" rows="3" @disabled(str_contains($endreadonly ?? '', 'readonly'))></textarea>
