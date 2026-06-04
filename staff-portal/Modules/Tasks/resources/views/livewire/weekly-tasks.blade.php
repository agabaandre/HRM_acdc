<div>
    <h4 class="text-success fw-bold mb-3">Weekly tasks</h4>
    <p class="text-muted small">Division staff list — full calendar and task editor from CI3 <code>weektasks/tasks</code> to follow.</p>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>Name</th><th>Division</th></tr></thead>
            <tbody>
                @forelse ($staffList as $person)
                    <tr>
                        <td>{{ trim(($person->fname ?? '').' '.($person->lname ?? '')) }}</td>
                        <td>{{ $divisions->firstWhere('division_id', $person->division_id ?? null)?->division_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted text-center">No staff in your division.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
