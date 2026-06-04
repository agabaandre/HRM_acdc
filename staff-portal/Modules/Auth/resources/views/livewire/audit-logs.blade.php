<div>
    <x-core::data-table :paginator="$logs" :from="$from" :to="$to" :total="$total">
        <x-slot:toolbar>
            <h4 class="text-success fw-bold mb-0">Audit logs</h4>
        </x-slot:toolbar>

        <x-slot:filters>
            <div class="row g-2 align-items-end">
                <x-core::filter-search
                    label="Search logs"
                    placeholder="Action, URI, table, user…"
                />
                <x-core::filter-per-page />
            </div>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <th>#</th>
                <th>When</th>
                <th>User</th>
                <th>Method</th>
                <th>Action</th>
                <th>Target</th>
                <th>URI</th>
            </tr>
        </x-slot:head>

        <x-slot:body>
            @forelse ($logs as $index => $log)
                <tr wire:key="log-row-{{ $log->id }}">
                    <td>{{ $from + $index }}</td>
                    <td class="text-nowrap small">{{ $log->created_at ?? '—' }}</td>
                    <td>{{ $log->user_name ?? $log->user_id ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark">{{ $log->http_method ?? '—' }}</span></td>
                    <td class="small">{{ $log->action ?? '—' }}</td>
                    <td class="small">
                        @if ($log->target_table)
                            {{ $log->target_table }}@if($log->target_id)#{{ $log->target_id }}@endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-muted">{{ Str::limit($log->request_uri ?? '—', 80) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-3">No log entries found</td></tr>
            @endforelse
        </x-slot:body>
    </x-core::data-table>
</div>
