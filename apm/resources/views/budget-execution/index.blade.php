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
.budget-exec-page .division-card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
  overflow: hidden;
}
.budget-exec-page .division-card .card-header {
  background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
  border-bottom: 1px solid #d4edda;
}
.budget-exec-page .division-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f5132;
}
.budget-exec-page .exec-table {
  table-layout: fixed;
  width: 100%;
  margin-bottom: 0;
}
.budget-exec-page .exec-table th,
.budget-exec-page .exec-table td {
  vertical-align: top;
  font-size: 0.82rem;
}
.budget-exec-page .wrap-title {
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  line-height: 1.35;
  max-width: 100%;
}
.budget-exec-page .col-type { width: 9%; }
.budget-exec-page .col-doc { width: 11%; }
.budget-exec-page .col-title { width: 24%; }
.budget-exec-page .col-money { width: 9%; }
.budget-exec-page .col-pct { width: 6%; }
.budget-exec-page .col-status { width: 9%; }
.budget-exec-page .col-fund { width: 23%; }
.budget-exec-page .fund-code-pill {
  display: block;
  font-size: 0.75rem;
  padding: 0.15rem 0;
  border-bottom: 1px dashed #e9ecef;
}
.budget-exec-page .fund-code-pill:last-child { border-bottom: none; }
.budget-exec-page .initiative-block {
  border-top: 1px solid #eee;
}
.budget-exec-page .initiative-block:first-child { border-top: none; }
.budget-exec-page .division-fund-summary {
  background: #f8faf8;
  border-bottom: 1px solid #e9ecef;
}
</style>

