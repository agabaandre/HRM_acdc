@php
    $isRenew = $contractMode === 'renew';
    $title = $isRenew ? 'New / renew contract' : 'Edit contract #'.$editingContractId;
@endphp

<div class="card border-0 shadow-sm mt-3 border-primary">
    <div class="card-header bg-white fw-semibold">{{ $title }}</div>
    <form wire:submit="{{ $isRenew ? 'createContract' : 'saveContract' }}">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-muted small text-uppercase mb-2">Contract information</h6>
                    <div class="mb-2">
                        <label class="form-label small">Job <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.job_id" required>
                            <option value="">Select job</option>
                            @foreach ($lookups['jobs'] as $job)
                                <option value="{{ $job->job_id }}">{{ $job->job_name }}</option>
                            @endforeach
                        </select>
                        @error('contractForm.job_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Job acting</label>
                        <select class="form-select form-select-sm" wire:model="contractForm.job_acting_id">
                            <option value="">None</option>
                            @foreach ($lookups['jobsActing'] as $ja)
                                <option value="{{ $ja->job_acting_id }}">{{ $ja->job_acting }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Grade <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.grade_id" required>
                            <option value="">Select grade</option>
                            @foreach ($lookups['grades'] as $g)
                                <option value="{{ $g->grade_id }}">{{ $g->grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Contract type <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.contract_type_id" required>
                            <option value="">Select type</option>
                            @foreach ($lookups['contractTypes'] as $ct)
                                <option value="{{ $ct->contract_type_id }}">{{ $ct->contract_type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Contracting institution <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.contracting_institution_id" required>
                            <option value="">Select institution</option>
                            @foreach ($lookups['institutions'] as $inst)
                                <option value="{{ $inst->contracting_institution_id }}">{{ $inst->contracting_institution }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Funder <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.funder_id" required>
                            <option value="">Select funder</option>
                            @foreach ($lookups['funders'] as $f)
                                <option value="{{ $f->funder_id }}">{{ $f->funder }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6 class="text-muted small text-uppercase mb-2">Location &amp; supervisors</h6>
                    <div class="mb-2">
                        <label class="form-label small">Duty station <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.duty_station_id" required>
                            <option value="">Select station</option>
                            @foreach ($lookups['dutyStations'] as $ds)
                                <option value="{{ $ds->duty_station_id }}">{{ $ds->duty_station_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Division <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model.live="contractForm.division_id" required>
                            <option value="">Select division</option>
                            @foreach ($lookups['divisions'] as $div)
                                <option value="{{ $div->division_id }}">{{ $div->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (count($unitOptions) > 0)
                        <div class="mb-2">
                            <label class="form-label small">Unit</label>
                            <select class="form-select form-select-sm" wire:model="contractForm.unit_id">
                                <option value="">Select unit</option>
                                @foreach ($unitOptions as $unit)
                                    <option value="{{ $unit->unit_id }}">{{ $unit->unit_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-2">
                        <label class="form-label small">Other associated divisions</label>
                        <select class="form-select form-select-sm" wire:model="contractForm.other_associated_divisions" multiple size="4">
                            @foreach ($lookups['divisions'] as $div)
                                <option value="{{ $div->division_id }}">{{ $div->division_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple. Optional.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">First supervisor <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" wire:model="contractForm.first_supervisor" required>
                            <option value="">Select supervisor</option>
                            @foreach ($lookups['supervisors'] as $sup)
                                <option value="{{ $sup->staff_id }}">{{ trim($sup->lname.' '.$sup->fname) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Second supervisor</label>
                        <select class="form-select form-select-sm" wire:model="contractForm.second_supervisor">
                            <option value="">None</option>
                            @foreach ($lookups['supervisors'] as $sup)
                                <option value="{{ $sup->staff_id }}">{{ trim($sup->lname.' '.$sup->fname) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <h6 class="text-muted small text-uppercase mb-2">Dates &amp; status</h6>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Start date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" wire:model="contractForm.start_date" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">End date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" wire:model="contractForm.end_date" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Contract status <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" wire:model="contractForm.status_id" required>
                        @if ($isRenew)
                            @foreach ($renewNewStatuses as $st)
                                <option value="{{ $st->status_id }}">{{ $st->status }}</option>
                            @endforeach
                        @else
                            @foreach ($editStatuses as $st)
                                @php
                                    $allowed = in_array((int) $st->status_id, [1, 4, 7], true);
                                    $selected = (int) $st->status_id === (int) ($contractForm['status_id'] ?? 0);
                                @endphp
                                <option value="{{ $st->status_id }}" @if(!$allowed && !$selected) disabled @endif>{{ $st->status }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                @if ($isRenew)
                    <div class="col-md-3">
                        <label class="form-label small">Previous contract status <span class="text-danger">*</span></label>
                        @if ($previousContractStatus === 4)
                            <input type="hidden" wire:model="contractForm.previous_contract_status_id">
                            <input type="text" class="form-control form-control-sm" value="Separated" readonly>
                            <div class="form-text">Previous contract is separated and cannot be changed.</div>
                        @else
                            <select class="form-select form-select-sm" wire:model="contractForm.previous_contract_status_id" required>
                                <option value="">Select status</option>
                                @foreach ($renewPreviousStatuses as $st)
                                    <option value="{{ $st->status_id }}">{{ $st->status }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label small">Physical contract PDF <span class="text-muted">(optional)</span></label>
                    <input type="file" class="form-control form-control-sm" wire:model="contractPdf" accept="application/pdf">
                    @error('contractPdf')<div class="text-danger small">{{ $message }}</div>@enderror
                    @if (!empty($contractForm['file_name']))
                        <div class="form-text mt-1">
                            Current file:
                            @if (\App\Support\StaffContractFile::url($contractForm['file_name']))
                                <a href="{{ \App\Support\StaffContractFile::url($contractForm['file_name']) }}" target="_blank" rel="noopener">{{ $contractForm['file_name'] }}</a>
                            @else
                                {{ $contractForm['file_name'] }}
                            @endif
                        </div>
                    @endif
                    <div wire:loading wire:target="contractPdf" class="small text-muted">Uploading…</div>
                </div>

                <div class="col-12">
                    <label class="form-label small">Comments</label>
                    <textarea class="form-control form-control-sm" rows="3" wire:model="contractForm.comments"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button type="submit" class="btn btn-success btn-sm" wire:loading.attr="disabled">
                {{ $isRenew ? 'Save new contract' : 'Save contract' }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cancelContractForm">Cancel</button>
        </div>
    </form>
</div>
