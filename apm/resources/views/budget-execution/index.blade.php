@extends('layouts.app')

@section('title', 'Budget execution dashboard')
@section('header', 'Budget execution dashboard')

@section('content')
<style>
.budget-exec-page {
  --be-green: #119a48;
  --be-green-dark: #0d7a38;
  --be-green-soft: #e8f7ee;
  --be-amber: #d97706;
  --be-red: #dc2626;
}
.budget-exec-page .hero-strip {
  background: linear-gradient(135deg, #0d7a38 0%, #119a48 45%, #1cb35c 100%);
  color: #fff;
  border-radius: 14px;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 8px 24px rgba(17, 154, 72, 0.22);
}
.budget-exec-page .scope-note {
  background: #fff;
  border: 1px solid #d4edda;
  border-left: 4px solid var(--be-green);
  padding: 0.85rem 1rem;
  border-radius: 0 10px 10px 0;
  font-size: 0.88rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.budget-exec-page .filter-card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.budget-exec-page .kpi-card {
  border: none;
  border-radius: 14px;
  box-shadow: 0 4px 14px rgba(0,0,0,.06);
  overflow: hidden;
  height: 100%;
  transition: transform .15s ease, box-shadow .15s ease;
}
.budget-exec-page .kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0,0,0,.08);
}
.budget-exec-page .kpi-card .kpi-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}
.budget-exec-page .kpi-value {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1.1;
}
.budget-exec-page .kpi-sub { font-size: 0.78rem; color: #6c757d; }
.budget-exec-page .exec-pct-high { color: #15803d; font-weight: 700; }
.budget-exec-page .exec-pct-mid { color: #b45309; font-weight: 700; }
.budget-exec-page .exec-pct-low { color: #b91c1c; font-weight: 700; }
.budget-exec-page .division-card {
  border: 1px solid #e8f0ea;
  border-radius: 16px;
  box-shadow: 0 4px 18px rgba(17, 154, 72, 0.07);
  overflow: hidden;
  background: #fff;
}
.budget-exec-page .division-card .division-header {
  background: linear-gradient(135deg, #f0faf4 0%, #e8f7ee 55%, #fff 100%);
  border-bottom: 1px solid #dcefe3;
  padding: 1rem 1.15rem;
}
.budget-exec-page .division-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--be-green-dark);
  letter-spacing: -0.02em;
}
.budget-exec-page .stat-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #fff;
  border: 1px solid #e2e8f0;
  color: #475569;
}
.budget-exec-page .stat-chip.chip-success { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.budget-exec-page .stat-chip.chip-warning { background: #fffbeb; border-color: #fde68a; color: #b45309; }
.budget-exec-page .stat-chip.chip-muted { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
.budget-exec-page .budget-balance-box {
  background: linear-gradient(135deg, #0d7a38 0%, #119a48 100%);
  color: #fff;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  min-width: 140px;
}
.budget-exec-page .budget-balance-box .label { font-size: 0.7rem; opacity: .9; text-transform: uppercase; letter-spacing: .04em; }
.budget-exec-page .budget-balance-box .amount { font-size: 1.15rem; font-weight: 800; line-height: 1.2; }
.budget-exec-page .budget-balance-box.balance-secondary {
  background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
}
.budget-exec-page .exec-ring {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 800;
  background: conic-gradient(var(--be-green) calc(var(--pct) * 1%), #e9ecef 0);
  position: relative;
}
.budget-exec-page .exec-ring::after {
  content: '';
  position: absolute;
  inset: 6px;
  background: #fff;
  border-radius: 50%;
}
.budget-exec-page .exec-ring span { position: relative; z-index: 1; }
.budget-exec-page .progress.exec-progress {
  height: 8px;
  border-radius: 999px;
  background: #e9ecef;
}
.budget-exec-page .division-fund-summary {
  background: #f8fafc;
  border-bottom: 1px solid #e9ecef;
  padding: 0.85rem 1.15rem;
}
.budget-exec-page .exec-table {
  table-layout: fixed;
  width: 100%;
  margin-bottom: 0;
}
.budget-exec-page .exec-table th,
.budget-exec-page .exec-table td {
  vertical-align: top;
  font-size: 0.8rem;
  padding: 0.5rem 0.6rem;
}
.budget-exec-page .wrap-title {
  white-space: normal;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  line-height: 1.35;
}
.budget-exec-page .col-type { width: 9%; }
.budget-exec-page .col-doc { width: 11%; }
.budget-exec-page .col-title { width: 22%; }
.budget-exec-page .col-money { width: 9%; }
.budget-exec-page .col-pct { width: 6%; }
.budget-exec-page .col-status { width: 10%; }
.budget-exec-page .col-fund { width: 24%; }
.budget-exec-page .fund-code-pill {
  display: block;
  font-size: 0.74rem;
  padding: 0.35rem 0;
  border-bottom: 1px dashed #e2e8f0;
}
.budget-exec-page .fund-code-pill:last-child { border-bottom: none; }
.budget-exec-page #divisions_pagination .page-link {
  border-radius: 8px;
  margin: 0 2px;
  color: var(--be-green-dark);
}
.budget-exec-page #divisions_pagination .page-item.active .page-link {
  background: var(--be-green);
  border-color: var(--be-green);
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

  <div class="hero-strip mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <div class="fw-bold fs-5 mb-1"><i class="bx bx-pie-chart-alt-2 me-1"></i> Budget execution</div>
        <div class="small opacity-90">Approved APM initiatives vs executed through Service Requests &amp; ARFs</div>
      </div>
      <div class="small opacity-90 text-end">
        <span id="period_label_hero">—</span>
      </div>
    </div>
  </div>

  <div class="scope-note mb-3">
    @if($scope['access'] === 'all')
      <i class="bx bx-buildings text-success me-1"></i> Viewing <strong>all divisions</strong> — 10 divisions per page. Use filters to narrow results.
    @elseif($scope['is_director'])
      <i class="bx bx-sitemap text-success me-1"></i> Viewing divisions under your <strong>directorate</strong> oversight.
    @else
      <i class="bx bx-building text-success me-1"></i> Viewing your <strong>division only</strong>.
    @endif
  </div>

  <div class="card filter-card mb-3">
    <div class="card-header bg-white py-2 border-0"><strong><i class="bx bx-filter-alt text-success me-1"></i> Filters</strong></div>
    <div class="card-body pt-0">
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
          <button type="button" id="btn_apply" class="btn btn-success btn-sm"><i class="bx bx-search me-1"></i> Apply</button>
          <button type="button" id="btn_reset" class="btn btn-outline-secondary btn-sm">Reset</button>
        </div>
      </div>
    </div>
  </div>

  <div id="loading_state" class="text-center py-5 text-muted">
    <div class="spinner-border text-success" role="status"></div>
    <p class="mt-2 mb-0">Loading budget execution…</p>
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
        <span id="cache_label" class="d-none"></span>
      </span>
    </div>

    <div class="row g-3 mb-4" id="summary_kpis"></div>

    <div id="divisions_container" class="d-flex flex-column gap-4"></div>

    <nav id="divisions_pagination" class="d-none d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 pt-3 border-top" aria-label="Division pages">
      <span id="pagination_info" class="small text-muted"></span>
      <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
    </nav>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const dataUrl = @json(route('budget-execution.data'));
  const excelUrl = @json(route('budget-execution.export.excel'));
  const pdfUrl = @json(route('budget-execution.export.pdf'));
  let currentPage = 1;
  const fmtMoney = (n) => '$' + (parseFloat(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtPct = (n) => (parseFloat(n) || 0).toFixed(1) + '%';
  const pctClass = (n) => n >= 99.9 ? 'exec-pct-high' : (n >= 50 ? 'exec-pct-mid' : 'exec-pct-low');
  const esc = (s) => $('<div>').text(s ?? '').html();

  function typeLabel(t) {
    return ({ matrix_activity: 'Matrix activity', single_memo: 'Single memo', special_memo: 'Special memo', non_travel_memo: 'Non-travel' })[t] || t;
  }

  function filterParams(forceRefresh, includePage) {
    const periodMode = $('#filter_period_mode').val();
    const params = {
      year: $('#filter_year').val(),
      period_mode: periodMode,
      quarter: periodMode === 'quarterly' ? $('#filter_quarter').val() : '',
      division_id: $('#filter_division').val() || ''
    };
    if (includePage !== false) params.page = currentPage;
    if (forceRefresh) params.nocache = 1;
    return params;
  }

  function updateExportLinks() {
    const qs = $.param(filterParams(false, false));
    $('#btn_export_excel').attr('href', excelUrl + '?' + qs);
    $('#btn_export_pdf').attr('href', pdfUrl + '?' + qs);
    $('#export_buttons').removeClass('d-none');
  }

  function loadData(forceRefresh, resetPage) {
    if (resetPage) currentPage = 1;
    const params = filterParams(forceRefresh, true);

    $('#loading_state').removeClass('d-none');
    $('#error_state').addClass('d-none');
    $('#dashboard_content').addClass('d-none');

    $.ajax({ url: dataUrl, data: params, dataType: 'json', timeout: 120000 })
      .done(function (resp) {
        if (resp && resp.error) { showError(resp.message || resp.error); return; }
        renderDashboard(resp);
        updateExportLinks();
        $('#loading_state').addClass('d-none');
        $('#dashboard_content').removeClass('d-none');
        if (resp.pagination && resp.pagination.total > 0) {
          document.getElementById('divisions_container').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .fail(function (xhr) {
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
    if (!fundCodes || !fundCodes.length) return '<span class="text-muted">—</span>';
    return fundCodes.map(fc => {
      const label = esc(fc.code) + (fc.activity ? ' <span class="text-muted">(' + esc(fc.activity) + ')</span>' : '');
      if (compact) {
        return `<span class="fund-code-pill">${label}<br>
          <span class="text-muted">Planned ${fmtMoney(fc.planned)} · Exec ${fmtMoney(fc.executed)} · Rem ${fmtMoney(fc.remaining)}</span>
          <span class="text-muted d-block">Working bal. ${fmtMoney(fc.working_balance)}</span></span>`;
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

  function renderPagination(pagination) {
    if (!pagination || pagination.total <= pagination.per_page) {
      $('#divisions_pagination').addClass('d-none');
      return;
    }
    $('#pagination_info').text(
      `Showing divisions ${pagination.from}–${pagination.to} of ${pagination.total} · Page ${pagination.current_page} of ${pagination.last_page}`
    );

    const cur = pagination.current_page;
    const last = pagination.last_page;
    let html = '';
    const add = (p, label, disabled, active) => {
      html += `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
        <a class="page-link" href="#" data-page="${p}">${label}</a></li>`;
    };
    add(cur - 1, '&laquo;', cur <= 1, false);
    const start = Math.max(1, cur - 2);
    const end = Math.min(last, cur + 2);
    if (start > 1) { add(1, '1', false, cur === 1); if (start > 2) html += '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
    for (let p = start; p <= end; p++) add(p, String(p), false, p === cur);
    if (end < last) { if (end < last - 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>'; add(last, String(last), false, cur === last); }
    add(cur + 1, '&raquo;', cur >= last, false);

    $('#pagination_links').html(html);
    $('#divisions_pagination').removeClass('d-none');
  }

  function renderDashboard(data) {
    const s = data.summary || {};
    const filters = data.filters || {};
    const periodLabel = filters.period_mode === 'annual' ? `Annual ${filters.year}` : `${filters.quarter || ''} ${filters.year}`.trim();
    $('#period_label_hero').text(periodLabel);
    if (data.cached_at) {
      $('#cache_label').removeClass('d-none').text('Cached ' + new Date(data.cached_at).toLocaleString());
    } else {
      $('#cache_label').addClass('d-none').text('');
    }

    $('#summary_kpis').html(`
      <div class="col-6 col-lg-3">
        <div class="card kpi-card"><div class="card-body">
          <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bx bx-file"></i></div>
          <div class="text-muted small">Initiatives</div>
          <div class="kpi-value text-success">${s.initiative_count || 0}</div>
          <div class="kpi-sub">${s.division_count || 0} divisions</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card kpi-card"><div class="card-body">
          <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bx bx-wallet"></i></div>
          <div class="text-muted small">Approved budget</div>
          <div class="kpi-value">${fmtMoney(s.planned_budget)}</div>
          <div class="kpi-sub">Remaining ${fmtMoney(s.remaining_budget)}</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card kpi-card"><div class="card-body">
          <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bx bx-transfer"></i></div>
          <div class="text-muted small">Executed (SR/ARF)</div>
          <div class="kpi-value">${fmtMoney(s.executed_budget)}</div>
          <div class="kpi-sub">${s.sr_count || 0} SR · ${s.arf_count || 0} ARF</div>
        </div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card kpi-card"><div class="card-body">
          <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bx bx-check-circle"></i></div>
          <div class="text-muted small">Execution rate</div>
          <div class="kpi-value ${pctClass(s.execution_pct)}">${fmtPct(s.execution_pct)}</div>
          <div class="kpi-sub">${s.fully_executed_count || 0} at 100% · ${s.partial_count || 0} partial · ${s.not_started_count || 0} not started</div>
        </div></div>
      </div>
    `);

    const divisions = data.divisions || [];
    if (!divisions.length) {
      $('#divisions_container').html('<div class="alert alert-light border text-center text-muted py-4"><i class="bx bx-info-circle me-1"></i> No data for this period.</div>');
      $('#divisions_pagination').addClass('d-none');
      return;
    }

    const html = divisions.map(div => {
      const pct = Math.min(100, parseFloat(div.execution_pct) || 0);
      const initiatives = div.initiatives || [];
      const fundSummary = (div.fund_codes || []).length ? `
        <div class="division-fund-summary">
          <div class="small fw-semibold text-success mb-2"><i class="bx bx-barcode me-1"></i> Fund codes (${div.fund_code_count || 0})</div>
          <table class="table table-sm exec-table mb-0">
            <thead class="table-light"><tr>
              <th>Code</th><th class="text-end">Planned</th><th class="text-end">Executed</th>
              <th class="text-end">Remaining</th><th class="text-end">Working bal.</th>
            </tr></thead>
            <tbody>${renderFundCodes(div.fund_codes, false)}</tbody>
          </table>
        </div>` : '';

      const initiativeRows = initiatives.map(row => {
        let status = '<span class="badge rounded-pill bg-secondary">Not started</span>';
        if (row.fully_executed) status = '<span class="badge rounded-pill bg-success">100%</span>';
        else if (row.has_sr_or_arf) status = '<span class="badge rounded-pill bg-warning text-dark">Partial</span>';
        return `<tr>
          <td class="col-type">${esc(typeLabel(row.source_type))}</td>
          <td class="col-doc small">${esc(row.document_number || '—')}</td>
          <td class="col-title wrap-title">${esc(row.title || '—')}</td>
          <td class="text-end col-money">${fmtMoney(row.planned_budget)}</td>
          <td class="text-end col-money">${fmtMoney(row.executed_budget)}</td>
          <td class="text-end col-pct ${pctClass(row.execution_pct)}">${fmtPct(row.execution_pct)}</td>
          <td class="col-status">${status}</td>
          <td class="col-fund">${renderFundCodes(row.fund_codes, true)}</td>
        </tr>`;
      }).join('');

      return `<div class="card division-card">
        <div class="division-header">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
              <div class="division-title mb-2"><i class="bx bx-building me-1"></i>${esc(div.division_name)}</div>
              <div class="d-flex flex-wrap gap-2">
                <span class="stat-chip"><i class="bx bx-file"></i> ${div.initiative_count || 0} initiatives</span>
                <span class="stat-chip chip-success"><i class="bx bx-check"></i> ${div.fully_executed_count || 0} at 100%</span>
                <span class="stat-chip chip-warning"><i class="bx bx-time"></i> ${div.partial_count || 0} partial</span>
                <span class="stat-chip chip-muted"><i class="bx bx-minus-circle"></i> ${div.not_started_count || 0} not started</span>
                <span class="stat-chip"><i class="bx bx-transfer"></i> ${div.sr_count || 0} SR · ${div.arf_count || 0} ARF</span>
              </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="exec-ring" style="--pct: ${pct}"><span class="${pctClass(pct)}">${fmtPct(pct)}</span></div>
              <div class="d-flex flex-column gap-2">
                <div class="budget-balance-box">
                  <div class="label">Budget remaining</div>
                  <div class="amount">${fmtMoney(div.remaining_budget)}</div>
                  <div class="small opacity-75">${fmtMoney(div.executed_budget)} of ${fmtMoney(div.planned_budget)} executed</div>
                </div>
                <div class="budget-balance-box balance-secondary">
                  <div class="label">Fund working balance</div>
                  <div class="amount">${fmtMoney(div.total_working_balance)}</div>
                  <div class="small opacity-75">${div.fund_code_count || 0} fund codes</div>
                </div>
              </div>
            </div>
          </div>
          <div class="progress exec-progress mt-3">
            <div class="progress-bar bg-success" style="width:${pct}%"></div>
          </div>
        </div>
        ${fundSummary}
        <div class="card-body p-0">
          <table class="table table-sm exec-table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="col-type">Type</th><th class="col-doc">Document</th><th class="col-title">Title</th>
                <th class="text-end col-money">Budget</th><th class="text-end col-money">Executed</th>
                <th class="text-end col-pct">%</th><th class="col-status">Status</th><th class="col-fund">Fund codes</th>
              </tr>
            </thead>
            <tbody>${initiativeRows || '<tr><td colspan="8" class="text-center text-muted py-3">No initiatives</td></tr>'}</tbody>
          </table>
        </div>
      </div>`;
    }).join('');

    $('#divisions_container').html(html);
    renderPagination(data.pagination);
  }

  $(document).on('click', '#pagination_links .page-link', function (e) {
    e.preventDefault();
    const $li = $(this).closest('.page-item');
    if ($li.hasClass('disabled') || $li.hasClass('active')) return;
    const page = parseInt($(this).data('page'), 10);
    if (page > 0) { currentPage = page; loadData(false, false); }
  });

  $('#filter_period_mode').on('change', function () {
    $('#quarter_filter_wrap').toggleClass('d-none', $(this).val() === 'annual');
  });

  $('#btn_apply').on('click', function () { loadData(false, true); });
  $('#btn_retry').on('click', function () { loadData(true, false); });
  $('#btn_reset').on('click', function () {
    $('#filter_period_mode').val('quarterly');
    $('#filter_year').val(@json($currentYear));
    $('#filter_quarter').val(@json($currentQuarter));
    $('#filter_division').val('');
    $('#quarter_filter_wrap').removeClass('d-none');
    loadData(false, true);
  });

  loadData(false, false);
})();
</script>
@endpush
