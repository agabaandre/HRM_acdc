<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="{{ route('performance.pending') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            <h4 class="text-success fw-bold mb-1">
                <i class="{{ \App\Support\CbpIcon::classes($phase->icon()) }} me-2"></i>
                {{ $phase->label() }} — {{ $periodLabel }}
            </h4>
            <p class="text-muted small mb-0">Staff #{{ $entry->staff_id }} · Entry {{ $entry->entry_id }}</p>
        </div>
        <a href="{{ $performance->reviewRoute($phase, $entry->entry_id, (int) $entry->staff_id) }}"
           class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-pen-to-square"></i> Open {{ $phase->label() }} form
        </a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Workflow</div>
                <div class="card-body">
                    <x-performance::workflow-timeline :steps="$timeline" />
                    <p class="small text-muted mb-0 mt-2">
                        Supervisors are taken from the latest contract when this phase is in progress.
                        Completed phases and approval history are not changed.
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Supervisors (this phase)</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>First</span>
                        <span>{{ $supervisors['supervisor_1'] ? app(\Modules\Performance\Services\SupervisorResolver::class)->staffName($supervisors['supervisor_1']) : '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Second</span>
                        <span>{{ $supervisors['supervisor_2'] ? app(\Modules\Performance\Services\SupervisorResolver::class)->staffName($supervisors['supervisor_2']) : '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between text-muted">
                        <span>Latest contract</span>
                        <span class="text-end">{{ $contractSup['supervisor_1_name'] }} / {{ $contractSup['supervisor_2_name'] }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Status</span>
                    <span class="badge bg-primary">{{ $state['label'] }}</span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">PPA</dt>
                        <dd class="col-sm-8">{{ $performance->draftStatusLabel((int) $entry->draft_status) }}</dd>
                        <dt class="col-sm-4">Midterm</dt>
                        <dd class="col-sm-8">
                            @if ($entry->midterm_created_at)
                                {{ $performance->midtermStatusLabel((int) ($entry->midterm_draft_status ?? 1)) }}
                            @else
                                Not started
                            @endif
                        </dd>
                        <dt class="col-sm-4">Endterm</dt>
                        <dd class="col-sm-8">
                            @if ($entry->endterm_created_at)
                                {{ $performance->midtermStatusLabel((int) ($entry->endterm_draft_status ?? 1)) }}
                            @else
                                Not started
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if ($canAct)
                <div class="card border-0 shadow-sm mb-3 border-start border-4 border-success">
                    <div class="card-header bg-white fw-semibold">Your action</div>
                    <div class="card-body">
                        @if ($state['step'] === 'employee_consent' && $isOwner)
                            <div class="mb-3">
                                <label class="form-label">Comments</label>
                                <textarea class="form-control" rows="3" wire:model="comments"></textarea>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" wire:model.live="acceptRating" value="1" id="acceptRating">
                                <label class="form-check-label" for="acceptRating">I accept the overall rating</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" wire:model.live="acceptRating" value="0" id="rejectRating">
                                <label class="form-check-label" for="rejectRating">I reject the overall rating</label>
                            </div>
                            <button type="button" class="btn btn-success" wire:click="submitConsent">
                                <i class="fa-solid fa-signature"></i> Submit consent
                            </button>
                        @else
                            <div class="mb-3">
                                <label class="form-label">Comments</label>
                                <textarea class="form-control" rows="3" wire:model="comments" placeholder="Required for return"></textarea>
                            </div>
                            @if ($state['step'] === 'supervisor_2' && $phase === \Modules\Performance\Enums\PerformancePhase::Endterm)
                                <div class="mb-3">
                                    <label class="form-label">Agreement with evaluation</label>
                                    <select class="form-select" wire:model.live="supervisor2Agreement">
                                        <option value="1">Agree</option>
                                        <option value="0">Disagree</option>
                                    </select>
                                </div>
                            @endif
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success" wire:click="submitApprove">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn btn-outline-danger" wire:click="submitReturn">
                                    <i class="fa-solid fa-reply"></i> Return
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Approval trail (immutable)</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>When</th><th>By</th><th>Action</th><th>Comments</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($trail as $row)
                                <tr>
                                    <td class="text-nowrap small">{{ $row->created_at }}</td>
                                    <td class="small">{{ app(\Modules\Performance\Services\SupervisorResolver::class)->staffName((int) $row->staff_id) }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $row->action }}</span></td>
                                    <td class="small">{{ $row->comments ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No trail entries yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
