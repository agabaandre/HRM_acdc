

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">
  <div class="col">
    <div class="card rounded-1" <?=getRandomAUColor()?>>
      <a href="<?php echo base_url() ?>staff">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Main Staff <b style="font-size:9px; color:black;">&lt; (Active & Due)</b></p>
              </h5>
              <h5 style="color:#FFFFFF;">
                <?php echo $staff . ' '; ?>
              </h5>
            </div>
            <div class="fs-1 text-white"><i class='bx bxs-wallet'></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="col">
    <div class="card rounded-1" <?=getRandomAUColor()?>>
      <a href="<?= base_url() ?>staff/contract_status/2">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Contracts Due <b style="font-size:9px; color:black;">&lt; 3 Months</b></p>
              </h5>
              <h5 style="color:#FFFFFF;">
                <?php echo $two_months; ?>
              </h5>
            </div>
            <div class="fs-1 text-white"><i class='bx bxs-wallet'></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>
  <div class="col">
    <div class="card rounded-1" <?=getRandomAUColor()?>>
      <a href="<?= base_url() ?>staff/contract_status/7">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Under Renewal</p>
              </h5>
              <h5 style="color:#FFFFFF;">
                <?php echo $staff_renewal; ?>
              </h5>
            </div>
            <div class="fs-1 text-white"><i class='bx bxs-bar-chart-alt-2'></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="col">
    <div class="card rounded-1" <?=getRandomAUColor()?>>
      <a href="<?= base_url() ?>staff/contract_status/3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Expired Contracts</p>
              </h5>
              <h5 style="color:#FFFFFF;">
                <?php echo $expired; ?>
              </h5>
            </div>
            <div class="fs-1 text-white"><i class='bx bxs-wallet'></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>


</div>

<!--end row-->

<div class="row">
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
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
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
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
  
</div>
<!--end row-->

<div class="row">
 <!-- //--- -->
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
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
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
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
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff Birthdays</h6>
          </div>
        </div>
        <div>
          
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Today</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Tomorrow</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">Next 7 days</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="month-tab" data-bs-toggle="tab" data-bs-target="#month" type="button" role="tab" aria-controls="month" aria-selected="false">Next 30 days</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Today</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($today as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.$data->photo;
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->grade ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->duty_station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Tomorrow</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 0;
                        foreach ($tomorrow as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.$data->photo;
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->grade ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->duty_station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->division_name, 20) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 7 days</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($week as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.$data->photo;
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->grade ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->duty_station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="month" role="tabpanel" aria-labelledby="month-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 30 days</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($month as $data) : ?> 
                        <tr>

                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.$data->photo;
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->grade ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->duty_station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        
        </div>
      </div>
    </div>
  </div>
</div>
<!--end row-->

<script>
  Highcharts.setOptions({
    colors: ['#b4a269', '#28a745', '#6905AD', '#0913AC', '#b4a269', '#a3a3a3']
  });

  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());

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

  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());
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
      categories: <?php echo json_encode($staff_by_contract['contract_type'], JSON_NUMERIC_CHECK); ?>,
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
      }
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
      categories: <?php echo json_encode($staff_by_division['division'], JSON_NUMERIC_CHECK); ?>,
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
      }
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
      categories: <?php echo json_encode($staff_by_member_state['member_states'], JSON_NUMERIC_CHECK); ?>,
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
      }
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