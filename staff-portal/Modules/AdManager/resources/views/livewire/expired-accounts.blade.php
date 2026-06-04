<div>
    <x-core::data-table :paginator="$staff" :from="$from" :to="$to" :total="$total">
        <x-slot:toolbar>
            <div>
                <h4 class="text-success fw-bold mb-1">Accounts to disable</h4>
                <p class="text-muted small mb-0">Staff with expired contracts whose AD accounts should be disabled.</p>
            </div>
        </x-slot:toolbar>

        <x-slot:filters>
            <div class="row g-2 align-items-end">
                <x-core::filter-search placeholder="Search name or email…" />
                <x-core::filter-per-page />
            </div>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Division</th>
            </tr>
        </x-slot:head>

        <x-slot:body>
            @forelse ($staff as $index => $person)
                <tr wire:key="expired-{{ $person->staff_id }}">
                    <td>{{ $from + $index }}</td>
                    <td>{{ trim(($person->fname ?? '').' '.($person->lname ?? '')) }}</td>
                    <td>{{ $person->work_email ?? '—' }}</td>
                    <td>{{ $person->division_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted text-center py-3">No accounts pending disable.</td></tr>
            @endforelse
        </x-slot:body>
    </x-core::data-table>
</div>