<div class="container-fluid budget-exec-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <a wire:navigate href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bx bx-arrow-back me-1"></i> Reports
    </a>
    <div id="export_buttons" class="d-none d-flex flex-wrap gap-2">
      <a href="#" id="btn_export_excel" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener">
        <i class="bx bx-spreadsheet me-1"></i> Export Excel
      </a>
      <a href="#" id="btn_export_pdf" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
        <i class="bx bx-file me-1"></i> Export PDF
      </a>
    </div>
  </div>

  <div class="scope-note mb-3">
    <strong>APM budget execution</strong> — based on <em>approved</em> activities and memos initiated in APM.
    <strong>Executed</strong> funds are those requested through an approved <strong>Service Request</strong> (intramural) or <strong>ARF</strong> (extramural).
    An initiative is <strong>100% executed</strong> when SR/ARF totals reach its approved budget.
    @if($scope['access'] === 'all')
      You are viewing <strong>all divisions</strong> (select a division below to filter).
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
            <option value="" @selected($canViewAllDivisions ?? false)>All divisions</option>
            @foreach($divisions as $d)
              <option value="{{ $d->id }}"
                @selected(!($canViewAllDivisions ?? false) && isset($scope['default_division_id']) && (int)$scope['default_division_id'] === (int)$d->id)>
                {{ $d->division_name }}
              </option>
            @endforeach
          </select>
        </div>
        @else
          <input type="hidden" id="filter_division" value="{{ $scope['default_division_id'] ?? '' }}">
        @endif
        <div class="col-md-3">
          <button type="button" id="btn_apply" class="btn btn-success btn-sm">Apply</button>
          <button type="button" id="btn_reset" class="btn btn-outline-secondary btn-sm">Reset</button>
        </div>
      </div>
    </div>
  </div>

  <div id="loading_state" class="text-center py-5 text-muted">
    <div class="spinner-border text-success" role="status"></div>
    <p class="mt-2 mb-0">Loading budget execution…</p>
    <p class="small text-muted mb-0">Results are cached for faster repeat loads.</p>
  </div>

  <div id="error_state" class="alert alert-danger d-none" role="alert">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <strong>Could not load budget execution data.</strong>
        <span id="error_detail" class="d-block small mt-1"></span>
      </div>
      <button type="button" id="btn_retry" class="btn btn-outline-danger btn-sm">Retry</button>
    </div>
  </div>

  <div id="dashboard_content" class="d-none">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <span class="small text-muted">
        <span id="period_label"></span>
        <span id="cache_label" class="ms-2 d-none"></span>
      </span>
    </div>

    <div class="row g-3 mb-4" id="summary_kpis"></div>

    <div id="divisions_container" class="d-flex flex-column gap-3"></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const dataUrl = @json(route('budget-execution.data'));
  const excelUrl = @json(route('budget-execution.export.excel'));
  const pdfUrl = @json(route('budget-execution.export.pdf'));
  const fmtMoney = (n) => '$' + (parseFloat(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtPct = (n) => (parseFloat(n) || 0).toFixed(1) + '%';
  const pctClass = (n) => n >= 99.9 ? 'exec-pct-high' : (n >= 50 ? 'exec-pct-mid' : 'exec-pct-low');
  const esc = (s) => $('<div>').text(s ?? '').html();

  function typeLabel(t) {
    return ({ matrix_activity: 'Matrix activity', single_memo: 'Single memo', special_memo: 'Special memo', non_travel_memo: 'Non-travel' })[t] || t;
  }

  function filterParams(forceRefresh) {
    const periodMode = $('#filter_period_mode').val();
    const params = {
      year: $('#filter_year').val(),
      period_mode: periodMode,
      quarter: periodMode === 'quarterly' ? $('#filter_quarter').val() : '',
      division_id: $('#filter_division').val() || ''
    };
    if (forceRefresh) params.nocache = 1;
    return params;
  }

  function updateExportLinks() {
    const qs = $.param(filterParams(false));
    $('#btn_export_excel').attr('href', excelUrl + '?' + qs);
    $('#btn_export_pdf').attr('href', pdfUrl + '?' + qs);
    $('#export_buttons').removeClass('d-none');
  }

  function loadData(forceRefresh) {
    const params = filterParams(forceRefresh);

    $('#loading_state').removeClass('d-none');
    $('#error_state').addClass('d-none');
    $('#dashboard_content').addClass('d-none');

    $.ajax({
      url: dataUrl,
      data: params,
      dataType: 'json',
      timeout: 120000
    }).done(function (resp) {
      if (resp && resp.error) {
        showError(resp.message || resp.error);
        return;
      }
      renderDashboard(resp);
      updateExportLinks();
      $('#loading_state').addClass('d-none');
      $('#dashboard_content').removeClass('d-none');
    }).fail(function (xhr) {
      let detail = '';
      try {
        const body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
        detail = body.message || body.error || '';
      } catch (e) {}
      if (!detail && xhr.status) detail = 'HTTP ' + xhr.status;
      showError(detail);
    });
  }

  function showError(detail) {
    $('#loading_state').addClass('d-none');
    $('#dashboard_content').addClass('d-none');
    $('#error_detail').text(detail || 'Please try again or contact support.');
    $('#error_state').removeClass('d-none');
  }

  function renderFundCodes(fundCodes, compact) {
    if (!fundCodes || !fundCodes.length) {
      return '<span class="text-muted">—</span>';
    }
    return fundCodes.map(fc => {
      const label = esc(fc.code) + (fc.activity ? ' <span class="text-muted">(' + esc(fc.activity) + ')</span>' : '');
      if (compact) {
        return `<span class="fund-code-pill">${label}<br>
          <span class="text-muted">Planned ${fmtMoney(fc.planned)} · Executed ${fmtMoney(fc.executed)} · Remaining ${fmtMoney(fc.remaining)}</span>
          <span class="text-muted d-block">Working balance ${fmtMoney(fc.working_balance)}</span>
        </span>`;
      }
      return `<tr>
        <td>${label}</td>
        <td class="text-end">${fmtMoney(fc.planned)}</td>
        <td class="text-end">${fmtMoney(fc.executed)}</td>
        <td class="text-end">${fmtMoney(fc.remaining)}</td>
        <td class="text-end">${fmtMoney(fc.working_balance)}</td>
      </tr>`;
    }).join('');
  }

  function renderDashboard(data) {
    const s = data.summary || {};
    const filters = data.filters || {};
    const periodLabel = filters.period_mode === 'annual'
      ? `Annual ${filters.year}`
      : `${filters.quarter || ''} ${filters.year}`.trim();
    $('#period_label').text(periodLabel);
    if (data.cached_at) {
      $('#cache_label').removeClass('d-none').text('Cached ' + new Date(data.cached_at).toLocaleString());
    } else {
      $('#cache_label').addClass('d-none').text('');
    }

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

    const divisions = data.divisions || [];
    if (!divisions.length) {
      $('#divisions_container').html('<div class="alert alert-light border text-center text-muted">No data for this period.</div>');
      return;
    }

    const html = divisions.map(div => {
      const initiatives = div.initiatives || [];
      const fundSummary = (div.fund_codes || []).length ? `
        <div class="division-fund-summary px-3 py-2">
          <div class="small fw-semibold text-success mb-1">Fund codes (division total)</div>
          <table class="table table-sm exec-table mb-0">
            <thead><tr>
              <th>Fund code</th>
              <th class="text-end col-money">Planned</th>
              <th class="text-end col-money">Executed</th>
              <th class="text-end col-money">Remaining</th>
              <th class="text-end col-money">Working balance</th>
            </tr></thead>
            <tbody>${renderFundCodes(div.fund_codes, false)}</tbody>
          </table>
        </div>` : '';

      const initiativeRows = initiatives.map(row => {
        let status = '<span class="badge bg-secondary">Not started</span>';
        if (row.fully_executed) status = '<span class="badge bg-success">100% executed</span>';
        else if (row.has_sr_or_arf) status = '<span class="badge bg-warning text-dark">Partial</span>';

        return `<tr class="initiative-block">
          <td class="col-type">${esc(typeLabel(row.source_type))}</td>
          <td class="col-doc">${esc(row.document_number || '—')}</td>
          <td class="col-title wrap-title">${esc(row.title || '—')}</td>
          <td class="text-end col-money">${fmtMoney(row.planned_budget)}</td>
          <td class="text-end col-money">${fmtMoney(row.executed_budget)}</td>
          <td class="text-end col-pct ${pctClass(row.execution_pct)}">${fmtPct(row.execution_pct)}</td>
          <td class="col-status">${status}</td>
          <td class="col-fund">${renderFundCodes(row.fund_codes, true)}</td>
        </tr>`;
      }).join('');

      return `<div class="card division-card">
        <div class="card-header py-2 px-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="division-title">${esc(div.division_name)}</div>
            <div class="small text-muted">
              ${div.initiative_count || 0} initiatives ·
              ${div.with_sr_or_arf || 0} with SR/ARF ·
              ${div.fully_executed_count || 0} at 100%
            </div>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
            <div class="small">${fmtMoney(div.executed_budget)} / ${fmtMoney(div.planned_budget)}</div>
            <div class="progress flex-grow-1" style="max-width:220px;height:8px;">
              <div class="progress-bar bg-success" style="width:${Math.min(100, div.execution_pct || 0)}%"></div>
            </div>
            <span class="small ${pctClass(div.execution_pct)}">${fmtPct(div.execution_pct)}</span>
          </div>
        </div>
        ${fundSummary}
        <div class="card-body p-0">
          <table class="table table-sm exec-table mb-0">
            <thead class="table-light">
              <tr>
                <th class="col-type">Type</th>
                <th class="col-doc">Document</th>
                <th class="col-title">Title</th>
                <th class="text-end col-money">Budget</th>
                <th class="text-end col-money">Executed</th>
                <th class="text-end col-pct">%</th>
                <th class="col-status">Status</th>
                <th class="col-fund">Fund codes</th>
              </tr>
            </thead>
            <tbody>${initiativeRows || '<tr><td colspan="8" class="text-center text-muted py-3">No initiatives</td></tr>'}</tbody>
          </table>
        </div>
      </div>`;
    }).join('');

    $('#divisions_container').html(html);
  }

  $('#filter_period_mode').on('change', function () {
    $('#quarter_filter_wrap').toggleClass('d-none', $(this).val() === 'annual');
  });

  $('#btn_apply').on('click', function () { loadData(false); });
  $('#btn_retry').on('click', function () { loadData(true); });
  $('#btn_reset').on('click', function () {
    $('#filter_period_mode').val('quarterly');
    $('#filter_year').val(@json($currentYear));
    $('#filter_quarter').val(@json($currentQuarter));
    $('#filter_division').val('');
    $('#quarter_filter_wrap').removeClass('d-none');
    loadData(false);
  });

  loadData(false);
})();
</script>
@endpush
