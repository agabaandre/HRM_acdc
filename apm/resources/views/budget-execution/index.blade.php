@extends('layouts.app')

@section('title', 'Budget execution dashboard')
@section('header', 'Budget execution dashboard')

@section('content')
<style>
.budget-exec-page .kpi-card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.budget-exec-page .kpi-value {
  font-size: 1.65rem;
  font-weight: 800;
  line-height: 1.1;
}
.budget-exec-page .progress {
  height: 10px;
  border-radius: 999px;
}
.budget-exec-page .scope-note {
  background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
  border-left: 4px solid #119a48;
  padding: 0.75rem 1rem;
  border-radius: 0 8px 8px 0;
  font-size: 0.9rem;
}
.budget-exec-page .exec-pct-high { color: #15803d; font-weight: 700; }
.budget-exec-page .exec-pct-mid { color: #b45309; font-weight: 700; }
.budget-exec-page .exec-pct-low { color: #b91c1c; font-weight: 700; }
</style>

<div class="container-fluid budget-exec-page">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bx bx-arrow-back me-1"></i> Reports
    </a>
  </div>

  <div class="scope-note mb-3">
    <strong>APM budget execution</strong> — based on <em>approved</em> activities and memos initiated in APM.
    <strong>Executed</strong> funds are those requested through an approved <strong>Service Request</strong> (intramural) or <strong>ARF</strong> (extramural).
    An initiative is <strong>100% executed</strong> when SR/ARF totals reach its approved budget.
    @if($scope['access'] === 'all')
      You are viewing <strong>all divisions</strong>.
    @elseif($scope['is_director'])
      You are viewing divisions under your <strong>directorate / director</strong> oversight.
    @else
      You are viewing your <strong>division only</strong>.
    @endif
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light py-2"><strong>Filters</strong></div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label small">Period</label>
          <select id="filter_period_mode" class="form-select form-select-sm">
            <option value="quarterly" selected>Quarterly</option>
            <option value="annual">Annual (full year)</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Year</label>
          <select id="filter_year" class="form-select form-select-sm">
            @foreach($years as $y)
              <option value="{{ $y }}" @selected($y === $currentYear)>{{ $y }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2" id="quarter_filter_wrap">
          <label class="form-label small">Quarter</label>
          <select id="filter_quarter" class="form-select form-select-sm">
            @foreach($quarters as $q)
              <option value="{{ $q }}" @selected($q === $currentQuarter)>{{ $q }}</option>
            @endforeach
          </select>
        </div>
        @if($canPickDivision)
        <div class="col-md-3">
          <label class="form-label small">Division</label>
          <select id="filter_division" class="form-select form-select-sm">
            <option value="">All visible divisions</option>
            @foreach($divisions as $d)
              <option value="{{ $d->id }}" @selected(isset($scope['default_division_id']) && (int)$scope['default_division_id'] === (int)$d->id)>{{ $d->division_name }}</option>
            @endforeach
          </select>
        </div>
        @else
          <input type="hidden" id="filter_division" value="{{ $scope['default_division_id'] ?? '' }}">
        @endif
        <div class="col-md-2">
          <button type="button" id="btn_apply" class="btn btn-success btn-sm">Apply</button>
          <button type="button" id="btn_reset" class="btn btn-outline-secondary btn-sm">Reset</button>
        </div>
      </div>
    </div>
  </div>

  <div id="loading_state" class="text-center py-5 text-muted d-none">
    <div class="spinner-border text-success" role="status"></div>
    <p class="mt-2 mb-0">Loading budget execution…</p>
  </div>

  <div id="dashboard_content" class="d-none">
    <div class="row g-3 mb-3" id="summary_kpis"></div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
        <strong>By division</strong>
        <span class="small text-muted" id="period_label"></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Division</th>
                <th class="text-end">Initiatives</th>
                <th class="text-end">With SR/ARF</th>
                <th class="text-end">100% executed</th>
                <th class="text-end">Approved budget</th>
                <th class="text-end">Executed</th>
                <th style="min-width:140px">Execution</th>
              </tr>
            </thead>
            <tbody id="division_table_body"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-light py-2"><strong>Initiative detail</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Type</th>
                <th>Document</th>
                <th>Title</th>
                <th>Division</th>
                <th class="text-end">Budget</th>
                <th class="text-end">Executed</th>
                <th class="text-end">%</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="initiative_table_body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const dataUrl = @json(route('budget-execution.data'));
  const fmtMoney = (n) => '$' + (parseFloat(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtPct = (n) => (parseFloat(n) || 0).toFixed(1) + '%';
  const pctClass = (n) => n >= 99.9 ? 'exec-pct-high' : (n >= 50 ? 'exec-pct-mid' : 'exec-pct-low');

  function typeLabel(t) {
    return ({ matrix_activity: 'Matrix activity', single_memo: 'Single memo', special_memo: 'Special memo', non_travel_memo: 'Non-travel' })[t] || t;
  }

  function loadData() {
    const periodMode = $('#filter_period_mode').val();
    const params = {
      year: $('#filter_year').val(),
      period_mode: periodMode,
      quarter: periodMode === 'quarterly' ? $('#filter_quarter').val() : '',
      division_id: $('#filter_division').val() || ''
    };

    $('#loading_state').removeClass('d-none');
    $('#dashboard_content').addClass('d-none');

    $.getJSON(dataUrl, params).done(function (resp) {
      renderDashboard(resp);
      $('#loading_state').addClass('d-none');
      $('#dashboard_content').removeClass('d-none');
    }).fail(function () {
      $('#loading_state').addClass('d-none');
      alert('Could not load budget execution data.');
    });
  }

  function renderDashboard(data) {
    const s = data.summary || {};
    const filters = data.filters || {};
    const periodLabel = filters.period_mode === 'annual'
      ? `Annual ${filters.year}`
      : `${filters.quarter || ''} ${filters.year}`.trim();
    $('#period_label').text(periodLabel);

    $('#summary_kpis').html(`
      <div class="col-md-3"><div class="card kpi-card"><div class="card-body">
        <div class="text-muted small">Approved initiatives</div>
        <div class="kpi-value text-success">${s.initiative_count || 0}</div>
      </div></div></div>
      <div class="col-md-3"><div class="card kpi-card"><div class="card-body">
        <div class="text-muted small">With SR / ARF</div>
        <div class="kpi-value">${s.with_sr_or_arf || 0}</div>
      </div></div></div>
      <div class="col-md-3"><div class="card kpi-card"><div class="card-body">
        <div class="text-muted small">100% executed</div>
        <div class="kpi-value">${s.fully_executed_count || 0}</div>
      </div></div></div>
      <div class="col-md-3"><div class="card kpi-card"><div class="card-body">
        <div class="text-muted small">Overall execution</div>
        <div class="kpi-value ${pctClass(s.execution_pct)}">${fmtPct(s.execution_pct)}</div>
        <div class="small text-muted">${fmtMoney(s.executed_budget)} / ${fmtMoney(s.planned_budget)}</div>
      </div></div></div>
    `);

    const divRows = (data.by_division || []).map(row => `
      <tr>
        <td>${row.division_name}</td>
        <td class="text-end">${row.initiative_count}</td>
        <td class="text-end">${row.with_sr_or_arf}</td>
        <td class="text-end">${row.fully_executed_count}</td>
        <td class="text-end">${fmtMoney(row.planned_budget)}</td>
        <td class="text-end">${fmtMoney(row.executed_budget)}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1"><div class="progress-bar bg-success" style="width:${Math.min(100, row.execution_pct)}%"></div></div>
            <span class="small ${pctClass(row.execution_pct)}">${fmtPct(row.execution_pct)}</span>
          </div>
        </td>
      </tr>
    `).join('');
    $('#division_table_body').html(divRows || '<tr><td colspan="7" class="text-center text-muted py-3">No data for this period.</td></tr>');

    const initRows = (data.initiatives || []).map(row => {
      let status = 'Not started';
      if (row.fully_executed) status = '<span class="badge bg-success">100% executed</span>';
      else if (row.has_sr_or_arf) status = '<span class="badge bg-warning text-dark">Partial</span>';
      return `<tr>
        <td>${typeLabel(row.source_type)}</td>
        <td>${row.document_number || '—'}</td>
        <td>${row.title || '—'}</td>
        <td>${row.division_name || '—'}</td>
        <td class="text-end">${fmtMoney(row.planned_budget)}</td>
        <td class="text-end">${fmtMoney(row.executed_budget)}</td>
        <td class="text-end ${pctClass(row.execution_pct)}">${fmtPct(row.execution_pct)}</td>
        <td>${status}</td>
      </tr>`;
    }).join('');
    $('#initiative_table_body').html(initRows || '<tr><td colspan="8" class="text-center text-muted py-3">No initiatives found.</td></tr>');
  }

  $('#filter_period_mode').on('change', function () {
    const annual = $(this).val() === 'annual';
    $('#quarter_filter_wrap').toggleClass('d-none', annual);
  });

  $('#btn_apply').on('click', loadData);
  $('#btn_reset').on('click', function () {
    $('#filter_period_mode').val('quarterly');
    $('#filter_year').val(@json($currentYear));
    $('#filter_quarter').val(@json($currentQuarter));
    $('#filter_division').val('');
    $('#quarter_filter_wrap').removeClass('d-none');
    loadData();
  });

  loadData();
})();
</script>
@endpush
