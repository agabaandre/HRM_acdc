<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">

  <div class="col">
    <!--<div class="card rounded-4 bg-gradient-worldcup">-->
    <div class="card rounded-4" style="background:rgba(52, 143, 65, 1);">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h5>
              <p class="mb-0 text-white">Main Staff
              </p>
            </h5>
            <h5 style="color:#FFFFFF;">
              <?php echo $staff . ' '; ?>
              <p class="medium"> <a href="<?php echo base_url() ?>staff" style="color:#FFFFFF;"> View</a></p>
            </h5>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-wallet'></i>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="col">
    <!--<div class="card rounded-4 bg-gradient-worldcup">-->
    <div class="card rounded-4" style=" background:#4a4a4a;">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h5>
              <p class="mb-0 text-white">Contracts Due <b style="font-size:9px; color:orange;">
                  < 2 Months</b>
              </p>
            </h5>
            <h5 style="color:#FFFFFF;">
              <?php echo $expired; ?>
              <p class="medium"> <a href="<?= base_url() ?>staff/contract_status/2" style="color:#FFFFFF;"> View</a></p>
            </h5>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-wallet'></i>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="col">
    <!--<div class="card rounded-4 bg-gradient-smile">-->
    <div class="card rounded-4" style=" background:#b4a269;">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h5>
              <p class="mb-0 text-white">Expired Contracts</p>
            </h5>
            <h5 style="color:#FFFFFF;">
              <?php echo $expired; ?>
              <p class="medium"> <a href="<?= base_url() ?>staff/contract_status/3" style="color:#FFFFFF;"> View</a></p>
            </h5>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-wallet'></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col">
    <!--<div class="card rounded-4 bg-gradient-pinki">-->
    <div class="card rounded-4" style=" background:#9F2241;">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h5>
              <p class="mb-0 text-white">Member States</p>
            </h5>
            <h5 style="color:#FFFFFF;">
              <?php echo $member_states; ?>
              <p class="medium"> <a href="<?= base_url() ?>geographical/countries" style="color:#FFFFFF;"> View</a></p>
            </h5>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-bar-chart-alt-2'></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!--end row-->


<div class="row">
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-4 w-100">
      <div class="card-body">
        <div class="d-flex align-items-cente">
          <div>
            <h6 class="mb-0">Main Staff Gender Distribution</h6>
          </div>

        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container"></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-4 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff by Member State</h6>
          </div>

        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container5"></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
</div>
<!--end row-->
<div class="row">
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-4 w-100">
      <div class="card-body">
        <div class="d-flex align-items-cente">
          <div>
            <h6 class="mb-0">Staff by Contract Type</h6>
          </div>

        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container3"></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-4 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff by Division</h6>
          </div>

        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container4"></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
</div>



<script>
  Highcharts.setOptions({
    colors: ['#b4a269', '#28a745', '#6905AD', '#0913AC', '#b4a269', '#a3a3a3']
  });
  // Radialize the colors
  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      // Start out with a darkened base color (negative brighten), and end
      // up with a much brighter color
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());

  // Build the chart
  Highcharts.chart('container', {
    chart: {
      plotBackgroundColor: null,
      plotBorderWidth: null,
      plotShadow: false,
      type: 'pie'
    },
    title: {
      text: ''
    },
    tooltip: {
      pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
    },
    accessibility: {
      point: {
        valueSuffix: '%'
      }
    },
    plotOptions: {
      pie: {
        allowPointSelect: true,
        size: '70%',
        cursor: 'pointer',
        dataLabels: {
          enabled: true,
          format: '{point.y:1f}<br><b>{point.name}</b><br>{point.percentage:.1f} %',
          distance: -60,
          filter: {
            property: 'percentage',
            operator: '>',
            value: 4
          },
          style: {
            fontSize: '15px'
          }
        }
      }
    },
    series: [{
      name: 'Percentage',
      data: <?php echo json_encode($data_points, JSON_NUMERIC_CHECK); ?>
    }],

    credits: {
      enabled: false
    }
  });
</script>

<script>
  Highcharts.setOptions({
    colors: ['#b4a269', '#a3a3a3']
  });
  // Radialize the colors
  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      // Start out with a darkened base color (negative brighten), and end
      // up with a much brighter color
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());

  // Build the chart
  // Data retrieved from https://netmarketshare.com
</script>
<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container3', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: <?php echo json_encode($staff_by_contract['contract_type'], JSON_NUMERIC_CHECK); ?>

        ,
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },

    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0.2,
        borderWidth: 0
      },

    },
    series: [{
      name: 'Contract Types',
      data: <?php echo json_encode($staff_by_contract['value'], JSON_NUMERIC_CHECK); ?>


    }],
    credits: {
      enabled: false
    }
  });
</script>


<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container4', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: <?php echo json_encode($staff_by_division['division'], JSON_NUMERIC_CHECK); ?>

        ,
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },

    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0,
        borderWidth: 0
      },

    },
    series: [{
      name: 'Divisions',
      data: <?php echo json_encode($staff_by_division['value'], JSON_NUMERIC_CHECK); ?>


    }],
    credits: {
      enabled: false
    }
  });
</script>

<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container5', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: <?php echo json_encode($staff_by_member_state['member_states'], JSON_NUMERIC_CHECK); ?>

        ,
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },

    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0.2,
        borderWidth: 0
      },

    },
    series: [{
      name: 'Member States',
      data: <?php echo json_encode($staff_by_member_state['value'], JSON_NUMERIC_CHECK); ?>


    }],
    credits: {
      enabled: false
    }
  });
</script>