
<script>
  const base_url = "<?= base_url(); ?>";
  Highcharts.setOptions({ credits: { enabled: false } });
</script>

<div class="container-fluid py-0 px-4">
  <!-- Filters -->
  <div class="row mb-2">
    <div class="col-md-4">
      <label for="divisionFilter" class="form-label fw-bold">Filter by Division:</label>
      <select id="divisionFilter" class="form-control select2 border-primary">
        <option value="">All Divisions</option>
        <?php foreach ($divisions as $div): ?>
          <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="periodFilter" class="form-label fw-bold">Filter by Period:</label>
      <select id="periodFilter" class="form-control select2 border-primary">
        <option value="">All Periods</option>
      </select>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4" id="summaryCards"></div>

  <!-- Charts -->
  <div class="row g-4">
    <div class="col-lg-6"><div id="totalSubmissionsChart" class="shadow rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="approvalBreakdownChart" class="shadow rounded p-3 bg-white"></div></div>
    <div class="col-lg-12"><div id="submissionTrendChart" class="shadow rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="avgApprovalChart" class="shadow rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="divisionWiseChart" class="shadow rounded p-3 bg-white"></div></div>
    <div class="col-lg-12"><div id="contractTypeChart" class="shadow rounded p-3 bg-white"></div></div>
  </div>
</div>

<script>
  $(document).ready(function () {
    loadPPADashboard();
    $('#divisionFilter, #periodFilter').on('change', function () {
      loadPPADashboard();
    });
  });

  function loadPPADashboard() {
    const divisionId = $('#divisionFilter').val();
    const period = $('#periodFilter').val();

    $.getJSON(base_url + 'performance/fetch_ppa_dashboard_data', { division_id: divisionId, period: period }, function (data) {
      if ($('#periodFilter option').length <= 1 && data.periods) {
        data.periods.forEach(period => {
          $('#periodFilter').append(`<option value="${period}" ${period === data.current_period ? 'selected' : ''}>${period}</option>`);
        });
      }

      const cards = [
        { label: 'Total PPAs', icon: 'fa-file-alt', color: '#9F2241', value: data.total },
        { label: 'Approved PPAs', icon: 'fa-check-circle', color: '#1A5632', value: data.approved },
        { label: 'Staff With PDPs', icon: 'fa-user-check', color: '#385CAD', value: data.staff_with_pdps },
        { label: 'Staff Without PPAs', icon: 'fa-user-times', color: '#194F90', value: data.staff_without_ppas }
      ];

      $('#summaryCards').html(cards.map(card => `
        <div class="col mb-3">
          <div class="card rounded-1 text-white" style="background: ${card.color};">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="mb-0 fw-bold">${card.label}</p>
                  <h4 class="text-white fw-bold">${card.value}</h4>
                </div>
                <div class="fs-1"><i class="fa ${card.icon}"></i></div>
              </div>
            </div>
          </div>
        </div>
      `).join(''));

      Highcharts.chart('totalSubmissionsChart', {
        chart: { type: 'column' },
        title: { text: 'Total Submissions', style: { color: '#911C39' } },
        xAxis: { categories: ['Submissions'] },
        yAxis: { title: { text: 'Count' } },
        colors: ['#911C39'],
        series: [{ name: 'Total', data: [data.total] }]
      });

      Highcharts.chart('approvalBreakdownChart', {
        chart: { type: 'pie' },
        title: { text: 'Approval Status Breakdown', style: { color: '#119A48' } },
        colors: ['#119A48', '#fbb924'],
        series: [{
          name: 'Status',
          data: [
            { name: 'Approved', y: data.approved },
            { name: 'Pending', y: data.submitted }
          ]
        }]
      });

      Highcharts.chart('submissionTrendChart', {
        chart: { type: 'area' },
        title: { text: 'Submission Trend Over Time', style: { color: '#C3A366' } },
        xAxis: {
          categories: data.trend.map(item => item.date),
          tickmarkPlacement: 'on',
          title: { text: 'Date' }
        },
        yAxis: { title: { text: 'Submissions' } },
        colors: ['#C3A366'],
        series: [{ name: 'Submissions', data: data.trend.map(item => item.count) }]
      });

      Highcharts.chart('avgApprovalChart', {
        chart: { type: 'solidgauge' },
        title: { text: 'Avg Approval Time (Days)', style: { color: '#5F5F5F' } },
        pane: {
          center: ['50%', '85%'], size: '140%', startAngle: -90, endAngle: 90,
          background: { backgroundColor: '#EEE', innerRadius: '60%', outerRadius: '100%', shape: 'arc' }
        },
        tooltip: { enabled: false },
        yAxis: {
          min: 0, max: 30,
          stops: [[0.1, '#119A48'], [0.5, '#fbb924'], [0.9, '#911C39']],
          lineWidth: 0, tickWidth: 0, labels: { enabled: false }
        },
        series: [{
          name: 'Days',
          data: [data.avg_approval_days],
          dataLabels: {
            format: '<div style="text-align:center"><span style="font-size:1.5em;color:#5F5F5F">{y}</span><br/><span style="font-size:12px;color:silver">days</span></div>'
          }
        }]
      });

      Highcharts.chart('divisionWiseChart', {
        chart: { type: 'bar' },
        title: { text: 'Submissions by Division', style: { color: '#119A48' } },
        xAxis: { categories: data.by_division.map(d => d.name) },
        yAxis: { title: { text: 'Submissions' } },
        colors: ['#119A48'],
        series: [{ name: 'Submissions', data: data.by_division.map(d => d.y) }]
      });

      Highcharts.chart('contractTypeChart', {
        chart: { type: 'column' },
        title: { text: 'PPA Completion by Contract Type', style: { color: '#7A7A7A' } },
        xAxis: { categories: data.by_contract.map(c => c.name) },
        yAxis: { title: { text: 'PPAs Submitted' } },
        colors: ['#119A48', '#911C39', '#C3A366', '#001011', '#fbb924', '#385CAD', '#194F90'],
        series: [{ name: 'PPAs', data: data.by_contract.map(c => c.y) }]
      });
    }).fail(function () {
      alert("Failed to load dashboard data. Please try again.");
    });
  }
</script>
