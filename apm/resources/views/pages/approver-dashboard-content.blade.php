<div class="container-fluid">
  <!-- Approver Statistics (quality cards, workplan-style) -->
  <div class="stats-container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <h5 class="mb-0 fw-bold">
        <i class="fa fa-chart-bar me-2"></i>Approver Dashboard Overview
      </h5>
      <span class="text-muted small text-nowrap" id="lastUpdatedShell">Last updated: <strong id="lastUpdated">—</strong></span>
    </div>
    <div class="row g-3">
      <div class="col-md-3 col-sm-6">
        <div class="stat-item total">
          <div class="stat-icon-wrap"><i class="fa fa-inbox stat-icon"></i></div>
          <span class="stat-number" id="totalApprovalRequests">0</span>
          <span class="stat-label">Total Approval Requests</span>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-item pending">
          <div class="stat-icon-wrap"><i class="fa fa-clock stat-icon"></i></div>
          <span class="stat-number" id="totalPending">0</span>
          <span class="stat-label">Total Pending</span>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-item approved">
          <div class="stat-icon-wrap"><i class="fa fa-check-circle stat-icon"></i></div>
          <span class="stat-number" id="totalApproved">0</span>
          <span class="stat-label">Total Requests Approved</span>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="stat-item returned">
          <div class="stat-icon-wrap"><i class="fa fa-undo stat-icon"></i></div>
          <span class="stat-number" id="totalReturned">0</span>
          <span class="stat-label">Total Returned</span>
        </div>
      </div>
    </div>
    <details class="approver-stats-help-details card border-0 bg-light mb-0">
      <summary class="fw-semibold text-dark py-3 px-3 mb-0 user-select-none d-flex align-items-center gap-2">
        <i class="fa fa-chevron-right approver-stats-help-chevron small text-secondary"></i>
        <span><i class="fa fa-info-circle me-1 text-secondary"></i>How these statistics are calculated</span>
      </summary>
      <div class="px-3 pb-3 border-top pt-2">
        <ul class="small text-muted mb-0 ps-3">
          <li><strong>Summary cards</strong> use the same <strong>Division</strong>, <strong>Document type</strong>, <strong>Year</strong>, and <strong>Month</strong> filters as the approver table. Each count is a <strong>document</strong> (or single-memo activity) in that scope: <em>Total Pending</em> and <em>Returned</em> are items currently in workflow at an approval level; <em>Approved</em> completed in the period; <em>Total Approval Requests</em> is pending + approved + returned for the selected filters. Types include matrices, special and non-travel memos, single memos, ARF, service requests, change requests, and other memos (when included in the selected document type).</li>
          <li><strong>Average time to last approver</strong> uses <strong>approved</strong> items only: elapsed time from submission (or the model’s submission timestamp) until the <strong>final</strong> approval, grouped by workflow. Other memos follow submit-to-final-approval on their own trail. The chart defaults to <strong>days</strong>; switch to hours for detail.</li>
          <li>Per-approver columns in the table (pending, handled, average time) use the same action rules as the live approval queues.</li>
        </ul>
      </div>
    </details>
  </div>

  <!-- Average Time to Last Approver by Workflow — table (approved documents only) -->
  <div class="card filter-card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h5 class="mb-0 text-dark">
          <i class="fa fa-table me-2"></i>Average Time to Last Approver by Workflow
        </h5>
        <p class="mb-0 mt-1 small text-muted">Includes matrices, memos, ARF, service requests, change requests, and <strong>Other Memos</strong> (Other Memo: submit → final approval). The table and chart below share the same <strong>days / hours</strong> unit.</p>
      </div>
      <div class="btn-group btn-group-sm" role="group" aria-label="Chart and table time unit">
        <button type="button" class="btn btn-outline-secondary active" id="wfChartUnitDays">Days</button>
        <button type="button" class="btn btn-outline-secondary" id="wfChartUnitHours">Hours</button>
      </div>
    </div>
    <details class="workflow-disclaimer-details border-bottom">
      <summary class="fw-semibold text-dark py-2 px-3 mb-0 user-select-none d-flex align-items-center gap-2 small">
        <i class="fa fa-chevron-right workflow-disclaimer-chevron text-secondary"></i>
        <span>About skipped approval levels (read before interpreting roles)</span>
      </summary>
      <div class="px-3 pb-3 small text-muted border-top pt-2">
        <p class="mb-2">In general workflows, some approval levels may be <strong>skipped automatically</strong> depending on conditions such as <strong>fund type</strong>, <strong>funder</strong>, <strong>division category</strong>, and related rules.</p>
        <p class="mb-2">Examples of steps that are often bypassed for certain documents include <strong>Grants</strong>, <strong>PIU</strong>, <strong>Operations</strong> or <strong>Programs</strong>, <strong>Others (DDG)</strong>, <strong>Chief of Staff</strong> or <strong>Deputy Chief of Staff</strong>, and <strong>Finance</strong> for externally funded activities.</p>
        <p class="mb-0">The <strong>Approver roles</strong> column lists every distinct role defined on the workflow; a given document may not have visited all of them. Average times reflect completed approval trails under your selected filters.</p>
      </div>
    </details>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="workflowStatsTable">
          <thead>
            <tr>
              <th>Workflow Name</th>
              <th style="min-width: 12rem;">Approver roles (unique)</th>
              <th class="text-end">Approved Docs</th>
              <th class="text-end">Avg. Time to Last Approver</th>
            </tr>
          </thead>
          <tbody id="workflowStatsBody">
            <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Average Time to Last Approver — chart (same filters; unit matches table) -->
  <div class="card filter-card mb-4">
    <div class="card-header">
      <h5 class="mb-0 text-dark">
        <i class="fa fa-chart-bar me-2"></i>Average Time to Last Approver — Chart
      </h5>
      <p class="mb-0 mt-1 small text-muted">Same workflows and filters as the table above. Approved documents only; use <strong>Days</strong> / <strong>Hours</strong> on the table card to change the unit.</p>
    </div>
    <div class="card-body">
      <div id="workflowAvgTimeChart" style="min-height: 350px;"></div>
    </div>
  </div>

  <!-- Enhanced Filters -->
  <div class="card filter-card">
    <div class="card-header">
      <h5 class="mb-0 text-dark">
        <i class="fa fa-filter me-2"></i>Filter Approvers
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-search me-1"></i>Search Approver
          </label>
          <input type="text" id="searchApprover" class="form-control" placeholder="Search...">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-building me-1"></i>Division
          </label>
          <select id="filterDivision" class="form-select">
            <option value="">All Divisions</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-file-alt me-1"></i>Document Type
          </label>
          <select id="filterDocType" class="form-select">
            <option value="">All Types</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-cogs me-1"></i>Approval Level
          </label>
          <select id="filterApprovalLevel" class="form-select">
            <option value="">All Levels</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar-alt me-1"></i>Month
          </label>
          <select id="filterMonth" class="form-select">
            <option value="">All</option>
            <option value="1">January</option>
            <option value="2">February</option>
            <option value="3">March</option>
            <option value="4">April</option>
            <option value="5">May</option>
            <option value="6">June</option>
            <option value="7">July</option>
            <option value="8">August</option>
            <option value="9">September</option>
            <option value="10">October</option>
            <option value="11">November</option>
            <option value="12">December</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar me-1"></i>Year
          </label>
          <select id="filterYear" class="form-select">
            <option value="">All Years</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold d-block">&nbsp;</label>
          <button type="button" class="btn btn-outline-secondary w-100" id="clearFilters">
            Clear
          </button>
        </div>
      </div>
    </div>
  </div>

    <!-- Approver Dashboard Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-table me-2 text-primary"></i>Approver Dashboard</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-danger btn-sm" id="exportPdfTable" title="Export current filters to PDF">
                        <i class="fa fa-file-pdf me-1"></i>Export to PDF
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="exportExcel">
                        <i class="fa fa-file-excel me-1"></i>Export to Excel
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="approverTable">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Approver</th>
                                <th>Last approval date</th>
                                <th style="width: 350px;">Role</th>
                                <th>Pending Items</th>
                                <th>Total Pending</th>
                                <th>Total Handled</th>
                                <th>Avg. Time</th>
                            </tr>
                        </thead>
                        <tbody id="approverTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Loading approver data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
  .approver-stats-help-details > summary {
    list-style: none;
    cursor: pointer;
  }
  .approver-stats-help-details > summary::-webkit-details-marker {
    display: none;
  }
  .approver-stats-help-chevron {
    display: inline-block;
    transition: transform 0.15s ease;
  }
  .approver-stats-help-details[open] .approver-stats-help-chevron {
    transform: rotate(90deg);
  }
  .workflow-disclaimer-details > summary {
    list-style: none;
    cursor: pointer;
    background: rgba(248, 249, 250, 0.95);
  }
  .workflow-disclaimer-details > summary::-webkit-details-marker {
    display: none;
  }
  .workflow-disclaimer-chevron {
    display: inline-block;
    transition: transform 0.15s ease;
  }
  .workflow-disclaimer-details[open] .workflow-disclaimer-chevron {
    transform: rotate(90deg);
  }
</style>
