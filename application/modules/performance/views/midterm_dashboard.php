<style>
  @media print {
    body {
      background: #fff !important;
      color: #000 !important;
      font-size: 12pt;
    }

    #dashboardFilters,
    .navbar,
    .btn,
    footer,
    .select2-container {
      display: none !important;
    }

    .container-fluid {
      width: 100%;
      padding: 0;
      margin: 0 auto;
    }

    .row {
      justify-content: center;
      align-items: center;
    }

    .card,
    .shadow,
    .rounded,
    .bg-white {
      box-shadow: none !important;
      background: #911C39 !important;
      color: #fff !important;
      border: 1px solid #ccc !important;
    }

    .card .card-body {
      color: #fff !important;
    }

    .highcharts-title {
      fill: #000 !important;
    }

    .highcharts-legend,
    .highcharts-credits {
      display: none !important;
    }

    .text-white {
      color: #fff !important;
    }

    .text-center-print {
      text-align: center !important;
    }
  }
</style>

<script>
  const base_url = "<?= base_url(); ?>";
  Highcharts.setOptions({ credits: { enabled: false } });
</script>

<div class="container-fluid py-0 px-4" id="dashboardContent">

  <!-- Filters -->
  <div class="row mb-4" id="dashboardFilters">
    <div class="col-md-4">
      <label class="form-label fw-bold text-secondary">Division</label>
      <select id="divisionFilter" class="form-select border-success select2">
        <option value="">All Divisions</option>
        <?php foreach ($divisions as $div): ?>
          <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-bold text-secondary">Performance Period</label>
      <select id="periodFilter" class="form-select border-success select2">
        <option value="">All Periods</option>
      </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
      <button class="btn btn-outline-success w-100" onclick="window.print()">
        <i class="fa fa-print"></i> Print Report
      </button>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4" id="summaryCards"></div>

  <!-- Charts Section -->
  <div class="row g-4">
    <div class="col-lg-6"><div id="approvalBreakdownChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="contractTypeChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="trainingCategoriesChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-6"><div id="avgApprovalChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-12"><div id="divisionWiseChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-12"><div id="trainingSkillsChart" class="shadow-sm rounded p-3 bg-white"></div></div>
    <div class="col-lg-12"><div id="submissionTrendChart" class="shadow-sm rounded p-3 bg-white"></div></div>
  </div>
</div>

