<h4 class="mt-4">F. Staff Submission / Sign Off</h4>
@if ($midreadonly === '' && ($canEmployeeSave ?? true))
  @if ((int) ($ppaSettings->allow_employee_comments ?? 0) === 1)
    <label class="fw-semibold">Comments for Submission</label>
    <textarea wire:model="midtermComments" class="form-control mb-3" rows="3" placeholder="Enter your comments..."></textarea>
  @endif
  @if ($isOwner ?? true)
    <button type="button" wire:click="saveDraft" class="btn btn-warning px-5 me-2"><i class="fa-solid fa-floppy-disk me-1"></i> Save Draft</button>
    <button type="button" wire:click="saveSubmit" class="btn btn-success px-5"><i class="fa-solid fa-paper-plane me-1"></i> Submit</button>
  @endif
@endif
