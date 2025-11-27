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
  Highcharts.setOptions({ 
    credits: { enabled: false },
    title: { text: '' }
  });
</script>

<div class="container-fluid py-4 px-4" id="dashboardContent">
  <!-- Dashboard Navigation Tabs -->
  <?php $this->load->view('performance/partials/dashboard_tabs'); ?>
  
  <!-- Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="mb-1" style="color: #911C39; font-weight: 600;">
            <i class="fa fa-chart-line me-2"></i>Endterm Performance Dashboard
          </h2>
          <p class="text-muted mb-0">Comprehensive analytics and insights for endterm performance reviews</p>
        </div>
        <button class="btn btn-outline-primary" onclick="window.print()">
          <i class="fa fa-print me-2"></i> Print Report
        </button>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="row mb-4" id="dashboardFilters">
    <div class="col-md-4">
      <label class="form-label fw-bold" style="color: #495057;">
        <i class="fa fa-building me-1"></i>Division
      </label>
      <select id="divisionFilter" class="form-select select2" style="border-color: #119A48;">
        <option value="">All Divisions</option>
        <?php foreach ($divisions as $div): ?>
          <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-bold" style="color: #495057;">
        <i class="fa fa-money-bill-wave me-1"></i>Funder
      </label>
      <select id="funderFilter" class="form-select select2" style="border-color: #119A48;">
        <option value="">All Funders</option>
        <?php foreach ($funders as $funder): ?>
          <option value="<?= $funder->funder_id ?>"><?= $funder->funder ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-bold" style="color: #495057;">
        <i class="fa fa-calendar-alt me-1"></i>Performance Period
      </label>
      <select id="periodFilter" class="form-select select2" style="border-color: #119A48;">
        <option value="">Loading periods...</option>
      </select>
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <button class="btn w-100" style="background-color: #119A48; color: white;" onclick="loadEndtermDashboard()">
        <i class="fa fa-sync-alt me-1"></i>Refresh
      </button>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4" id="summaryCards"></div>

  <!-- Charts Section -->
  <div class="row g-4">
    <!-- First Row: Approval Status and Average Score -->
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #119A48; font-weight: 600;">
            <i class="fa fa-check-circle me-2"></i>Approval Status Breakdown
          </h5>
          <div id="approvalBreakdownChart" style="min-height: 300px;"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #911C39; font-weight: 600;">
            <i class="fa fa-star me-2"></i>Average Performance Score
          </h5>
          <div id="avgScoreChart" style="min-height: 300px;"></div>
        </div>
      </div>
    </div>
    
    <!-- Second Row: Score Bands Distribution -->
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #385CAD; font-weight: 600;">
            <i class="fa fa-chart-bar me-2"></i>Performance Score Distribution
          </h5>
          <div id="scoreBandsChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
    
    <!-- Third Row: Average Score by Division and Contract Type -->
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #119A48; font-weight: 600;">
            <i class="fa fa-building me-2"></i>Average Score by Division
          </h5>
          <div id="divisionScoreChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #7A7A7A; font-weight: 600;">
            <i class="fa fa-file-contract me-2"></i>Endterm Completion by Contract Type
          </h5>
          <div id="contractTypeChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
    
    <!-- Fourth Row: Average Score by Funder -->
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #C3A366; font-weight: 600;">
            <i class="fa fa-money-bill-wave me-2"></i>Average Score by Funder
          </h5>
          <div id="funderScoreChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
    
    <!-- Fifth Row: Submission Trends -->
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #385CAD; font-weight: 600;">
            <i class="fa fa-chart-line me-2"></i>Endterm Submission Trend Over Time
          </h5>
          <div id="submissionTrendChart" style="min-height: 350px;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  $(function () {
    loadEndtermDashboard();
    $('#divisionFilter, #funderFilter, #periodFilter').on('change', loadEndtermDashboard);
  });

  function loadEndtermDashboard() {
    const divisionId = $('#divisionFilter').val();
    const funderId = $('#funderFilter').val();
    const period = $('#periodFilter').val();

    $.getJSON(base_url + 'performance/endterm/fetch_ppa_dashboard_data', {
      division_id: divisionId,
      funder_id: funderId,
      period: period
    }, function (data) {

      // Populate period filter with distinct periods
      if (data.periods && data.periods.length > 0) {
        $('#periodFilter').html('<option value="">All Periods</option>');
        data.periods.forEach(period => {
          const periodDisplay = period.replace(/-/g, ' ');
          const isSelected = period === data.current_period ? 'selected' : '';
          $('#periodFilter').append(`<option value="${period}" ${isSelected}>${periodDisplay}</option>`);
        });
      } else {
        $('#periodFilter').html('<option value="">No periods available</option>');
      }

      const cards = [
        { label: 'Endterm Reviews', icon: 'fa-file-alt', color: '#911C39', value: data.total, type: 'total' },
        { label: 'Approved Endterm Reviews', icon: 'fa-check-circle', color: '#119A48', value: data.approved, type: 'approved' },
        { label: 'Require Calibration', icon: 'fa-exclamation-triangle', color: '#fbb924', value: data.staff_require_calibration, type: 'require_calibration' },
        { label: 'Staff Without Endterm Review', icon: 'fa-user-times', color: '#C3A366', value: data.staff_without_endterms, type: 'without_ppa' }
      ];

      $('#summaryCards').html(cards.map(card => `
        <div class="col mb-3">
          <a href="javascript:void(0)" class="view-staff-link text-decoration-none" data-type="${card.type}">
            <div class="card shadow-sm rounded-3 border-0 text-white h-100" style="background: linear-gradient(135deg, ${card.color} 0%, ${card.color}dd 100%); transition: transform 0.2s;">
              <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                  <p class="mb-2 text-white-50 fw-semibold small text-uppercase">${card.label}</p>
                  <h2 class="fw-bold text-white mb-0">${card.value || 0}</h2>
                </div>
                <div class="fs-1 opacity-75"><i class="fa ${card.icon}"></i></div>
              </div>
            </div>
          </a>
        </div>
      `).join(''));
      
      // Add hover effect
      $('.view-staff-link').hover(
        function() { $(this).find('.card').css('transform', 'translateY(-5px)'); },
        function() { $(this).find('.card').css('transform', 'translateY(0)'); }
      );

      // Approval Status Breakdown Chart
      Highcharts.chart('approvalBreakdownChart', {
        chart: { type: 'pie', height: 300 },
        title: { text: '' },
        colors: ['#119A48', '#fbb924'],
        plotOptions: {
          pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
              enabled: true,
              format: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)'
            }
          }
        },
        series: [{
          name: 'Status',
          data: [
            { name: 'Approved', y: parseInt(data.approved || 0) },
            { name: 'Pending', y: parseInt(data.submitted || 0) }
          ]
        }]
      });

      // Average Score Gauge Chart
      Highcharts.chart('avgScoreChart', {
        chart: { type: 'solidgauge', height: 300 },
        title: { text: '' },
        pane: {
          center: ['50%', '75%'],
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
          max: 100,
          stops: [
            [0.1, '#911C39'],   // Poor (0-50)
            [0.51, '#fbb924'],  // Satisfactory (51-79)
            [0.8, '#119A48']    // Outstanding (80-100)
          ],
          lineWidth: 0,
          tickWidth: 0,
          labels: { enabled: false }
        },
        series: [{
          name: 'Score',
          data: [parseFloat(data.avg_score || 0)],
          dataLabels: {
            format: '<div style="text-align:center"><span style="font-size:2em;color:#5F5F5F;font-weight:bold">{y}</span><br/><span style="font-size:14px;color:silver">out of 100</span></div>',
            borderWidth: 0,
            y: 20
          }
        }]
      });

      // Score Bands Distribution Chart
      Highcharts.chart('scoreBandsChart', {
        chart: { type: 'column', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: ['Outstanding Performance<br/>(80-100)', 'Satisfactory Performance<br/>(51-79)', 'Poor Performance<br/>(0-50)', 'Not Rated – New in Position'],
          labels: { style: { fontSize: '12px' } }
        },
        yAxis: {
          title: { text: 'Number of Staff' },
          allowDecimals: false
        },
        colors: ['#119A48', '#385CAD', '#911C39', '#C3A366'],
        plotOptions: {
          column: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Number of Staff',
          data: [
            parseInt(data.score_bands?.outstanding || 0),
            parseInt(data.score_bands?.satisfactory || 0),
            parseInt(data.score_bands?.poor || 0),
            parseInt(data.score_bands?.not_rated || 0)
          ]
        }]
      });

      // Average Score by Division Chart
      Highcharts.chart('divisionScoreChart', {
        chart: { type: 'bar', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.division_averages || []).map(d => d.name),
          title: { text: null }
        },
        yAxis: {
          title: { text: 'Average Score' },
          min: 0,
          max: 100
        },
        colors: ['#119A48'],
        plotOptions: {
          bar: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Average Score',
          data: (data.division_averages || []).map(d => parseFloat(d.avg_score || 0))
        }]
      });

      // Contract Type Chart
      Highcharts.chart('contractTypeChart', {
        chart: { type: 'bar', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.by_contract || []).map(c => c.name),
          title: { text: null }
        },
        yAxis: {
          title: { text: 'Endterm Reviews Submitted' },
          allowDecimals: false
        },
        colors: ['#911C39'],
        plotOptions: {
          bar: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Endterm Reviews',
          data: (data.by_contract || []).map(c => parseInt(c.y || 0))
        }]
      });

      // Average Score by Funder Chart
      Highcharts.chart('funderScoreChart', {
        chart: { type: 'bar', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.funder_averages || []).map(f => f.name),
          title: { text: null }
        },
        yAxis: {
          title: { text: 'Average Score' },
          min: 0,
          max: 100
        },
        colors: ['#C3A366'],
        plotOptions: {
          bar: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Average Score',
          data: (data.funder_averages || []).map(f => parseFloat(f.avg_score || 0))
        }]
      });

      // Submission Trend Chart
      Highcharts.chart('submissionTrendChart', {
        chart: { type: 'area', height: 350 },
        title: { text: '' },
        xAxis: {
          categories: (data.trend || []).map(item => item.date),
          tickmarkPlacement: 'on',
          title: { text: 'Date' }
        },
        yAxis: {
          title: { text: 'Endterm Submissions' },
          allowDecimals: false
        },
        colors: ['#119A48'],
        plotOptions: {
          area: {
            fillOpacity: 0.5,
            marker: { enabled: false }
          }
        },
        series: [{
          name: 'Endterm Submissions',
          data: (data.trend || []).map(item => parseInt(item.count || 0))
        }]
      });

    }).fail(() => alert("Failed to load dashboard data. Please try again."));
  }

  $(document).on('click', '.view-staff-link', function () {
    const type = $(this).data('type');
    const divisionId = $('#divisionFilter').val();
    const funderId = $('#funderFilter').val();
    const period = $('#periodFilter').val();
    window.open(`${base_url}performance/endterm/staff_list?type=${type}&division_id=${divisionId}&funder_id=${funderId}&period=${period}`, '_blank');
  });
</script>
