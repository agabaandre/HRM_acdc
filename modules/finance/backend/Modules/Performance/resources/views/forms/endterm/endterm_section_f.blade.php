<h4 class="mt-4">F. Staff Submission / Sign Off</h4>
@if (($endreadonly ?? '') === '' && ($canEmployeeSave ?? true))
  @if ((int) ($ppaSettings->allow_employee_comments ?? 0) === 1)
    <textarea wire:model="endtermComments" class="form-control mb-3" rows="3"></textarea>
  @endif
  @if ($isOwner ?? true)
    <button type="button" wire:click="saveDraft" class="btn btn-warning px-5 me-2">Save Draft</button>
    <button type="button" wire:click="saveSubmit" class="btn btn-success px-5">Submit</button>
  @endif
@endif
