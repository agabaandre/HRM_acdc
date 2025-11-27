<style>
  .calendar-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    padding: 1.5rem;
  }

  .fc-theme-standard td, .fc-theme-standard th {
    border-color: #e0e0e0;
  }

  .fc-button-primary {
    background-color: #119A48 !important;
    border-color: #119A48 !important;
  }

  .fc-button-primary:hover {
    background-color: #0d7a38 !important;
    border-color: #0d7a38 !important;
  }

  .fc-button-primary:not(:disabled):active {
    background-color: #0a5d2a !important;
    border-color: #0a5d2a !important;
  }

  .fc-daygrid-event {
    border-radius: 4px;
    padding: 2px 6px;
  }

  .fc-event-title {
    font-weight: 500;
  }

  @media print {
    #dashboardFilters,
    .navbar,
    .btn,
    footer,
    .select2-container {
      display: none !important;
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

<div class="container-fluid py-4 px-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); min-height: 100vh;">
  <!-- African Union Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm" style="background: #119A48; border-radius: 2px;">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="me-3">
                <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC Logo" style="max-height: 60px; filter: brightness(0) invert(1);">
              </div>
              <div>
                <h2 class="mb-1 text-white" style="font-weight: 700; font-size: 28px;">
                  <i class="fa fa-home me-2"></i>Main Dashboard
                </h2>
                <p class="text-white mb-0" style="opacity: 0.9; font-size: 14px;">
                  <i class="fa fa-map-marker-alt me-1"></i>Staff Portal | Africa CDC Central Business Platform
                </p>
              </div>
            </div>
            <button class="btn btn-light" onclick="window.print()" style="border-radius: 8px; font-weight: 600;">
              <i class="fa fa-print me-2"></i> Print Report
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Performance Dashboard Tabs -->
  <?php 
  // Check if user has permission to view performance dashboards (permission 82)
  $session = $this->session->userdata('user');
  $permissions = isset($session->permissions) ? $session->permissions : [];
  $has_performance_permission = is_array($permissions) && in_array('82', $permissions);
  ?>
  <?php if ($has_performance_permission): ?>
    <div class="row mb-4">
      <div class="col-12">
        <ul class="nav nav-tabs" style="border-bottom: 2px solid #e0e0e0; background-color: #fff; border-radius: 8px 8px 0 0; padding: 0 10px;">
          <li class="nav-item">
            <a class="nav-link active" style="color: #119A48; border-bottom: 3px solid #119A48; font-weight: 600; padding: 14px 24px; background-color: transparent;">
              <i class="fa fa-home me-2"></i>Main Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= base_url('performance/ppa_dashboard') ?>" style="color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;">
              <i class="fa fa-chart-pie me-2"></i>PPA Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= base_url('performance/midterm/ppa_dashboard') ?>" style="color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;">
              <i class="fa fa-chart-bar me-2"></i>Midterm Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= base_url('performance/endterm/ppa_dashboard') ?>" style="color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;">
              <i class="fa fa-chart-line me-2"></i>Endterm Dashboard
            </a>
          </li>
        </ul>
      </div>
    </div>
    <style>
      .nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
      }
      .nav-tabs .nav-link:hover:not(.active) {
        color: #119A48 !important;
        border-bottom-color: #a3e6b9 !important;
        background-color: #f8f9fa;
      }
      .nav-tabs .nav-link.active {
        color: #119A48 !important;
        border-bottom-color: #119A48 !important;
        background-color: transparent;
      }
      @media print {
        .nav-tabs {
          display: none !important;
        }
      }
    </style>
  <?php endif; ?>

  <!-- Filters -->
  <div class="row mb-4" id="dashboardFilters">
    <div class="col-md-3">
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
    <div class="col-md-3">
      <label class="form-label fw-bold" style="color: #495057;">
        <i class="fa fa-map-marker-alt me-1"></i>Duty Station
      </label>
      <select id="dutyStationFilter" class="form-select select2" style="border-color: #119A48;">
        <option value="">All Duty Stations</option>
        <?php foreach ($duty_stations as $station): ?>
          <option value="<?= $station->duty_station_id ?>"><?= $station->duty_station_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
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
    <div class="col-md-2">
      <label class="form-label fw-bold" style="color: #495057;">
        <i class="fa fa-briefcase me-1"></i>Job
      </label>
      <select id="jobFilter" class="form-select select2" style="border-color: #119A48;">
        <option value="">All Jobs</option>
        <?php foreach ($jobs as $job): ?>
          <option value="<?= $job->job_id ?>"><?= $job->job_name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <button class="btn w-100" style="background-color: #119A48; color: white;" onclick="loadDashboard()">
        <i class="fa fa-sync-alt"></i>
      </button>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4" id="summaryCards"></div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #194F90; font-weight: 600; border-bottom: 2px solid #194F90; padding-bottom: 10px;">
            <i class="fa fa-venus-mars me-2"></i>Staff Gender Distribution
          </h5>
          <div id="genderChart" style="min-height: 350px;"></div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm rounded border-0 h-100">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #C3A366; font-weight: 600; border-bottom: 2px solid #C3A366; padding-bottom: 10px;">
            <i class="fa fa-file-contract me-2"></i>Staff by Contract Type
          </h5>
          <div id="contractChart" style="min-height: 350px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4 mb-4">
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #911C39; font-weight: 600; border-bottom: 2px solid #911C39; padding-bottom: 10px;">
            <i class="fa fa-building me-2"></i>Staff by Division
          </h5>
          <div id="divisionChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2.5 - Active Staff by Funder (Full Width) -->
  <div class="row g-4 mb-4">
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #119A48; font-weight: 600; border-bottom: 2px solid #119A48; padding-bottom: 10px;">
            <i class="fa fa-money-bill-wave me-2"></i>Active Staff by Funder
          </h5>
          <div id="funderChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 3 -->
  <div class="row g-4 mb-4">
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #194F90; font-weight: 600; border-bottom: 2px solid #194F90; padding-bottom: 10px;">
            <i class="fa fa-globe me-2"></i>Staff by Member State
          </h5>
          <div id="memberStateChart" style="min-height: 400px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Events Calendar -->
  <div class="row g-4">
    <div class="col-lg-12">
      <div class="card shadow-sm rounded border-0">
        <div class="card-body p-4">
          <h5 class="card-title mb-4" style="color: #119A48; font-weight: 600; border-bottom: 2px solid #119A48; padding-bottom: 10px;">
            <i class="fa fa-calendar-alt me-2"></i>Staff Birthdays & Events Calendar
          </h5>
          <div class="calendar-container">
            <div id="birthdayCalendar" style="min-height: 600px;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  $(function () {
    loadDashboard();
    initializeCalendar();
    $('#divisionFilter, #dutyStationFilter, #funderFilter, #jobFilter').on('change', function() {
      loadDashboard();
      if (window.birthdayCalendar) {
        window.birthdayCalendar.refetchEvents();
      }
    });
  });

  function loadDashboard() {
    const divisionId = $('#divisionFilter').val();
    const dutyStationId = $('#dutyStationFilter').val();
    const funderId = $('#funderFilter').val();
    const jobId = $('#jobFilter').val();

    $.getJSON(base_url + 'dashboard/fetch_dashboard_data', {
      division_id: divisionId,
      duty_station_id: dutyStationId,
      funder_id: funderId,
      job_id: jobId
    }, function (data) {
      // Update summary cards
      const cards = [
        { label: 'Main Staff', icon: 'fa-users', color: '#194F90', value: data.staff || 0, link: base_url + 'staff' },
        { label: 'Contracts Due', icon: 'fa-calendar-times', color: '#C3A366', value: data.two_months || 0, link: base_url + 'staff/contract_status/2' },
        { label: 'Under Renewal', icon: 'fa-sync-alt', color: '#119A48', value: data.staff_renewal || 0, link: base_url + 'staff/contract_status/7' },
        { label: 'Expired Contracts', icon: 'fa-exclamation-triangle', color: '#911C39', value: data.expired || 0, link: base_url + 'staff/contract_status/3' }
      ];

      $('#summaryCards').html(cards.map(card => `
        <div class="col mb-3">
          <a href="${card.link}" class="text-decoration-none">
            <div class="card shadow-lg rounded-3 border-0 text-white h-100" style="background: linear-gradient(135deg, ${card.color} 0%, ${card.color}cc 100%); transition: all 0.3s ease; border-left: 4px solid ${card.color} !important;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
              <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                  <p class="mb-2 text-white fw-semibold small text-uppercase" style="opacity: 0.9; letter-spacing: 0.5px;">${card.label}</p>
                  <h2 class="fw-bold text-white mb-0" style="font-size: 2.5rem;">${card.value}</h2>
                </div>
                <div class="fs-1 opacity-75" style="font-size: 3.5rem;"><i class="fa ${card.icon}"></i></div>
              </div>
            </div>
          </a>
        </div>
      `).join(''));

      // Gender Distribution Chart
      Highcharts.chart('genderChart', {
        chart: { type: 'pie', height: 350 },
        title: { text: '' },
        colors: ['#194F90', '#119A48', '#C3A366'],
        plotOptions: {
          pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
              enabled: true,
              format: '<b>{point.name}</b><br>{point.y} ({point.percentage:.1f}%)'
            }
          }
        },
        series: [{
          name: 'Gender',
          data: (data.data_points || []).map(p => ({ name: p.name, y: parseInt(p.y || 0) }))
        }]
      });

      // Contract Type Chart
      Highcharts.chart('contractChart', {
        chart: { type: 'column', height: 350 },
        title: { text: '' },
        xAxis: {
          categories: (data.staff_by_contract?.contract_type || []),
          title: { text: null }
        },
        yAxis: {
          title: { text: 'Number of Staff' },
          allowDecimals: false
        },
        colors: ['#C3A366'],
        plotOptions: {
          column: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Staff',
          data: (data.staff_by_contract?.value || [])
        }]
      });

      // Division Chart
      Highcharts.chart('divisionChart', {
        chart: { type: 'column', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.staff_by_division?.division || []),
          title: { text: null },
          labels: { rotation: -45, style: { fontSize: '11px' } }
        },
        yAxis: {
          title: { text: 'Number of Staff' },
          allowDecimals: false
        },
        colors: ['#911C39'],
        plotOptions: {
          column: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Staff',
          data: (data.staff_by_division?.value || [])
        }]
      });

      // Funder Chart (Column Chart - Full Width)
      Highcharts.chart('funderChart', {
        chart: { type: 'column', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.staff_by_funder?.funder || []),
          title: { text: null },
          labels: { rotation: -45, style: { fontSize: '11px' } }
        },
        yAxis: {
          title: { text: 'Number of Staff' },
          allowDecimals: false
        },
        colors: ['#119A48'],
        plotOptions: {
          column: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            },
            pointPadding: 0.2,
            borderWidth: 0
          }
        },
        series: [{
          name: 'Active Staff',
          data: (data.staff_by_funder?.value || [])
        }]
      });

      // Member State Chart
      Highcharts.chart('memberStateChart', {
        chart: { type: 'column', height: 400 },
        title: { text: '' },
        xAxis: {
          categories: (data.staff_by_member_state?.member_states || []),
          title: { text: null },
          labels: { rotation: -45, style: { fontSize: '11px' } }
        },
        yAxis: {
          title: { text: 'Number of Staff' },
          allowDecimals: false
        },
        colors: ['#194F90'],
        plotOptions: {
          column: {
            dataLabels: {
              enabled: true,
              format: '{y}'
            }
          }
        },
        series: [{
          name: 'Staff',
          data: (data.staff_by_member_state?.value || [])
        }]
      });

    }).fail(() => {
      console.error("Failed to load dashboard data");
    });
  }

  function initializeCalendar() {
    const calendarEl = document.getElementById('birthdayCalendar');
    
    window.birthdayCalendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridWeek',
      height: "auto",
      allDaySlot: true,
      firstDay: 1, // Monday
      nowIndicator: true,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridWeek,dayGridMonth,listWeek'
      },
      events: function(info, successCallback, failureCallback) {
        const divisionId = $('#divisionFilter').val();
        const dutyStationId = $('#dutyStationFilter').val();
        const funderId = $('#funderFilter').val();
        const jobId = $('#jobFilter').val();
        
        $.ajax({
          url: base_url + 'dashboard/get_birthday_events',
          method: 'GET',
          dataType: 'json',
          data: {
            division_id: divisionId || '',
            duty_station_id: dutyStationId || '',
            funder_id: funderId || '',
            job_id: jobId || '',
            start: info.startStr,
            end: info.endStr
          },
          success: function(response) {
            // Handle both array response and error response
            if (Array.isArray(response)) {
              successCallback(response);
            } else if (response && response.error) {
              console.error('Error loading birthday events:', response.error);
              successCallback([]); // Return empty array on error
            } else {
              successCallback(response || []);
            }
          },
          error: function(xhr, status, error) {
            console.error('AJAX error loading birthday events:', status, error);
            console.error('Response:', xhr.responseText);
            failureCallback();
          }
        });
      },
      eventClick: function(info) {
        const event = info.event;
        const props = event.extendedProps;
        
        let html = `
          <div class="text-start">
            <p><strong>Name:</strong> ${event.title}</p>
            <p><strong>Date:</strong> ${event.start.toLocaleDateString()}</p>
        `;
        
        if (props.grade) html += `<p><strong>Grade:</strong> ${props.grade}</p>`;
        if (props.job_name) html += `<p><strong>Job:</strong> ${props.job_name}</p>`;
        if (props.division) html += `<p><strong>Division:</strong> ${props.division}</p>`;
        if (props.duty_station) html += `<p><strong>Duty Station:</strong> ${props.duty_station}</p>`;
        
        html += `</div>`;
        
        Swal.fire({
          title: 'Birthday Details',
          html: html,
          icon: 'info',
          confirmButtonText: 'Close',
          confirmButtonColor: '#119A48',
          showCloseButton: true
        });
      },
      eventDidMount: function(info) {
        info.el.style.cursor = 'pointer';
        info.el.setAttribute('title', info.event.title);
      },
      eventDisplay: 'block',
      dayMaxEvents: 3,
      moreLinkClick: 'popover'
    });

    window.birthdayCalendar.render();
  }
</script>
