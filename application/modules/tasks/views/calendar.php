<?php $this->load->view('tasks_tabs')?>
<div class="container mt-5">
    <h2>My Tasks Calendar</h2>
    
    <!-- Filter Form -->
    <form id="filterForm" class="row g-3 mb-4">
      <div class="col-md-4">
        <label for="output" class="form-label">Output</label>
        <select id="output" name="output" class="form-select">
          <option value="">All Outputs</option>
          <?php 
            // Example: Loop through your outputs
            foreach($outputs as $output): ?>
              <option value="<?php echo $output->id; ?>">
                <?php echo $output->name; ?>
              </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="quarter" class="form-label">Quarter</label>
        <select id="quarter" name="quarter" class="form-select">
          <option value="">All Quarters</option>
          <option value="Q1">Q1</option>
          <option value="Q2">Q2</option>
          <option value="Q3">Q3</option>
          <option value="Q4">Q4</option>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary">Filter</button>
      </div>
    </form>
    
    <!-- Calendar Container -->
    <div id="calendar"></div>
  </div>

  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      
      // Initialize the calendar
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        // Fetch events from a PHP endpoint that returns JSON
        events: {
          url: 'events-endpoint.php', // Endpoint to get tasks data
          method: 'GET',
          extraParams: function() {
            return {
              output: document.getElementById('output').value,
              quarter: document.getElementById('quarter').value
            };
          },
          failure: function() {
            alert('There was an error fetching tasks!');
          }
        },
        eventClick: function(info) {
          // Optionally, handle event clicks (e.g., open modal with task details)
          alert('Task: ' + info.event.title);
        }
      });
      
      calendar.render();
      
      // Refresh calendar events when filter form is submitted
      document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        calendar.refetchEvents();
      });
    });
  </script>