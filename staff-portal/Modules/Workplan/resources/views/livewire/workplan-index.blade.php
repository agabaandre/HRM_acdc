<div>
    <h4 class="text-success fw-bold mb-3">Workplan</h4>
    <div class="table-responsive card border-0 shadow-sm">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Title</th><th>Period</th></tr></thead>
            <tbody>
                @forelse ($plans as $p)
                    <tr>
                        <td>{{ $p->workplan_id ?? $p->id }}</td>
                        <td>{{ $p->title ?? $p->workplan_name ?? '—' }}</td>
                        <td>{{ $p->period ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center">No workplans or table not present.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
