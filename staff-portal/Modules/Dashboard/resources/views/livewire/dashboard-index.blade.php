<div>
    <div class="card border-0 shadow-sm mb-4" style="background: #119A48;">
        <div class="card-body p-4 text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1 fw-bold"><i class="fa fa-home me-2"></i>Main Dashboard</h2>
                    <p class="mb-0 opacity-75 small">Staff Portal | Africa CDC Central Business Platform</p>
                </div>
                <button type="button" class="btn btn-light btn-sm" onclick="window.print()"><i class="fa fa-print me-1"></i> Print Report</button>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-4 no-print" id="dashboardFilters">
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="fa fa-building me-1"></i>Division</label>
            <select class="form-select form-select-sm" wire:model.live="division_id">
                <option value="">All</option>
                @foreach ($divisions as $d)
                    <option value="{{ $d->division_id }}">{{ $d->division_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="fa fa-map-marker-alt me-1"></i>Duty station</label>
            <select class="form-select form-select-sm" wire:model.live="duty_station_id">
                <option value="">All</option>
                @foreach ($dutyStations as $d)
                    <option value="{{ $d->duty_station_id }}">{{ $d->duty_station_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="fa fa-money-bill-wave me-1"></i>Funder</label>
            <select class="form-select form-select-sm" wire:model.live="funder_id">
                <option value="">All</option>
                @foreach ($funders as $f)
                    <option value="{{ $f->funder_id }}">{{ $f->funder }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold"><i class="fa fa-briefcase me-1"></i>Job</label>
            <select class="form-select form-select-sm" wire:model.live="job_id">
                <option value="">All</option>
                @foreach ($jobs as $j)
                    <option value="{{ $j->job_id }}">{{ $j->job_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="{{ route('staff.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 text-white" style="background: linear-gradient(135deg, #194F90, #194F90cc);">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold mb-0">{{ $stats['staff'] ?? 0 }}</h2>
                        <p class="mb-0 small opacity-90">Main staff</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('staff.contract-status', ['preset' => 'due']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 text-white" style="background: linear-gradient(135deg, #C3A366, #C3A366cc);">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold mb-0">{{ $stats['two_months'] ?? 0 }}</h2>
                        <p class="mb-0 small opacity-90">Contracts due</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('staff.contract-status', ['preset' => 'renewal']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 text-white" style="background: linear-gradient(135deg, #119A48, #119A48cc);">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold mb-0">{{ $stats['staff_renewal'] ?? 0 }}</h2>
                        <p class="mb-0 small opacity-90">Under renewal</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('staff.contract-status', ['preset' => 'expired']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 text-white" style="background: linear-gradient(135deg, #911C39, #911C39cc);">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold mb-0">{{ $stats['expired'] ?? 0 }}</h2>
                        <p class="mb-0 small opacity-90">Expired contracts</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold" style="color: #194F90; border-bottom: 2px solid #194F90; padding-bottom: 8px;">
                        <i class="fa fa-venus-mars me-2"></i>Staff gender distribution
                    </h5>
                    <div id="genderChart" wire:ignore style="min-height: 350px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold" style="color: #C3A366; border-bottom: 2px solid #C3A366; padding-bottom: 8px;">
                        <i class="fa fa-file-contract me-2"></i>Staff by contract type
                    </h5>
                    <div id="contractChart" wire:ignore style="min-height: 350px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold" style="color: #911C39; border-bottom: 2px solid #911C39; padding-bottom: 8px;">
                        <i class="fa fa-building me-2"></i>Staff by division
                    </h5>
                    <div id="divisionChart" wire:ignore style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold" style="color: #119A48; border-bottom: 2px solid #119A48; padding-bottom: 8px;">
                        <i class="fa fa-money-bill-wave me-2"></i>Active staff by funder
                    </h5>
                    <div id="funderChart" wire:ignore style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-semibold" style="color: #194F90; border-bottom: 2px solid #194F90; padding-bottom: 8px;">
                        <i class="fa fa-globe me-2"></i>Staff by member state
                    </h5>
                    <div id="memberStateChart" wire:ignore style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@assets
<script src="{{ \App\Support\CbpAsset::url('plugins/highcharts/js/highcharts.js') }}"></script>
@endassets

@script
<script>
    Highcharts.setOptions({
        credits: { enabled: false },
        title: { text: '' },
    });

    const chartIds = ['genderChart', 'contractChart', 'divisionChart', 'funderChart', 'memberStateChart'];

    function destroyDashboardCharts() {
        chartIds.forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            const existing = Highcharts.charts.find((c) => c && c.renderTo === el);
            if (existing) existing.destroy();
        });
    }

    function genderSeries(data) {
        const points = data.data_points || data.staff_by_gender || [];
        return points.map((p) => ({
            name: p.name || 'Unknown',
            y: parseInt(p.y ?? 0, 10),
        }));
    }

    function renderDashboardCharts(data) {
        if (typeof Highcharts === 'undefined') return;
        destroyDashboardCharts();

        Highcharts.chart('genderChart', {
            chart: { type: 'pie', height: 350 },
            colors: ['#194F90', '#119A48', '#C3A366'],
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<b>{point.name}</b><br>{point.y} ({point.percentage:.1f}%)',
                    },
                },
            },
            series: [{ name: 'Gender', data: genderSeries(data) }],
        });

        const contract = data.staff_by_contract || { contract_type: [], value: [] };
        Highcharts.chart('contractChart', {
            chart: { type: 'column', height: 350 },
            xAxis: { categories: contract.contract_type || [], title: { text: null } },
            yAxis: { title: { text: 'Number of staff' }, allowDecimals: false },
            colors: ['#C3A366'],
            plotOptions: { column: { dataLabels: { enabled: true, format: '{y}' } } },
            series: [{ name: 'Staff', data: contract.value || [] }],
        });

        const division = data.staff_by_division || { division: [], value: [] };
        Highcharts.chart('divisionChart', {
            chart: { type: 'column', height: 400 },
            xAxis: {
                categories: division.division || [],
                labels: { rotation: -45, style: { fontSize: '11px' } },
            },
            yAxis: { title: { text: 'Number of staff' }, allowDecimals: false },
            colors: ['#911C39'],
            plotOptions: { column: { dataLabels: { enabled: true, format: '{y}' } } },
            series: [{ name: 'Staff', data: division.value || [] }],
        });

        const funder = data.staff_by_funder || { funder: [], value: [] };
        Highcharts.chart('funderChart', {
            chart: { type: 'column', height: 400 },
            xAxis: {
                categories: funder.funder || [],
                labels: { rotation: -45, style: { fontSize: '11px' } },
            },
            yAxis: { title: { text: 'Number of staff' }, allowDecimals: false },
            colors: ['#119A48'],
            plotOptions: {
                column: {
                    dataLabels: { enabled: true, format: '{y}' },
                    pointPadding: 0.2,
                    borderWidth: 0,
                },
            },
            series: [{ name: 'Active staff', data: funder.value || [] }],
        });

        const member = data.staff_by_member_state || { member_states: [], value: [] };
        Highcharts.chart('memberStateChart', {
            chart: { type: 'column', height: 400 },
            xAxis: {
                categories: member.member_states || [],
                labels: { rotation: -45, style: { fontSize: '11px' } },
            },
            yAxis: { title: { text: 'Number of staff' }, allowDecimals: false },
            colors: ['#194F90'],
            plotOptions: { column: { dataLabels: { enabled: true, format: '{y}' } } },
            series: [{ name: 'Staff', data: member.value || [] }],
        });
    }

    renderDashboardCharts(@json($stats));

    $wire.$watch('stats', (value) => {
        renderDashboardCharts(value);
    });
</script>
@endscript

@push('styles')
<style>
    @media print {
        #dashboardFilters, .no-print { display: none !important; }
    }
</style>
@endpush
