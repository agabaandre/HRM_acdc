<?php $this->load->view('tasks_tabs')?>
<div class="container my-4">
  <div class="row">
    <div class="col-md-9">
      <div id="taskCalendar" style="min-height: 450px;"></div>
    </div>
    <div class="col-md-3">
      <h5>Status Key</h5>
      <ul class="list-group">
        <li class="list-group-item"><span class="badge bg-warning me-2">&nbsp;</span> Pending</li>
        <li class="list-group-item"><span class="badge bg-success me-2">&nbsp;</span> Completed</li>
        <li class="list-group-item"><span class="badge bg-primary me-2">&nbsp;</span> Carried Forward</li>
        <li class="list-group-item"><span class="badge bg-danger me-2">&nbsp;</span> Cancelled</li>
      </ul>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('taskCalendar');

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridWeek',
    height: "auto",
    allDaySlot: true,
    events: '<?= base_url("weeklytasks/get_staff_events") ?>',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: ''
    },
    firstDay: 1, // Monday
    nowIndicator: true
  });

  calendar.render();
});
</script>