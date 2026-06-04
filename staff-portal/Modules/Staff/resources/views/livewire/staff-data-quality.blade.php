<div>
    <h4 class="text-success fw-bold mb-3">Data quality report</h4>
    <p class="text-muted small">Staff missing email, SAP number, or date of birth (subset of CI3 report).</p>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>SAP</th><th>DOB</th><th></th></tr></thead>
            <tbody>
                @forelse ($issues as $person)
                    <tr>
                        <td>{{ trim($person->fname.' '.$person->lname) }}</td>
                        <td>{{ $person->work_email ?: '—' }}</td>
                        <td>{{ $person->SAPNO ?: '—' }}</td>
                        <td>{{ $person->date_of_birth ?: '—' }}</td>
                        <td><a href="{{ route('staff.show', $person->staff_id) }}" class="btn btn-sm btn-outline-success">Fix</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">No issues found in sample.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
