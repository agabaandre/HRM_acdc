<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-success fw-bold mb-0">Performance &amp; workflows</h4>
        <a href="{{ route('settings.hub') }}" class="btn btn-outline-secondary btn-sm">Settings home</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab === 'workflow') active @endif" wire:click="$set('tab', 'workflow')">Approval workflows</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link @if($tab === 'general') active @endif" wire:click="$set('tab', 'general')">General &amp; deadlines</button>
        </li>
    </ul>

    <form wire:submit="save">
        @if ($tab === 'workflow')
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold">PPA (performance plan)</div>
                        <div class="card-body">
                            <p class="text-muted small">After the employee submits, supervisors approve in order. By default only the <strong>first supervisor</strong> must approve.</p>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="ppa_s2" wire:model.live="ppa_requires_second_supervisor">
                                <label class="form-check-label" for="ppa_s2">Require second supervisor approval</label>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold">Midterm review</div>
                        <div class="card-body">
                            <p class="text-muted small">Same approval pattern as PPA unless you enable a second supervisor below.</p>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="mt_s2" wire:model.live="midterm_requires_second_supervisor">
                                <label class="form-check-label" for="mt_s2">Require second supervisor approval</label>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold">End-of-year review</div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="et_consent" wire:model.live="endterm_requires_employee_consent">
                                <label class="form-check-label" for="et_consent">Require employee consent after first supervisor approval (staff agrees with their rating/results)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="et_s2" wire:model.live="endterm_requires_second_supervisor">
                                <label class="form-check-label" for="et_s2">Require second supervisor approval</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm bg-light">
                        <div class="card-body">
                            <h6 class="fw-semibold text-success">Live preview</h6>
                            @foreach (['ppa' => 'PPA', 'midterm' => 'Midterm', 'endterm' => 'End-of-year'] as $key => $title)
                                <p class="small fw-semibold mb-1 mt-3">{{ $title }}</p>
                                <ol class="small mb-0 ps-3">
                                    @foreach ($workflowPreview[$key] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($tab === 'general')
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Form behaviour</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="allow_supervisor_return" id="ret">
                            <label class="form-check-label" for="ret">Allow supervisors to return for revision</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="allow_supervisor_ppa_edit" id="edit">
                            <label class="form-check-label" for="edit">Allow supervisor PPA edits</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="allow_supervisor_comments" id="scom">
                            <label class="form-check-label" for="scom">Allow supervisor comments on forms</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="allow_employee_comments" id="ecom">
                            <label class="form-check-label" for="ecom">Allow employee comments on submit</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Submission windows (by calendar month)</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Choose the <strong>months</strong> when staff may create or submit each phase (applies every year).
                        Leave both empty for no restriction. Current month: <strong>{{ $currentMonthLabel }}</strong>.
                    </p>
                    @php
                        $windows = [
                            'ppa' => ['title' => 'PPA', 'start' => 'ppa_start', 'end' => 'ppa_deadline'],
                            'midterm' => ['title' => 'Midterm', 'start' => 'mid_term_start', 'end' => 'mid_term_deadline'],
                            'endterm' => ['title' => 'End-of-year', 'start' => 'end_term_start', 'end' => 'end_term_deadline'],
                        ];
                    @endphp
                    @foreach ($windows as $key => $win)
                        @php $status = $windowStatuses[$key] ?? null; @endphp
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <span class="fw-semibold">{{ $win['title'] }}</span>
                                @if ($status)
                                    <span class="badge {{ $status['open'] ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $status['open'] ? 'Open now' : 'Closed now' }}
                                    </span>
                                @endif
                            </div>
                            @if ($status)
                                <p class="small text-muted mb-2">{{ $status['message'] }}</p>
                            @endif
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small">First month (open)</label>
                                    <select class="form-select form-select-sm" wire:model.live="{{ $win['start'] }}">
                                        <option value="">— Any —</option>
                                        @foreach ($monthOptions as $num => $name)
                                            <option value="{{ $num }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Last month (close)</label>
                                    <select class="form-select form-select-sm" wire:model.live="{{ $win['end'] }}">
                                        <option value="">— Any —</option>
                                        @foreach ($monthOptions as $num => $name)
                                            <option value="{{ $num }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-4">
            <button type="submit" class="btn btn-success px-4">Save settings</button>
        </div>
    </form>
</div>
