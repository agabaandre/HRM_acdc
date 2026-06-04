<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $phase->label() }} — {{ $supervisors->staffName($staffId) }}</h4>
        <a href="{{ route('performance.index') }}" class="btn btn-outline-secondary btn-sm">Back to hub</a>
    </div>

    @include('performance::partials.ppa-tabs', [
        'staffId' => $staffId,
        'pendingCount' => $pendingCount,
        'submissionWindows' => app(\Modules\Performance\Services\PpaSettingsService::class)->allSubmissionWindowStatuses(),
    ])

    <x-performance::submission-window-alert :status="$submissionWindow" :phase-label="$phase->label()" class="mb-3" />

    @if ($contractMissing)
        <div class="alert alert-warning">No staff contract on file. Contact HR before submitting.</div>
    @endif

    @if ($entry && $timeline)
        <x-performance::workflow-timeline :steps="$timeline" class="mb-4" />
    @endif

    <form wire:submit.prevent>
        <input type="hidden" wire:model="staffId">
        <input type="hidden" wire:model="staffContractId">
        <input type="hidden" wire:model="performancePeriod">
        <input type="hidden" wire:model="supervisorId">
        <input type="hidden" wire:model="supervisor2Id">

        @if ($phase->value === 'ppa')
            @include('performance::forms.ppa.section-a-c')
        @elseif ($phase->value === 'midterm')
            @if ($entry && ! $ppaApproved)
                <div class="alert alert-info">PPA must be approved before midterm review.</div>
            @else
                @include('performance::forms.midterm.midterm_section_a')
                <hr>@include('performance::forms.midterm.midterm_section_b')
                <hr>@include('performance::forms.midterm.midterm_section_c')
                <hr>@include('performance::forms.midterm.midterm_section_d')
                <hr>@include('performance::forms.midterm.midterm_section_e')
                <hr>@include('performance::forms.midterm.midterm_section_f')
            @endif
        @else
            @if ($entry && ! $ppaApproved)
                <div class="alert alert-info">PPA must be approved before end-of-year review.</div>
            @else
                @include('performance::forms.endterm.endterm_section_a')
                <hr>@include('performance::forms.endterm.endterm_section_b')
                <hr>@include('performance::forms.endterm.endterm_section_c')
                <hr>@include('performance::forms.endterm.endterm_section_d')
                <hr>@include('performance::forms.endterm.endterm_section_e')
                <hr>@include('performance::forms.endterm.endterm_section_f')
            @endif
        @endif
    </form>

    @if ($entry && $canAct)
        <div class="card border-warning mt-4">
            <div class="card-header bg-warning bg-opacity-25">Workflow action — {{ $state['label'] ?? '' }}</div>
            <div class="card-body">
                <textarea wire:model="approvalComments" class="form-control mb-3" rows="3" placeholder="Comments"></textarea>
                @if ($phase->value === 'endterm' && ($state['step'] ?? '') === 'employee_consent' && ($ppaSettings->endterm_requires_employee_consent ?? true))
                    <button type="button" wire:click="submitConsent" class="btn btn-success me-2">Record consent</button>
                @else
                    <button type="button" wire:click="submitApprove" class="btn btn-success me-2">Approve</button>
                    <button type="button" wire:click="submitReturn" class="btn btn-danger">Return</button>
                @endif
            </div>
        </div>
    @endif

    @if ($entry)
        <hr>
        <h4>Approval Trail</h4>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Name</th><th>Action</th><th>Date</th><th>Comment</th></tr></thead>
            <tbody>
                @forelse ($trail as $log)
                    <tr>
                        <td>{{ $supervisors->staffName((int) $log->staff_id) }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
                        <td>{{ $log->comments }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No approval activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
