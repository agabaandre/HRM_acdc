<div>
    <h4 class="text-success fw-bold mb-3">Staff birthdays — {{ now()->format('F') }}</h4>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>Name</th><th>Date of birth</th><th>Email</th></tr></thead>
            <tbody>
                @forelse ($staff as $person)
                    <tr>
                        <td>{{ trim($person->fname.' '.$person->lname) }}</td>
                        <td>{{ $person->date_of_birth }}</td>
                        <td>{{ $person->work_email }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center">No birthdays this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
