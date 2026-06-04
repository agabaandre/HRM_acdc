<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">Leave configuration</h4>
        <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-sm">Settings home</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab === 'policy') active @endif" wire:click="$set('tab', 'policy')">Accumulation &amp; policy rules</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab === 'types') active @endif" wire:click="$set('tab', 'types')">Leave types</button>
        </li>
    </ul>

    @if ($tab === 'policy')
        <form wire:submit="savePolicy" class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-semibold text-success">Annual leave accumulation</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Accrual per completed month (days)</label>
                        <input type="number" step="0.01" class="form-control" wire:model="policy.annual_accrual_per_month">
                        <small class="text-muted">Default: 2.33 working days / month</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Minimum days required per year</label>
                        <input type="number" class="form-control" wire:model="policy.annual_min_days_per_year">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Maximum carry-forward (days)</label>
                        <input type="number" class="form-control" wire:model="policy.annual_max_carry_forward">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Maximum per calendar year</label>
                        <input type="number" class="form-control" wire:model="policy.annual_max_per_calendar_year">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="policy.annual_prorate_mid_year_join" id="prorate">
                            <label class="form-check-label" for="prorate">Prorate for staff joining after January</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="policy.annual_forfeit_unused_minimum" id="forfeit">
                            <label class="form-check-label" for="forfeit">Forfeit if minimum annual days not taken</label>
                        </div>
                    </div>
                </div>

                <h5 class="fw-semibold text-success">Compensatory leave</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="policy.deduct_compensatory_first" id="compFirst">
                            <label class="form-check-label" for="compFirst">Deduct compensatory balance first when applying leave</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry (months) — weekend travel</label>
                        <input type="number" class="form-control" wire:model="policy.compensatory_weekend_travel_months">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry (months) — public holiday</label>
                        <input type="number" class="form-control" wire:model="policy.compensatory_public_holiday_months">
                    </div>
                </div>

                <h5 class="fw-semibold text-success">Sick leave</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Full pay (months / year)</label>
                        <input type="number" class="form-control" wire:model="policy.sick_full_pay_months">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Half pay (months / year)</label>
                        <input type="number" class="form-control" wire:model="policy.sick_half_pay_months">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Medical report after (days)</label>
                        <input type="number" class="form-control" wire:model="policy.sick_medical_report_after_days">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="policy.sick_medical_certificate_required" id="sickCert">
                            <label class="form-check-label" for="sickCert">Medical certificate required</label>
                        </div>
                    </div>
                </div>

                <h5 class="fw-semibold text-success">Maternity &amp; paternity</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Maternity (calendar days)</label>
                        <input type="number" class="form-control" wire:model="policy.maternity_calendar_days">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Maternity max instances</label>
                        <input type="number" class="form-control" wire:model="policy.maternity_max_instances">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Paternity (working days)</label>
                        <input type="number" class="form-control" wire:model="policy.paternity_working_days">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Paternity max periods</label>
                        <input type="number" class="form-control" wire:model="policy.paternity_max_periods">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-success" wire:loading.attr="disabled">Save policy rules</button>
            </div>
        </form>
    @endif

    @if ($tab === 'types')
        <div class="row">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">{{ $editingTypeId ? 'Edit leave type' : 'Add leave type' }}</div>
                    <div class="card-body">
                        <form wire:submit="saveType">
                            <div class="mb-2">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" wire:model="leave_name" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Code</label>
                                <input type="text" class="form-control" wire:model="code" placeholder="ANNUAL, SICK, …">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Entitlement days</label>
                                    <input type="number" class="form-control" wire:model="leave_days">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Accrual rate / month</label>
                                    <input type="number" step="0.01" class="form-control" wire:model="accrual_rate">
                                </div>
                            </div>
                            <div class="form-check my-2">
                                <input type="checkbox" class="form-check-input" wire:model="is_accrued" id="isAccrued">
                                <label class="form-check-label" for="isAccrued">Accrues monthly</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" wire:model="requires_hr_approval" id="hrAppr">
                                <label class="form-check-label" for="hrAppr">Requires Head of HR approval</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" wire:model="requires_medical_certificate" id="medCert">
                                <label class="form-check-label" for="medCert">Requires medical certificate</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" wire:model="deduct_compensatory_first" id="dedComp">
                                <label class="form-check-label" for="dedComp">Deduct compensatory first</label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Policy notes</label>
                                <textarea class="form-control" rows="2" wire:model="policy_notes"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm">Save type</button>
                                @if ($editingTypeId)
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetTypeForm">Cancel</button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Leave types</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Days</th>
                                    <th>Accrued</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($leaveTypes as $type)
                                    <tr>
                                        <td>{{ $type->leave_name }}</td>
                                        <td><code>{{ $type->code }}</code></td>
                                        <td>{{ $type->leave_days }}</td>
                                        <td>{{ $type->is_accrued ? $type->accrual_rate.'/mo' : '—' }}</td>
                                        <td><button type="button" class="btn btn-sm btn-outline-primary" wire:click="editType({{ $type->leave_id }})">Edit</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