<script>
  $(function () {
    loadMidtermDashboard();
    $('#divisionFilter, #periodFilter').on('change', loadMidtermDashboard);
  });

  function loadMidtermDashboard() {
    const divisionId = $('#divisionFilter').val();
    const period = $('#periodFilter').val();

    $.getJSON(base_url + 'performance/midterm/fetch_ppa_dashboard_data', {
      division_id: divisionId,
      period: period
    }, function (data) {

      if ($('#periodFilter option').length <= 1 && data.periods) {
        data.periods.forEach(period => {
          $('#periodFilter').append(`<option value="${period}" ${period === data.current_period ? 'selected' : ''}>${period}</option>`);
        });
      }

      const cards = [
        { label: 'Midterm Reviews', icon: 'fa-file-alt', color: '#911C39', value: data.total, type: 'total' },
        { label: 'Approved Midterm Reviews', icon: 'fa-check-circle', color: '#119A48', value: data.approved, type: 'approved' },
        { label: 'Staff with PDPs', icon: 'fa-user-check', color: '#385CAD', value: data.staff_with_pdps, type: 'with_pdp' },
        { label: 'Staff Without Midterm Review', icon: 'fa-user-times', color: '#C3A366', value: data.staff_without_Midterms, type: 'without_ppa' }
      ];

      $('#summaryCards').html(cards.map(card => `
        <div class="col mb-3">
          <a href="javascript:void(0)" class="view-staff-link text-decoration-none" data-type="${card.type}">
            <div class="card shadow-sm rounded-2 border-0 text-white" style="background-color:${card.color}">
              <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                  <p class="mb-0 text-white fw-bold">${card.label}</p>
                  <h4 class="fw-bold text-white">${card.value}</h4>
                </div>
                <div class="fs-1"><i class="fa ${card.icon}"></i></div>
              </div>
            </div>
          </a>
        </div>
      `).join(''));

      Highcharts.chart('approvalBreakdownChart', {
        chart: { type: 'pie' },
        title: { text: 'Midterm Approval Status Breakdown', style: { color: '#119A48' } },
        colors: ['#119A48', '#fbb924'],
        series: [{
          name: 'Status',
          data: [
            { name: 'Approved', y: parseInt(data.approved) },
            { name: 'Pending', y: parseInt(data.submitted) }
          ]
        }]
      });

      Highcharts.chart('contractTypeChart', {
        chart: { type: 'bar' },
        title: { text: 'Midterm PPA Completion by Contract Type', style: { color: '#7A7A7A' } },
        xAxis: { categories: data.by_contract.map(c => c.name) },
        yAxis: { title: { text: 'Midterm Reviews Submitted' } },
        colors: ['#911C39'],
        series: [{ name: 'Midterm Reviews', data: data.by_contract.map(c => parseInt(c.y)) }]
      });

      Highcharts.chart('avgApprovalChart', {
        chart: { type: 'solidgauge' },
        title: { text: 'Avg Midterm Approval Time (Days)', style: { color: '#001011' } },
        pane: {
          center: ['50%', '85%'],
          size: '140%',
          startAngle: -90,
          endAngle: 90,
          background: {
            backgroundColor: '#f4f4f4',
            innerRadius: '60%',
            outerRadius: '100%',
            shape: 'arc'
          }
        },
        tooltip: { enabled: false },
        yAxis: {
          min: 0,
          max: 30,
          stops: [[0.1, '#119A48'], [0.5, '#fbb924'], [0.9, '#911C39']],
          lineWidth: 0,
          tickWidth: 0,
          labels: { enabled: false }
        },
        series: [{
          name: 'Days',
          data: [parseFloat(data.avg_approval_days)],
          dataLabels: {
            format: '<div style="text-align:center"><span style="font-size:1.5em;color:#5F5F5F">{y}</span><br/><span style="font-size:12px;color:silver">days</span></div>'
          }
        }]
      });

      Highcharts.chart('divisionWiseChart', {
        chart: { type: 'column' },
        title: { text: 'Midterm Submissions by Division', style: { color: '#911C39' } },
        xAxis: { categories: data.by_division.map(d => d.name) },
        yAxis: { title: { text: 'Midterm Submissions' } },
        colors: ['#119A48'],
        series: [{ name: 'Midterm Submissions', data: data.by_division.map(d => parseInt(d.y)) }]
      });

      Highcharts.chart('trainingCategoriesChart', {
        chart: { type: 'bar' },
        title: { text: 'Training Categories from Midterm PPA', style: { color: '#001011' } },
        xAxis: { categories: data.training_categories.map(c => c.name) },
        yAxis: { title: { text: 'Requests' } },
        colors: ['#C3A366'],
        series: [{ name: 'Requests', data: data.training_categories.map(c => parseInt(c.y)) }]
      });

      Highcharts.chart('trainingSkillsChart', {
        chart: { type: 'bar' },
        title: { text: 'Top 10 Training Skills Requested (Midterm)', style: { color: '#911C39' } },
        xAxis: {
          categories: data.training_skills.map(s => s.name),
          labels: { rotation: -45 }
        },
        yAxis: { title: { text: 'Mentions' } },
        colors: ['#fbb924'],
        series: [{ name: 'Skills', data: data.training_skills.map(s => parseInt(s.y)) }]
      });

      Highcharts.chart('submissionTrendChart', {
        chart: { type: 'area' },
        title: { text: 'Midterm Submission Trend Over Time', style: { color: '#385CAD' } },
        xAxis: {
          categories: data.trend.map(item => item.date),
          tickmarkPlacement: 'on',
          title: { text: 'Date' }
        },
        yAxis: { title: { text: 'Midterm Submissions' } },
        colors: ['#119A48'],
        series: [{ name: 'Midterm Submissions', data: data.trend.map(item => parseInt(item.count)) }]
      });

    }).fail(() => alert("Failed to load dashboard data. Please try again."));
  }

  $(document).on('click', '.view-staff-link', function () {
    const type = $(this).data('type');
    const divisionId = $('#divisionFilter').val();
    const period = $('#periodFilter').val();
    window.open(`${base_url}performance/midterm/staff_list?type=${type}&division_id=${divisionId}&period=${period}`, '_blank');
  });
</script>
