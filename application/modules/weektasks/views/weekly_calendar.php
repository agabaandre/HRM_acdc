<style>
  /* Enhanced Calendar Styling */


  .calendar-container {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-bottom: 2rem;
  }

  .calendar-sidebar {
    background: #f8f9fa;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    height: fit-content;
  }

  .status-legend {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
  }

  .status-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
    transition: all 0.3s ease;
  }

  .status-item:last-child {
    border-bottom: none;
  }

  .status-item:hover {
    background: rgba(52, 143, 65, 0.05);
    border-radius: 8px;
    padding-left: 0.5rem;
  }

  .status-badge {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .status-text {
    font-weight: 600;
    color: #495057;
  }

  .calendar-stats {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  }

  .stat-item {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 15px;
    background: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
  }

  .stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .stat-item.total {
    --stat-color: #17a2b8;
    --stat-color-light: #20c997;
  }

  .stat-item.completed {
    --stat-color: #28a745;
    --stat-color-light: #34ce57;
  }

  .stat-item.pending {
    --stat-color: #ffc107;
    --stat-color-light: #ffed4e;
  }

  .stat-item.overdue {
    --stat-color: #dc3545;
    --stat-color-light: #e74c3c;
  }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--stat-color);
    display: block;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .stat-label {
    font-size: 0.9rem;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .stat-icon {
    font-size: 1.5rem;
    color: var(--stat-color);
    margin-bottom: 0.5rem;
    display: block;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  .stat-number {
    animation: countUp 1s ease-out;
  }

  @keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Progress bar for visual representation */
  .stat-progress {
    height: 4px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
  }

  .stat-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
    border-radius: 2px;
    transition: width 1s ease-out;
    position: relative;
    overflow: hidden;
  }

  .stat-progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
  }

  @keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
  }

  /* Sparkle effect for completed tasks */
  .stat-item.sparkle {
    animation: sparkle 0.5s ease-in-out;
  }

  @keyframes sparkle {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); box-shadow: 0 0 20px rgba(40, 200, 40, 0.5); }
  }

  /* Enhanced hover effects */
  .stat-item:hover .stat-icon {
    animation: bounce 0.6s ease-in-out;
  }

  @keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
  }

  /* Glow effect for overdue tasks */
  .stat-item.overdue:hover {
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
  }

  /* Success glow for completed tasks */
  .stat-item.completed:hover {
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
  }

  /* FullCalendar Custom Styling */
  .fc {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .fc-header-toolbar {
    background: linear-gradient(135deg, rgba(52, 143, 65, 1) 0%, rgba(40, 120, 50, 1) 100%);
    color: white;
    padding: 1rem 1.5rem;
    margin: 0;
    border-radius: 15px 15px 0 0;
  }

  .fc-toolbar-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
  }

  .fc-button {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
  }

  .fc-button:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
  }

  .fc-button:focus {
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
  }

  .fc-daygrid-day {
    background: #fafbfc;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
  }

  .fc-daygrid-day:hover {
    background: rgba(52, 143, 65, 0.05);
  }

  .fc-day-today {
    background: rgba(52, 143, 65, 0.1) !important;
    border: 2px solid rgba(52, 143, 65, 1) !important;
  }

  .fc-col-header-cell {
    background: rgba(52, 143, 65, 1);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 0.5rem;
  }

  .fc-day-sat, .fc-day-sun {
    background: #e9ecef;
  }

  .fc-event {
    border-radius: 8px;
    border: none;
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
  }

  .fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  }

  .fc-event-title {
    font-weight: 600;
  }

  .calendar-actions {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
  }

  .btn-calendar {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.85rem;
    margin: 0.25rem;
  }

  .btn-calendar:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
  }

  @media (max-width: 768px) {
    .calendar-sidebar {
      margin-top: 2rem;
    }
    
    .fc-toolbar-title {
      font-size: 1.2rem;
    }
    
    .fc-button {
      padding: 0.4rem 0.8rem;
      font-size: 0.8rem;
    }
  }
</style>

<?php $this->load->view('tasks_tabs')?>

