<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">

  <div class="col">
    <!--<div class="card rounded-4 bg-gradient-rainbow">-->
    <div class="card rounded-4" style="background:rgba(52, 143, 65, 1);">
      <div class=" card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h5>
              <p class="mb-0 text-white">Main Staff</p>
            </h5>
            <h2 style="color:#FFFFFF;">
              <?php echo $staff . ' '; ?>
              <p class="medium"> <a href="staff.php" style="color:#FFFFFF;"> View</a></p>
            </h2>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-cart'></i>
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
              <p class="mb-0 text-white">Contracts Due <small> (< 2 months)</small>
              </p>
            </h5>
            <h2 style="color:#FFFFFF;">
              <?php echo $two_months . ' '; ?>
              <font size="4"> <a href="due.php" style="color:#FFFFFF;"> View</a></font>
            </h2>
          </div>
          <div class="fs-1 text-white"><i class='bx bxs-cart'></i>
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
            <h2 style="color:#FFFFFF;">
              <?php echo $expired; ?>
             <p class="medium">  <a href="expired.php" style="color:#FFFFFF;"> View</a></p>
            </h2>
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
            <h2 style="color:#FFFFFF;">
              <?php echo $member_states; ?>
             <p class="medium">  <a href="ms.php" style="color:#FFFFFF;"> View</a></p>
            </h2>
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

<!--end page wrapper -->
<!--start overlay-->
<div class="overlay toggle-icon"></div>
<!--end overlay-->
<!--Start Back To Top Button--> <a href="javaScript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>
<!--End Back To Top Button-->

</div>
<!--end wrapper-->
<!--start switcher-->
<div class="switcher-wrapper">
  <div class="switcher-btn"> <i class='bx bx-cog bx-spin'></i>
  </div>
  <div class="switcher-body">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 text-uppercase">Theme Customizer</h5>
      <button type="button" class="btn-close ms-auto close-switcher" aria-label="Close"></button>
    </div>
    <hr />
    <h6 class="mb-0">Theme Styles</h6>
    <hr />
    <div class="d-flex align-items-center justify-content-between">
      <div class="form-check">
        <input class="form-check-input" type="radio" name="flexRadioDefault" id="lightmode" checked>
        <label class="form-check-label" for="lightmode">Light</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="flexRadioDefault" id="darkmode">
        <label class="form-check-label" for="darkmode">Dark</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="flexRadioDefault" id="semidark">
        <label class="form-check-label" for="semidark">Semi Dark</label>
      </div>
    </div>
    <hr />
    <div class="form-check">
      <input class="form-check-input" type="radio" id="minimaltheme" name="flexRadioDefault">
      <label class="form-check-label" for="minimaltheme">Minimal Theme</label>
    </div>
    <hr />
    <h6 class="mb-0">Header Colors</h6>
    <hr />
    <div class="header-colors-indigators">
      <div class="row row-cols-auto g-3">
        <div class="col">
          <div class="indigator headercolor1" id="headercolor1"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor2" id="headercolor2"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor3" id="headercolor3"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor4" id="headercolor4"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor5" id="headercolor5"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor6" id="headercolor6"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor7" id="headercolor7"></div>
        </div>
        <div class="col">
          <div class="indigator headercolor8" id="headercolor8"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="assets/plugins/apexcharts-bundle/js/apexcharts.min.js"></script>
<script src="assets/plugins/chartjs/js/Chart.min.js"></script>
<script src="assets/plugins/chartjs/js/Chart.extension.js"></script>
<script src="assets/js/index2.js"></script>
<!--app JS-->
<script src="assets/js/app.js"></script>

<script>

  Highcharts.setOptions({
    colors: ['#b4a269', '#28a745', '#6905AD', '#0913AC', '#b4a269', '#a3a3a3']
  });
  // Radialize the colors
  var pieColors = (function () {
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
      data: <?php echo json_encode($dataPoints, JSON_NUMERIC_CHECK); ?>
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
  var pieColors = (function () {
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
  Highcharts.chart('container2', {
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
          distance: -54,
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
      data: <?php echo json_encode($dataPoint, JSON_NUMERIC_CHECK); ?>
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
      categories:
        <?php echo json_encode($contract_type, JSON_NUMERIC_CHECK); ?>

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
      data: <?php echo json_encode($value, JSON_NUMERIC_CHECK); ?>


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
      categories:
        <?php echo json_encode($division, JSON_NUMERIC_CHECK); ?>

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
      data: <?php echo json_encode($value2, JSON_NUMERIC_CHECK); ?>


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
      categories:
        <?php echo json_encode($member_states, JSON_NUMERIC_CHECK); ?>

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
      data: <?php echo json_encode($number, JSON_NUMERIC_CHECK); ?>


    }],
    credits: {
      enabled: false
    }
  });

</script>
</body>

</html>