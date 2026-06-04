<div>
    <a href="{{ route('staff.index') }}" class="text-muted small text-decoration-none">&larr; Staff directory</a>

    <div class="card border-0 shadow-sm mt-2 mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    <x-staff::staff-avatar :fname="$person->fname" :lname="$person->lname" :photo="$person->photo ?? null" size="md" />
                </div>
                <div class="col">
                    <h3 class="mb-2 text-success">
                        {{ trim(($person->title ?? '').' '.($person->fname ?? '').' '.($person->lname ?? '').' '.($person->oname ?? '')) }}
                    </h3>
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        @if (!empty($person->SAPNO))
                            <span><i class="fa fa-id-card me-1"></i><strong>SAPNO:</strong> {{ $person->SAPNO }}</span>
                        @endif
                        @if (!empty($person->nationality))
                            <span><i class="fa fa-globe me-1"></i><strong>Nationality:</strong> {{ $person->nationality }}</span>
                        @endif
                        @if (!empty($person->work_email))
                            <span><i class="fa fa-envelope me-1"></i><a href="mailto:{{ $person->work_email }}">{{ $person->work_email }}</a></span>
                        @endif
                        @if (!empty($person->tel_1))
                            <span><i class="fa fa-phone me-1"></i>{{ $person->tel_1 }}@if(!empty($person->tel_2)) / {{ $person->tel_2 }}@endif</span>
                        @endif
                        @if (!empty($person->gender))
                            <span><i class="fa fa-user me-1"></i>{{ $person->gender }}</span>
                        @endif
                    </div>
                </div>
                @if ($canManageContracts)
                    <div class="col-md-3 text-md-end">
                        <button type="button" class="btn btn-dark btn-sm mb-2 d-block w-100" wire:click="startRenewContract">
                            <i class="fa fa-plus me-1"></i> New / renew contract
                        </button>
                        <span class="badge bg-secondary">{{ count($contracts) }} contract(s)</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab==='profile') active @endif" wire:click="$set('tab','profile')">Profile & contracts</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab==='leave') active @endif" wire:click="$set('tab','leave')">Leave balances</button>
        </li>
    </ul>

    @if ($tab === 'profile')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Biodata</div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-3"><strong>Date of birth</strong><br>{{ $person->date_of_birth ?? 'N/A' }} ({{ \App\Support\StaffPhoto::age($person->date_of_birth ?? null) }})</div>
                    <div class="col-md-3"><strong>Initiation date</strong><br>{{ $person->initiation_date ?? 'N/A' }}</div>
                    <div class="col-md-3"><strong>Tenure</strong><br>{{ \App\Support\StaffPhoto::yearsOfTenure($person->initiation_date ?? null) }}</div>
                    <div class="col-md-3"><strong>Region</strong><br>{{ $person->region_name ?? 'N/A' }}</div>
                    <div class="col-md-3"><strong>WhatsApp</strong><br>{{ $person->whatsapp ?? '—' }}</div>
                    <div class="col-md-3"><strong>Private email</strong><br>{{ $person->private_email ?? '—' }}</div>
                    <div class="col-md-6"><strong>Physical location</strong><br>{{ $person->physical_location ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="fa fa-file-contract me-2"></i>Contract history</span>
                <span class="badge bg-success">Total: {{ count($contracts) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0" style="font-size:0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Duty station</th>
                                <th>Division</th>
                                <th>Other divisions</th>
                                <th>Job</th>
                                <th>Acting</th>
                                <th>Grade</th>
                                <th>Type</th>
                                <th>Funder</th>
                                <th>Institution</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>PDF</th>
                                <th>1st sup.</th>
                                <th>2nd sup.</th>
                                <th>Comment</th>
                                @if ($canManageContracts)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contracts as $i => $c)
                                @php
                                    $pdfUrl = \App\Support\StaffContractFile::url($c->file_name ?? null);
                                @endphp
                                <tr @if($contractMode === 'edit' && $editingContractId === (int)$c->staff_contract_id) class="table-warning" @endif>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $c->duty_station_name ?? 'N/A' }}</td>
                                    <td>{{ $c->division_name ?? 'N/A' }}</td>
                                    <td class="small">{{ $c->other_divisions_label ?? '—' }}</td>
                                    <td>{{ $c->job_name ?? 'N/A' }}</td>
                                    <td>{{ $c->job_acting ?? '—' }}</td>
                                    <td>{{ $c->grade ?? '—' }}</td>
                                    <td>{{ $c->contract_type ?? '—' }}</td>
                                    <td>{{ $c->funder ?? '—' }}</td>
                                    <td>{{ $c->contracting_institution ?? '—' }}</td>
                                    <td>{{ $c->start_date ?? 'N/A' }}</td>
                                    <td>{{ $c->end_date ?? 'N/A' }}</td>
                                    <td><span class="badge bg-secondary">{{ $c->status_label ?? $c->status_id }}</span></td>
                                    <td>
                                        @if ($pdfUrl)
                                            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary py-0 px-1" title="View contract PDF"><i class="fa fa-file-pdf"></i></a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ trim($c->first_supervisor_name ?? '') ?: '—' }}</td>
                                    <td>{{ trim($c->second_supervisor_name ?? '') ?: '—' }}</td>
                                    <td class="small">{{ \Illuminate\Support\Str::limit($c->comments ?? '', 30) ?: '—' }}</td>
                                    @if ($canManageContracts)
                                        <td class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editContract({{ $c->staff_contract_id }})">Edit</button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageContracts ? 18 : 17 }}" class="text-center text-muted py-3">No contracts on file.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($canManageContracts && $contractMode !== '')
            @include('staff::livewire.partials.contract-form')
        @endif
    @else
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Current leave balances ({{ $balanceYear }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Leave type</th>
                                    <th>Available</th>
                                    <th>Opening</th>
                                    <th>CF</th>
                                    <th>Accrued</th>
                                    <th>Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($balanceRows as $row)
                                    <tr>
                                        <td>{{ $row['type']->leave_name }}</td>
                                        <td class="fw-semibold">{{ $row['balance']['available'] }}</td>
                                        <td>{{ $row['balance']['opening'] }}</td>
                                        <td>{{ $row['balance']['carried_forward'] }}</td>
                                        <td>{{ $row['balance']['accrued'] }}</td>
                                        <td>{{ $row['balance']['used'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @if ($isHr)
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Opening balances (HR)</span>
                            <input type="number" class="form-control form-control-sm w-auto" wire:model.live="balanceYear" min="2020" max="2100">
                        </div>
                        <form wire:submit="saveOpeningBalances">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr><th>Type</th><th>Opening</th><th>CF</th><th>Comp.</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($leaveTypes as $type)
                                                @php $lid = (int) $type->leave_id; @endphp
                                                <tr>
                                                    <td class="small">{{ $type->leave_name }}</td>
                                                    <td><input type="number" step="0.5" class="form-control form-control-sm" wire:model="openingRows.{{ $lid }}.opening_days"></td>
                                                    <td><input type="number" step="0.5" class="form-control form-control-sm" wire:model="openingRows.{{ $lid }}.carried_forward_days"></td>
                                                    <td><input type="number" step="0.5" class="form-control form-control-sm" wire:model="openingRows.{{ $lid }}.compensatory_days"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <button type="submit" class="btn btn-success btn-sm" wire:loading.attr="disabled">Save opening balances</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="modal fade" id="staffPhotoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Passport photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="staffPhotoModalImg" src="" alt="Staff photo" class="img-fluid rounded" style="max-height:75vh;">
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.addEventListener('staff-photo-zoom', function (e) {
        var img = document.getElementById('staffPhotoModalImg');
        var modalEl = document.getElementById('staffPhotoModal');
        if (!img || !modalEl || !e.detail?.url) return;
        img.src = e.detail.url;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
</script>
@endpush
