<div class="sys-config-stale-memos">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <h5 class="card-title text-success mb-3"><i class="bx bx-info-circle me-1"></i> Policy</h5>
            <p class="mb-2 text-muted small">
                Draft memos with budget lines that are not updated for
                <strong>{{ $draftMaxAgeMonths }}</strong> month(s) are considered stale and stop committing budget.
                When auto-archive is enabled, stale drafts are archived automatically
                <strong>{{ $weeklyRunLabel }}</strong> (next run: <strong>{{ $nextWeeklyRun }}</strong>).
            </p>
            <p class="mb-0 text-muted small">
                Auto-archive:
                @if($autoArchiveEnabled)
                    <span class="badge bg-success">Enabled</span>
                @else
                    <span class="badge bg-secondary">Disabled</span>
                    — enable under <a href="{{ route('system-configs.index', ['tab' => 'app-settings']) }}">App settings → Budget</a>
                    (<code>budget_stale_draft_auto_archive_enabled</code>).
                @endif
            </p>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-time-five me-1 text-warning"></i> Pending stale drafts ({{ count($pendingStale) }})</h5>
        </div>
        <div class="card-body p-0">
            @if(empty($pendingStale))
                <p class="text-muted p-4 mb-0">No stale draft memos currently holding budget.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Type</th>
                                <th>Title</th>
                                <th>Document</th>
                                <th>Last updated</th>
                                <th>Budget</th>
                                <th>Scheduled archive</th>
                                <th class="pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingStale as $item)
                                <tr>
                                    <td class="ps-3">{{ $item['type_label'] ?? $item['type'] }}</td>
                                    <td>{{ $item['title'] ?? 'Untitled' }}</td>
                                    <td>{{ $item['document_number'] ?? '—' }}</td>
                                    <td>{{ $item['updated_at'] ?? '' }}</td>
                                    <td>${{ number_format((float) ($item['budget_total'] ?? 0), 2) }}</td>
                                    <td>{{ $item['scheduled_archive_at'] ?? '—' }}</td>
                                    <td class="pe-3 text-end text-nowrap">
                                        @if(!empty($item['edit_url']))
                                            <a href="{{ $item['edit_url'] }}" class="btn btn-sm btn-outline-primary me-1" wire:navigate>Open</a>
                                        @endif
                                        @include('partials.stale-draft-archive-button', [
                                            'item' => $item,
                                            'redirect' => route('system-configs.index', ['tab' => 'stale-memos']),
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bx bx-archive me-1 text-secondary"></i> Archived stale drafts</h5>
        </div>
        <div class="card-body p-0">
            @if($archived->isEmpty())
                <p class="text-muted p-4 mb-0">No auto- or manually archived stale drafts yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Archived at</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Document</th>
                                <th>Memo updated</th>
                                <th>Budget</th>
                                <th class="pe-3">Trigger</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($archived as $row)
                                <tr>
                                    <td class="ps-3">{{ $row->archived_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $row->typeLabel() }}</td>
                                    <td>{{ $row->title ?? 'Untitled' }}</td>
                                    <td>{{ $row->document_number ?? '—' }}</td>
                                    <td>{{ $row->memo_updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td>${{ number_format((float) $row->budget_total, 2) }}</td>
                                    <td class="pe-3">
                                        <span class="badge bg-{{ $row->trigger === 'manual' ? 'warning' : 'secondary' }}">
                                            {{ ucfirst($row->trigger) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    {{ $archived->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
