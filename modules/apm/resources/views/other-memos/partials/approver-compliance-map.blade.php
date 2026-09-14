@php
    use App\Helpers\PrintHelper;
    /** @var \App\Models\OtherMemo $memo */
    $organized = PrintHelper::organizeOtherMemoApproversBySection($memo);
    $printOrder = ['to', 'through', 'from'];
    $sectionLabels = ['to' => 'To', 'through' => 'Through', 'from' => 'From'];
    $configByStep = collect(PrintHelper::applyOtherMemoDefaultSections($memo->approvers_config ?? []))
        ->keyBy(fn ($row) => (int) ($row['sequence'] ?? 0));
    $statusBadge = function (string $status): string {
        return match ($status) {
            'approved' => '<span class="badge bg-success">Approved</span>',
            'current' => '<span class="badge bg-primary">Awaiting action</span>',
            'waiting' => '<span class="badge bg-secondary">Waiting</span>',
            default => '<span class="badge bg-warning text-dark">Pending</span>',
        };
    };
@endphp
<div class="card border bg-light mb-3">
    <div class="card-header py-2 bg-white">
        <span class="fw-semibold small text-success"><i class="bx bx-map me-1"></i> Approval map</span>
    </div>
    <div class="card-body small py-3">
        <p class="text-muted mb-3 mb-md-2">
            <strong>PDF header</strong> prints <strong>To</strong> (top) → <strong>Through</strong> → <strong>From</strong> (bottom).
            <strong>Approval</strong> runs Step 1 first; do not skip steps.
        </p>
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="fw-bold text-success mb-2">Printed header (top → bottom)</h6>
                @php $hasPrintRows = false; @endphp
                @foreach ($printOrder as $section)
                    @php $rows = $organized[$section] ?? []; @endphp
                    @if (count($rows) === 0)
                        @continue
                    @endif
                    @php $hasPrintRows = true; @endphp
                    <div class="mb-2">
                        <span class="badge bg-success">{{ $sectionLabels[$section] ?? ucfirst($section) }}</span>
                        <ul class="list-unstyled mb-0 ms-2 mt-1">
                            @foreach ($rows as $row)
                                @php
                                    $step = (int) ($row['sequence'] ?? 0);
                                    $cfg = $configByStep->get($step, []);
                                    $status = PrintHelper::otherMemoApproverStepStatus($memo, $step);
                                @endphp
                                <li class="mb-1">
                                    {!! $statusBadge($status) !!}
                                    <strong>{{ $row['staff']['name'] ?? 'Staff' }}</strong>
                                    <span class="text-muted">({{ $row['role'] ?? ($cfg['role_label'] ?? 'Approver') }})</span>
                                    <span class="badge bg-light text-dark border">Step {{ $step }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @if (! $hasPrintRows)
                    <p class="text-muted mb-0">No approvers configured.</p>
                @endif
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold text-success mb-2">Approval order (who acts when)</h6>
                <ol class="mb-0 ps-3">
                    @foreach ($configByStep->sortKeys() as $step => $row)
                        @php
                            $status = PrintHelper::otherMemoApproverStepStatus($memo, (int) $step);
                            $section = strtolower((string) ($row['memo_section'] ?? 'through'));
                            $sectionLabel = $sectionLabels[$section] ?? 'Through';
                            $st = \App\Models\Staff::query()->where('staff_id', (int) ($row['staff_id'] ?? 0))->first();
                            $name = $st
                                ? trim(($st->title ? $st->title . ' ' : '') . $st->fname . ' ' . $st->lname)
                                : 'Staff #' . ($row['staff_id'] ?? '');
                        @endphp
                        <li class="mb-2">
                            {!! $statusBadge($status) !!}
                            <strong>Step {{ $step }}</strong> → {{ $name }}
                            <span class="text-muted">({{ $row['role_label'] ?? 'Approver' }})</span>
                            · prints as <em>{{ $sectionLabel }}</em>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</div>
