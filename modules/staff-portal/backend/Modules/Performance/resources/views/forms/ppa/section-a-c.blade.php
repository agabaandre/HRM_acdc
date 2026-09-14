<h4>A. Staff Details</h4>
<table class="form-table table-bordered w-100">
  <tr>
    <td><b>Name</b></td>
    <td>{{ $contract->fname }} {{ $contract->lname }}</td>
    <td><b>SAP NO</b></td>
    <td>{{ $contract->SAPNO ?? '—' }}</td>
  </tr>
  <tr>
    <td><b>Position</b></td>
    <td>{{ $contract->job_name ?? '—' }}</td>
    <td><b>Initiation Date</b></td>
    <td>{{ $contract->initiation_date ?? '—' }}</td>
  </tr>
  <tr>
    <td><b>Division/Directorate</b></td>
    <td>{{ $contract->division_name ?? '—' }}</td>
    <td><b>Performance Period</b></td>
    <td>{{ $periodLabel }}</td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td>{{ $supervisors->staffName($supervisorId ?: $contract->first_supervisor ?? null) }}</td>
    <td><b>Second Supervisor</b></td>
    <td>{{ $supervisors->staffName($supervisor2Id ?: $contract->second_supervisor ?? null) }}</td>
  </tr>
  <tr>
    <td><b>Funder</b></td>
    <td>{{ $contract->funder ?? '—' }}</td>
    <td><b>Contract Type</b></td>
    <td>{{ $contract->contract_type ?? '—' }}</td>
  </tr>
</table>
<hr>
<h4>B. Performance Objectives</h4>
<small>Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two</small>
<div class="table-responsive">
  <table class="table objective-table table-bordered">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Objective</th>
        <th>Timeline</th>
        <th>Deliverables and KPI’s</th>
        <th>Weight</th>
      </tr>
    </thead>
    <tbody>
      @for ($i = 1; $i <= 5; $i++)
        @php $val = $objectives[$i] ?? []; $required = $i <= 3; @endphp
        <tr wire:key="ppa-obj-{{ $i }}">
          <td>{{ $i }}</td>
          <td><textarea wire:model="objectives.{{ $i }}.objective" class="form-control" rows="4" @disabled(str_contains($readonly, 'readonly')) @if($required) required @endif></textarea></td>
          <td>
            <input type="text" wire:model="objectives.{{ $i }}.timeline" class="form-control"
                   value="{{ $val['timeline'] ?: ($required ? $periodEndYear.'-12-31' : '') }}"
                   @disabled(str_contains($readonly, 'readonly')) @if($required) required @endif>
          </td>
          <td><textarea wire:model="objectives.{{ $i }}.indicator" class="form-control" rows="4" @disabled(str_contains($readonly, 'readonly')) @if($required) required @endif></textarea></td>
          <td><input type="number" wire:model="objectives.{{ $i }}.weight" class="form-control" @disabled(str_contains($readonly, 'readonly')) @if($required) required @endif></td>
        </tr>
      @endfor
    </tbody>
  </table>
</div>
<hr>
<h4>C. Personal Development Plan</h4>
<table class="form-table table-bordered w-100">
  <tr>
    <td style="width:30%"><label>Is training recommended for this staff member?</label></td>
    <td>
      <div class="form-check form-check-inline">
        <input type="radio" class="form-check-input" wire:model.live="trainingRecommended" value="Yes" id="training_yes" @disabled(str_contains($readonly, 'readonly'))>
        <label for="training_yes">Yes</label>
      </div>
      <div class="form-check form-check-inline">
        <input type="radio" class="form-check-input" wire:model.live="trainingRecommended" value="No" id="training_no" @disabled(str_contains($readonly, 'readonly'))>
        <label for="training_no">No</label>
      </div>
    </td>
  </tr>
</table>
@if ($trainingRecommended === 'Yes')
<section class="mt-3">
  <table class="form-table table-bordered w-100">
    <tr>
      <td style="width:30%"><label>Skill area(s) recommended</label></td>
      <td>
        <select wire:model="requiredSkills" class="form-select" multiple @disabled(str_contains($readonly, 'readonly'))>
          @foreach ($skills as $skill)
            <option value="{{ $skill->id }}">{{ $skill->skill }}</option>
          @endforeach
        </select>
      </td>
    </tr>
    <tr>
      <td><label>How training contributes to development</label></td>
      <td><textarea wire:model="trainingContributions" class="form-control" rows="3" @disabled(str_contains($readonly, 'readonly'))></textarea></td>
    </tr>
    <tr>
      <td><label>Recommended course(s) — AUC L&amp;D Catalogue</label></td>
      <td><textarea wire:model="recommendedTrainings" class="form-control" rows="3" @disabled(str_contains($readonly, 'readonly'))></textarea></td>
    </tr>
    <tr>
      <td><label>Other recommendable course(s)</label></td>
      <td><textarea wire:model="recommendedTrainingsDetails" class="form-control" rows="3" @disabled(str_contains($readonly, 'readonly'))></textarea></td>
    </tr>
  </table>
</section>
@endif
@if ($readonly === '' && ($isOwner ?? true) && ($canEmployeeSave ?? true))
  @if ((int) ($ppaSettings->allow_employee_comments ?? 0) === 1)
    <label class="mt-3">Comments for Approval</label>
    <textarea wire:model="comments" class="form-control mb-3" rows="3"></textarea>
  @endif
  <button type="button" wire:click="saveDraft" class="btn btn-warning px-5 me-2"><i class="fa-solid fa-floppy-disk me-1"></i> Save Draft</button>
  <button type="button" wire:click="saveSubmit" class="btn btn-success px-5"><i class="fa-solid fa-paper-plane me-1"></i> Submit</button>
@endif
