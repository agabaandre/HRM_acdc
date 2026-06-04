<div>
    <x-core::data-table
        :paginator="$staff"
        :from="$from"
        :to="$to"
        :total="$total"
        :compact="true"
        table-class="table table-striped table-bordered table-sm align-middle mb-0 staff-directory-table-font"
    >
        <x-slot:toolbar>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="text-success fw-bold mb-0">Staff directory</h4>
                @if ($canManage)
                    <a href="#" class="btn btn-dark btn-sm disabled" title="Add staff — coming soon">+ Add new staff</a>
                @endif
            </div>
        </x-slot:toolbar>

        <x-slot:filters>
            <div class="row g-2 align-items-end">
                <x-core::filter-search
                    placeholder="Name, email, SAP no…"
                    :debounce="350"
                />
                <x-core::filter-select model="filter" label="Contract status" col="col-md-4">
                    <option value="active">Current staff ({{ $filterCounts['active'] ?? 0 }})</option>
                    <option value="due">Contracts due ({{ $filterCounts['due'] ?? 0 }})</option>
                    <option value="expired">Contracts expired ({{ $filterCounts['expired'] ?? 0 }})</option>
                    <option value="former">Former staff ({{ $filterCounts['former'] ?? 0 }})</option>
                    <option value="renewal">Under renewal ({{ $filterCounts['renewal'] ?? 0 }})</option>
                    <option value="all">All statuses ({{ $filterCounts['all'] ?? 0 }})</option>
                </x-core::filter-select>
                <x-core::filter-per-page />
            </div>
        </x-slot:filters>

        <x-slot:head>
            <tr>
                <th>#</th>
                <th>SAPNO</th>
                <th>Title</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>Age</th>
                <th>Nationality</th>
                <th>Region</th>
                <th>Duty station</th>
                <th>Division</th>
                <th>Grade</th>
                <th>Job</th>
                <th>Initiation</th>
                <th>Contract start</th>
                <th>Contract end</th>
                <th>Tenure</th>
                <th>Acting job</th>
                <th>1st supervisor</th>
                <th>2nd supervisor</th>
                <th>Funder</th>
                <th>Email</th>
                <th>Telephone</th>
                <th>WhatsApp</th>
            </tr>
        </x-slot:head>

        <x-slot:body>
            @forelse ($staff as $index => $person)
                @php
                    $rowNum = $from + $index;
                    $profileUrl = route('staff.show', $person->staff_id);
                    $fullName = trim(($person->title ?? '').' '.($person->lname ?? '').' '.($person->fname ?? '').' '.($person->oname ?? ''));
                @endphp
                <tr wire:key="staff-row-{{ $person->staff_id }}">
                    <td>{{ $rowNum }}</td>
                    <td>{{ $person->SAPNO ?? '—' }}</td>
                    <td>{{ $person->title ?? '—' }}</td>
                    <td class="text-center">
                        <a href="{{ $profileUrl }}" class="text-decoration-none">
                            <x-staff::staff-avatar :fname="$person->fname" :lname="$person->lname" :photo="$person->photo" size="sm" />
                        </a>
                    </td>
                    <td>
                        <a href="{{ $profileUrl }}" class="fw-semibold text-success text-decoration-none">{{ $fullName }}</a>
                    </td>
                    <td>{{ $person->gender ?? '—' }}</td>
                    <td>{{ $person->date_of_birth ?? 'N/A' }}</td>
                    <td>{{ \App\Support\StaffPhoto::age($person->date_of_birth ?? null) }}</td>
                    <td>{{ $person->nationality ?? '—' }}</td>
                    <td>{{ $person->region_name ?? 'N/A' }}</td>
                    <td>{{ $person->duty_station_name ?? '—' }}</td>
                    <td>{{ $person->division_name ?? '—' }}</td>
                    <td>{{ $person->grade ?? '—' }}</td>
                    <td>{{ $person->job_name ?? '—' }}</td>
                    <td>{{ $person->initiation_date ?? 'N/A' }}</td>
                    <td>{{ $person->start_date ?? 'N/A' }}</td>
                    <td>{{ $person->end_date ?? 'N/A' }}</td>
                    <td>{{ \App\Support\StaffPhoto::yearsOfTenure($person->initiation_date ?? null) }}</td>
                    <td>{{ $person->job_acting ?? '—' }}</td>
                    <td>{{ trim($person->first_supervisor_name ?? '') ?: '—' }}</td>
                    <td>{{ trim($person->second_supervisor_name ?? '') ?: '—' }}</td>
                    <td>{{ $person->funder ?? '—' }}</td>
                    <td class="small">{{ $person->work_email ?? '—' }}</td>
                    <td>{{ trim(($person->tel_1 ?? '').' '.($person->tel_2 ?? '')) ?: '—' }}</td>
                    <td>{{ $person->whatsapp ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="25" class="text-center text-muted py-4">No data available</td>
                </tr>
            @endforelse
        </x-slot:body>
    </x-core::data-table>

    <div class="modal fade" id="staffPhotoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Passport photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="staffPhotoModalImg" src="" alt="Staff photo" class="img-fluid rounded shadow-sm" style="max-height:75vh;">
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>.staff-directory-table-font { font-size: 0.8rem; }</style>
@endpush

@push('scripts')
<script>
    window.addEventListener('staff-photo-zoom', function (e) {
        var img = document.getElementById('staffPhotoModalImg');
        var modalEl = document.getElementById('staffPhotoModal');
        if (!img || !modalEl || !e.detail?.url) return;
        img.src = e.detail.url;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
</script>
@endpush
