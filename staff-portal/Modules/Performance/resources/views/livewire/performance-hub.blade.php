<div>
    <div class="card border-0 shadow-sm mb-4" style="background: #119A48;">
        <div class="card-body text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="fw-bold mb-0">
                <i class="{{ \App\Support\CbpIcon::classes('fa-line-chart') }} me-2"></i>Performance
            </h2>
            @if ($ppaSubmissionOpen ?? true)
                <a href="{{ $performance->createPpaUrl() }}" class="btn btn-light btn-sm">
                    <i class="{{ \App\Support\CbpIcon::classes('fa-circle-plus') }} me-1"></i> Create PPA
                </a>
            @else
                <span class="btn btn-light btn-sm disabled" title="PPA submission window is closed">Create PPA (closed)</span>
            @endif
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <a href="{{ route('performance.ppa-dashboard') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-2">
                    <i class="{{ \App\Support\CbpIcon::classes(\Modules\Performance\Enums\PerformancePhase::Ppa->icon(), 'text-success fs-4') }}"></i>
                    <span class="text-dark fw-semibold">PPA dashboard</span>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('performance.my-ppas') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-2">
                    <i class="{{ \App\Support\CbpIcon::classes('fa-folder-open', 'text-success fs-4') }}"></i>
                    <span class="text-dark fw-semibold">My PPAs</span>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('performance.pending') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-2">
                    <i class="{{ \App\Support\CbpIcon::classes('fa-clock', 'text-success fs-4') }}"></i>
                    <span class="text-dark fw-semibold">Pending action
                        @if ($pendingCount > 0)<span class="badge bg-danger">{{ $pendingCount }}</span>@endif
                    </span>
                </div>
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link @if($tab === 'dashboard') active @endif" href="{{ route('performance.ppa-dashboard', ['period' => $period, 'division' => $division]) }}">
                <i class="{{ \App\Support\CbpIcon::classes(\Modules\Performance\Enums\PerformancePhase::Ppa->icon()) }} me-1"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if($tab === 'my') active @endif" href="{{ route('performance.my-ppas', ['period' => $period]) }}">
                <i class="{{ \App\Support\CbpIcon::classes('fa-user') }} me-1"></i> My PPAs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if($tab === 'pending') active @endif" href="{{ route('performance.pending') }}">
                <i class="{{ \App\Support\CbpIcon::classes('fa-inbox') }} me-1"></i> Pending
                @if ($pendingCount > 0)<span class="badge bg-danger">{{ $pendingCount }}</span>@endif
            </a>
        </li>
    </ul>

    @if ($tab === 'dashboard')
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Performance period</label>
                <select class="form-select form-select-sm" wire:model.live="period">
                    <option value="{{ $performance->currentPeriodSlug() }}">Current ({{ $performance->currentPeriodLabel() }})</option>
                    @foreach ($periods as $p)
                        @if ($p !== $performance->currentPeriodSlug())
                            <option value="{{ $p }}">{{ str_replace('-', ' ', $p) }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Division</label>
                <select class="form-select form-select-sm" wire:model.live="division">
                    <option value="">All divisions</option>
                    @foreach ($divisions as $d)
                        <option value="{{ $d->division_id }}">{{ $d->division_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Submitted PPAs', 'value' => $summary['total'], 'icon' => 'fa-file-lines', 'class' => 'text-success'],
                ['label' => 'Approved', 'value' => $summary['approved'], 'icon' => 'fa-circle-check', 'class' => ''],
                ['label' => 'Awaiting approval', 'value' => $summary['submitted'], 'icon' => 'fa-hourglass-half', 'class' => 'text-warning'],
                ['label' => 'Without PPA', 'value' => $summary['without_ppa'], 'icon' => 'fa-user-xmark', 'class' => 'text-danger'],
            ] as $card)
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">
                                <i class="{{ \App\Support\CbpIcon::classes($card['icon']) }} me-1"></i>{{ $card['label'] }}
                            </div>
                            <div class="fs-3 fw-bold {{ $card['class'] }}">{{ number_format($card['value']) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-0">
            <div class="col-lg-6">
                <div class="text-muted small">
                    <strong>Configured workflows</strong>
                    <a href="{{ route('settings.performance') }}" class="ms-1">(manage in Settings)</a>
                    <ul class="mb-0 mt-1">
                        <li><strong>PPA:</strong> {{ $workflowSummary['ppa'] ?? '' }}</li>
                        <li><strong>Midterm:</strong> {{ $workflowSummary['midterm'] ?? '' }}</li>
                        <li><strong>End-of-year:</strong> {{ $workflowSummary['endterm'] ?? '' }}</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-muted small">
                    <strong>Submission windows (this month)</strong>
                    <ul class="mb-0 mt-1 list-unstyled">
                        @foreach (['ppa' => 'PPA', 'midterm' => 'Midterm', 'endterm' => 'End-of-year'] as $key => $label)
                            @php $w = $submissionWindows[$key] ?? null; @endphp
                            <li class="mb-1">
                                <span class="badge {{ ($w['open'] ?? true) ? 'bg-success' : 'bg-secondary' }} me-1">{{ ($w['open'] ?? true) ? 'Open' : 'Closed' }}</span>
                                <strong>{{ $label }}:</strong> {{ $w['label'] ?? '—' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if ($tab === 'my')
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">Filter by period</label>
                <select class="form-select form-select-sm" wire:model.live="period">
                    <option value="">All periods</option>
                    @foreach ($periods as $p)
                        <option value="{{ $p }}">{{ str_replace('-', ' ', $p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <x-core::filter-per-page col="col-12" />
            </div>
        </div>

        @if ($myPpas)
            <x-core::data-table :paginator="$myPpas" :from="$myFrom" :to="$myTo" :total="$myTotal" :compact="true">
                <x-slot:head>
                    <tr>
                        <th>#</th>
                        <th>Period</th>
                        <th>PPA</th>
                        <th>Midterm</th>
                        <th>Endterm</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                <x-slot:body>
                    @forelse ($myPpas as $index => $p)
                        <tr wire:key="my-ppa-{{ $p->entry_id }}">
                            <td>{{ $myFrom + $index }}</td>
                            <td>{{ str_replace('-', ' ', $p->performance_period) }}</td>
                            <td>{{ $performance->draftStatusLabel((int) $p->draft_status) }}</td>
                            <td>{{ $performance->midtermStatusLabel(isset($p->midterm_draft_status) ? (int) $p->midterm_draft_status : null) }}</td>
                            <td>{{ $performance->midtermStatusLabel(isset($p->endterm_draft_status) ? (int) $p->endterm_draft_status : null) }}</td>
                            <td class="text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ $performance->reviewRoute(\Modules\Performance\Enums\PerformancePhase::Ppa, $p->entry_id, (int) $p->staff_id) }}" class="btn btn-outline-success" title="PPA">PPA</a>
                                    @if ($p->midterm_created_at)
                                        <a href="{{ $performance->reviewRoute(\Modules\Performance\Enums\PerformancePhase::Midterm, $p->entry_id, (int) $p->staff_id) }}" class="btn btn-outline-warning" title="Midterm">M</a>
                                    @endif
                                    @if ($p->endterm_created_at)
                                        <a href="{{ $performance->reviewRoute(\Modules\Performance\Enums\PerformancePhase::Endterm, $p->entry_id, (int) $p->staff_id) }}" class="btn btn-outline-info" title="Endterm">E</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-3">
                            No PPAs yet.
                            @if ($ppaSubmissionOpen ?? true)
                                <a href="{{ $performance->createPpaUrl() }}">Create PPA</a>
                            @else
                                PPA submissions are closed for this month.
                            @endif
                        </td></tr>
                    @endforelse
                </x-slot:body>
            </x-core::data-table>
        @endif
    @endif

    @if ($tab === 'pending')
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <th>Period</th>
                        <th>Phase</th>
                        <th>Workflow step</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $row)
                        @php
                            $phase = \Modules\Performance\Enums\PerformancePhase::from($row->approval_type ?? 'ppa');
                        @endphp
                        <tr wire:key="pending-{{ $row->entry_id }}-{{ $phase->value }}">
                            <td>{{ $row->staff_name ?? '—' }}</td>
                            <td>{{ str_replace('-', ' ', $row->performance_period ?? '') }}</td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <i class="{{ \App\Support\CbpIcon::classes($phase->icon()) }} me-1"></i>{{ $phase->label() }}
                                </span>
                            </td>
                            <td>{{ $row->overall_status ?? 'Pending' }}</td>
                            <td>
                                <a href="{{ $performance->reviewRoute($phase, $row->entry_id, (int) $row->staff_id) }}" class="btn btn-sm btn-success">
                                    <i class="{{ \App\Support\CbpIcon::classes('fa-eye') }}"></i> Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-4">No pending workflow actions for you.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
