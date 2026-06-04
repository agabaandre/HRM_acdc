<div>
    <x-core::data-table :paginator="$users" :from="$from" :to="$to" :total="$total">
        <x-slot:toolbar>
            <h4 class="text-success fw-bold mb-0">Manage users</h4>
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
                <th>Role</th>
                <th>Status</th>
            </tr>
        </x-slot:head>

        <x-slot:body>
            @forelse ($users as $index => $u)
                <tr wire:key="user-row-{{ $u->user_id }}">
                    <td>{{ $from + $index }}</td>
                    <td>{{ $u->name ?? trim(($u->fname ?? '').' '.($u->lname ?? '')) }}</td>
                    <td>{{ $u->work_email ?? '—' }}</td>
                    <td>{{ $u->role ?? '—' }}</td>
                    <td>
                        @if (($u->status ?? '') === 'active' || $u->status == 1)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted text-center py-3">No users found</td></tr>
            @endforelse
        </x-slot:body>
    </x-core::data-table>
</div>
