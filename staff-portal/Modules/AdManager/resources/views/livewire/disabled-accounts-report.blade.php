<div>
    <x-core::data-table :paginator="$staff" :from="$from" :to="$to" :total="$total">
        <x-slot:toolbar>
            <h4 class="text-success fw-bold mb-0">Disabled accounts</h4>
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
                <th>Disabled at</th>
                <th>Division</th>
            </tr>
        </x-slot:head>

        <x-slot:body>
            @forelse ($staff as $index => $person)
                <tr wire:key="disabled-{{ $person->staff_id }}">
                    <td>{{ $from + $index }}</td>
                    <td>{{ trim(($person->fname ?? '').' '.($person->lname ?? '')) }}</td>
                    <td>{{ $person->work_email ?? '—' }}</td>
                    <td class="text-nowrap small">{{ $person->email_disabled_at ?? '—' }}</td>
                    <td>{{ $person->division_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted text-center py-3">No disabled accounts found.</td></tr>
            @endforelse
        </x-slot:body>
    </x-core::data-table>
</div>
