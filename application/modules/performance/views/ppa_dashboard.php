<?php $this->load->view('ppa_tabs'); ?>

<!-- Base URL for JS -->
<script>
  const base_url = "<?= base_url(); ?>";
</script>


<script>
  Highcharts.setOptions({
    credits: { enabled: false }
  });
</script>

<!-- Dashboard Layout -->
<div class="container-fluid py-4 px-5">

  <!-- Filters -->
  <div class="row align-items-end mb-4">
    <div class="col-md-4">
      <label for="divisionFilter" class="form-label fw-bold">Filter by Division:</label>
      <select id="divisionFilter" class="form-control select2 border-primary">
        <option value="">All Divisions</option>
        <?php foreach ($divisions as $div): ?>
          <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Charts -->
  <div class="row g-4">
    <div class="col-lg-6">
      <div id="totalSubmissionsChart" class="shadow rounded p-3 bg-white"></div>
    </div>
    <div class="col-lg-6">
      <div id="approvalBreakdownChart" class="shadow rounded p-3 bg-white"></div>
    </div>
    <div class="col-lg-12">
      <div id="submissionTrendChart" class="shadow rounded p-3 bg-white"></div>
    </div>
    <div class="col-lg-6">
      <div id="avgApprovalChart" class="shadow rounded p-3 bg-white"></div>
    </div>
    <div class="col-lg-6">
      <div id="divisionWiseChart" class="shadow rounded p-3 bg-white"></div>
    </div>
  </div>
</div>

<!-- Chart Script -->
<script>
  $(document).ready(function () {
    loadPPADashboard();

    $('#divisionFilter').on('change', function () {
      loadPPADashboard($(this).val());
    });
  });

  function loadPPADashboard(divId = '') {
    $.getJSON(base_url + 'performance/fetch_ppa_dashboard_data', { division_id: divId }, function (data) {
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
        series: [{
          name: 'Submissions',
          data: data.trend.map(item => item.count)
        }]
      });

      Highcharts.chart('avgApprovalChart', {
        chart: { type: 'solidgauge' },
        title: { text: 'Avg Approval Time (Days)', style: { color: '#5F5F5F' } },
        pane: {
          center: ['50%', '85%'],
          size: '140%',
          startAngle: -90,
          endAngle: 90,
          background: {
            backgroundColor: '#EEE',
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
          data: [data.avg_approval_days],
          dataLabels: {
            format: '<div style="text-align:center"><span style="font-size:1.5em;color:#5F5F5F">{y}</span><br/><span style="font-size:12px;color:silver">days</span></div>'
          }
        }]
      });

      Highcharts.chart('divisionWiseChart', {
        chart: { type: 'bar' },
        title: { text: 'Submissions by Division', style: { color: '#119A48' } },
        xAxis: {
          categories: data.by_division.map(d => d.name),
          title: { text: null }
        },
        yAxis: { min: 0, title: { text: 'Submissions' } },
        colors: ['#119A48'],
        series: [{
          name: 'Submissions',
          data: data.by_division.map(d => d.y)
        }]
      });
    }).fail(function () {
      alert('Failed to load dashboard data. Please try again.');
    });
  }
</script>
