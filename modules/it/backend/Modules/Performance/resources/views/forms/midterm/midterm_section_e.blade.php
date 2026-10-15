<h4 class="mt-4">E. Personal Development Plan – Progress Review</h4>
@if ($entry)
<div class="card mb-4">
  <div class="card-header bg-light"><strong>Original PDP Training Plan</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-bordered mb-0">
      <tr><th style="width:35%">Recommended?</th><td>{{ $entry->training_recommended ?? 'No' }}</td></tr>
      <tr><th>Required Skills</th><td>
        @php $skillMap = collect($skills)->pluck('skill', 'id'); $ids = json_decode($entry->required_skills ?? '[]', true) ?: []; @endphp
        @forelse ($ids as $id)<div>{{ $skillMap[$id] ?? 'Unknown' }}</div>@empty<em>None listed</em>@endforelse
      </td></tr>
      <tr><th>Training Contributions</th><td>{!! $entry->training_contributions ?? '' !!}</td></tr>
      <tr><th>Recommended AUC Courses</th><td>{!! $entry->recommended_trainings ?? '' !!}</td></tr>
      <tr><th>Other Courses</th><td>{!! $entry->recommended_trainings_details ?? '' !!}</td></tr>
    </table>
  </div>
</div>
@endif
<div class="mb-3">
  <label class="form-label fw-semibold">1. Comments on progress made against employee's Personal Development Plan (PDP).</label>
  <textarea wire:model="midtermTrainingReview" class="form-control" rows="6" @disabled(str_contains($midreadonly, 'readonly'))></textarea>
</div>
<div class="mb-3">
  <label class="form-label fw-semibold">2. Additional training recommended — skill area(s)</label>
  <select wire:model="midtermRecommendedSkills" class="form-select" multiple @disabled(str_contains($midreadonly, 'readonly'))>
    @foreach ($skills as $skill)
      <option value="{{ $skill->id }}">{{ $skill->skill }}</option>
    @endforeach
  </select>
</div>
<div class="mb-3">
  <label class="form-label fw-semibold">How will training contribute to development?</label>
  <textarea wire:model="midtermTrainingContributions" class="form-control" rows="3" @disabled(str_contains($midreadonly, 'readonly'))></textarea>
</div>
<div class="mb-3">
  <label class="form-label fw-semibold">Recommended course(s) from AUC L&amp;D Catalogue</label>
  <textarea wire:model="midtermRecommendedTrainings" class="form-control" rows="3" @disabled(str_contains($midreadonly, 'readonly'))></textarea>
</div>
<div class="mb-3">
  <label class="form-label fw-semibold">Other recommendable course(s)</label>
  <textarea wire:model="midtermRecommendedTrainingsDetails" class="form-control" rows="3" @disabled(str_contains($midreadonly, 'readonly'))></textarea>
</div>
