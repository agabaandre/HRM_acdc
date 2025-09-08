<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="bx bx-calendar-event me-2 text-primary"></i>
            Division Schedule - {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}
        </h5>
        <small class="text-muted d-block mt-1">
            Staff schedule for {{ $matrix->division->division_name ?? 'Division' }} in {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">#</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Staff Name</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Position</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Division Days</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Other Divisions</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Total Days</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $count = 0;
                    @endphp
                    @forelse($matrix->division_staff as $staff)
                        @php
                         $quarter_year = $matrix->quarter."-".$matrix->year;
                         $count++;
                         $division_days = (isset($staff->division_days[$quarter_year])) ? $staff->division_days[$quarter_year] : 0;
                         $other_days = (isset($staff->other_days[$quarter_year])) ? $staff->other_days[$quarter_year] : 0;
                         $total_days = $division_days + $other_days;
                         $isOverLimit = $total_days >= 21;
                        @endphp
                        <tr class="{{ $isOverLimit ? 'table-danger' : '' }}">
                            <td class="px-3 py-3">
                                <span class="badge bg-secondary rounded-pill">{{ $count }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="fw-semibold">
                                    <a href="#" class="text-decoration-none text-primary" onclick="showStaffActivities({{ $staff->staff_id }}, '{{ $staff->fname . " " . $staff->lname }}')">
                                        {{ $staff->title . " " . $staff->fname . " " . $staff->lname }}
                                    </a>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-muted">{{ $staff->job_name ?? 'Not specified' }}</div>
                                  @if($staff->duty_station_name)
                                    <small class="text-muted">{{ $staff->duty_station_name }}</small>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="fw-semibold text-muted">{{ $division_days }}</span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="fw-semibold text-muted">{{ $other_days }}</span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if($isOverLimit)
                                    <span class="fw-bold text-danger">
                                        <i class="bx bx-exclamation-triangle me-1"></i>{{ $total_days }}
                                    </span>
                                    <small class="d-block text-danger mt-1">Over limit</small>
                                @else
                                    <span class="fw-bold text-muted">{{ $total_days }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bx bx-calendar-x fs-1 text-muted"></i>
                                <div class="mt-2">No staff schedule data available</div>
                                <small>Staff schedules will appear here once they are added</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($matrix->division_staff->count() > 0)
            <div class="card-footer bg-light border-0 py-3">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bx bx-user-check text-success me-2"></i>
                            <div>
                                <div class="fw-bold text-success">{{ $matrix->division_staff->count() }}</div>
                                <small class="text-muted">Total Staff</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bx bx-calendar-check text-primary me-2"></i>
                            <div>
                                <div class="fw-bold text-primary">
                                    {{ $matrix->division_staff->sum(function($staff) use ($matrix) {
                                        $quarter_year = $matrix->quarter."-".$matrix->year;
                                        $division_days = (isset($staff->division_days[$quarter_year])) ? $staff->division_days[$quarter_year] : 0;
                                        return $division_days;
                                    }) }}
                                </div>
                                <small class="text-muted">Division Days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bx bx-exclamation-triangle text-warning me-2"></i>
                            <div>
                                <div class="fw-bold text-danger">
                                    {{ $matrix->division_staff->filter(function($staff) use ($matrix) {
                                        $quarter_year = $matrix->quarter."-".$matrix->year;
                                        $division_days = (isset($staff->division_days[$quarter_year])) ? $staff->division_days[$quarter_year] : 0;
                                        $other_days = (isset($staff->other_days[$quarter_year])) ? $staff->other_days[$quarter_year] : 0;
                                        return ($division_days + $other_days) >= 21;
                                    })->count() }}
                                </div>
                                <small class="text-muted">Over Limit</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>