<?php
// Prepare header data for weekly calendar
$header_data = [
    'title' => 'Weekly Task Calendar',
    'subtitle' => 'Visual overview of your weekly activities and tasks',
    'icon' => 'fa-calendar-alt',
    'actions' => [
        [
            'text' => 'Refresh',
            'icon' => 'fa-sync-alt',
            'class' => 'btn-success',
            'onclick' => 'onclick="refreshCalendar()"'
        ],
        [
            'text' => 'Today',
            'icon' => 'fa-home',
            'class' => 'btn-primary',
            'onclick' => 'onclick="goToToday()"'
        ]
    ]
];

// Load shared header
$this->load->view('templates/partials/shared_page_header', $header_data);
?>

<div class="container-fluid">
  <!-- Weekly Stats Above Calendar -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="calendar-stats">
        <h5 class="mb-3 fw-bold text-center">
          <i class="fa fa-chart-bar me-2"></i>This Week Statistics
        </h5>
        <div class="row g-3">
          <div class="col-md-3">
            <div class="stat-item total">
              <i class="fa fa-tasks stat-icon"></i>
              <span class="stat-number" id="totalTasks">0</span>
              <span class="stat-label">Total Tasks</span>
              <div class="stat-progress">
                <div class="stat-progress-bar" id="totalProgress" style="width: 0%"></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item completed">
              <i class="fa fa-check-circle stat-icon"></i>
              <span class="stat-number" id="completedTasks">0</span>
              <span class="stat-label">Completed</span>
              <div class="stat-progress">
                <div class="stat-progress-bar" id="completedProgress" style="width: 0%"></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item pending">
              <i class="fa fa-clock stat-icon"></i>
              <span class="stat-number" id="pendingTasks">0</span>
              <span class="stat-label">Pending</span>
              <div class="stat-progress">
                <div class="stat-progress-bar" id="pendingProgress" style="width: 0%"></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item overdue">
              <i class="fa fa-exclamation-triangle stat-icon"></i>
              <span class="stat-number" id="overdueTasks">0</span>
              <span class="stat-label">Overdue</span>
              <div class="stat-progress">
                <div class="stat-progress-bar" id="overdueProgress" style="width: 0%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-9">
      <div class="calendar-container">
        <div id="taskCalendar" style="min-height: 600px;"></div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="calendar-sidebar">
        <!-- Status Legend -->
        <div class="status-legend">
          <h5 class="mb-3 fw-bold text-center">
            <i class="fa fa-key me-2"></i>Status Legend
          </h5>
          <div class="status-item">
            <div class="status-badge" style="background: #ffc107;"></div>
            <span class="status-text">Pending</span>
          </div>
          <div class="status-item">
            <div class="status-badge" style="background: #28a745;"></div>
            <span class="status-text">Completed</span>
          </div>
          <div class="status-item">
            <div class="status-badge" style="background: #007bff;"></div>
            <span class="status-text">Carried Forward</span>
          </div>
          <div class="status-item">
            <div class="status-badge" style="background: #dc3545;"></div>
            <span class="status-text">Cancelled</span>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="calendar-stats">
          <h5 class="mb-3 fw-bold text-center">
            <i class="fa fa-bolt me-2"></i>Quick Actions
          </h5>
          <div class="d-grid gap-2">
            <a href="<?= base_url('weektasks/tasks') ?>" class="btn btn-outline-success btn-sm">
              <i class="fa fa-list me-2"></i>View Task List
            </a>
            <a href="<?= base_url('weektasks/tasks') ?>" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="fa fa-plus me-2"></i>Add New Task
            </a>
            <button class="btn btn-outline-info btn-sm" onclick="exportCalendar()">
              <i class="fa fa-download me-2"></i>Export Calendar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('taskCalendar');
  let calendar;

  // Initialize calendar
  calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridWeek',
    height: "auto",
    allDaySlot: true,
    events: {
      url: '<?= base_url("weektasks/get_staff_events") ?>',
      method: 'GET',
      failure: function() {
        console.error('Failed to load calendar events');
      }
    },
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridWeek,dayGridMonth'
    },
    firstDay: 1, // Monday
    nowIndicator: true,
    eventClick: function(info) {
      // Show task details in a modal or alert
      const event = info.event;
      const status = getStatusText(event.extendedProps.status);
      
      Swal.fire({
        title: event.title,
        html: `
          <div class="text-start">
            <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(event.extendedProps.status)}">${status}</span></p>
            <p><strong>Start Date:</strong> ${event.start.toLocaleDateString()}</p>
            <p><strong>End Date:</strong> ${event.end ? event.end.toLocaleDateString() : 'N/A'}</p>
            ${event.extendedProps.comments ? `<p><strong>Comments:</strong> ${event.extendedProps.comments}</p>` : ''}
          </div>
        `,
        icon: 'info',
        confirmButtonText: 'Close',
        confirmButtonColor: 'rgba(52, 143, 65, 1)'
      });
    },
    eventDidMount: function(info) {
      // Add tooltip to events
      info.el.setAttribute('title', `${info.event.title} - ${getStatusText(info.event.extendedProps.status)}`);
    },
    datesSet: function(info) {
      // Update statistics when calendar view changes
      updateStatistics(info.start, info.end);
    }
  });

  calendar.render();

  // Global functions for calendar actions
  window.refreshCalendar = function() {
    calendar.refetchEvents();
    updateStatistics();
  };

  window.goToToday = function() {
    calendar.today();
  };

  window.exportCalendar = function() {
    // Export calendar as PDF or image
    const exportUrl = '<?= base_url("weektasks/export_calendar") ?>';
    window.open(exportUrl, '_blank');
  };

  // Helper functions
  function getStatusText(status) {
    switch (parseInt(status)) {
      case 1: return 'Pending';
      case 2: return 'Completed';
      case 3: return 'Carried Forward';
      case 4: return 'Cancelled';
      default: return 'Unknown';
    }
  }

  function getStatusColor(status) {
    switch (parseInt(status)) {
      case 1: return 'warning';
      case 2: return 'success';
      case 3: return 'primary';
      case 4: return 'danger';
      default: return 'secondary';
    }
  }

  function updateStatistics(startDate, endDate) {
    // Fetch statistics for the current view
    const start = startDate || calendar.view.activeStart;
    const end = endDate || calendar.view.activeEnd;
    
    // Make AJAX call to get statistics
    $.ajax({
      url: '<?= base_url("weektasks/get_calendar_stats") ?>',
      method: 'POST',
      data: {
        start_date: start.toISOString().split('T')[0],
        end_date: end.toISOString().split('T')[0],
        '<?= $this->security->get_csrf_token_name(); ?>': '<?= $this->security->get_csrf_hash(); ?>'
      },
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          const data = response.data;
          const total = data.total || 0;
          const completed = data.completed || 0;
          const pending = data.pending || 0;
          const overdue = data.overdue || 0;
          
          // Animate number counting
          animateNumber('#totalTasks', total);
          animateNumber('#completedTasks', completed);
          animateNumber('#pendingTasks', pending);
          animateNumber('#overdueTasks', overdue);
          
          // Update progress bars
          updateProgressBar('#totalProgress', total, total);
          updateProgressBar('#completedProgress', completed, total);
          updateProgressBar('#pendingProgress', pending, total);
          updateProgressBar('#overdueProgress', overdue, total);
        }
      },
      error: function() {
        console.error('Failed to load calendar statistics');
      }
    });
  }

  // Helper function for animated number counting
  function animateNumber(selector, targetNumber) {
    const element = $(selector);
    const startNumber = parseInt(element.text()) || 0;
    const duration = 1000; // 1 second
    const increment = (targetNumber - startNumber) / (duration / 16); // 60fps
    let currentNumber = startNumber;
    
    const timer = setInterval(() => {
      currentNumber += increment;
      if ((increment > 0 && currentNumber >= targetNumber) || 
          (increment < 0 && currentNumber <= targetNumber)) {
        currentNumber = targetNumber;
        clearInterval(timer);
      }
      element.text(Math.round(currentNumber));
    }, 16);
  }

  // Helper function for progress bar animation
  function updateProgressBar(selector, value, max) {
    const percentage = max > 0 ? (value / max) * 100 : 0;
    $(selector).css('width', percentage + '%');
  }

  // Add sparkle effect for completed tasks
  function addSparkleEffect() {
    if (parseInt($('#completedTasks').text()) > 0) {
      $('.stat-item.completed').addClass('sparkle');
      setTimeout(() => {
        $('.stat-item.completed').removeClass('sparkle');
      }, 2000);
    }
  }

  // Initial statistics load
  updateStatistics();
});
</script>