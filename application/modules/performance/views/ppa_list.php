<h3>My Performance Plans</h3>

<ul class="nav nav-tabs" id="ppaTabs" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#myppa">My PPAs</a></li>
  <li class="nav-item"><a class="nav-link " data-bs-toggle="tab" href="#pending">Pending Review</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#approved">Approved PPAs</a></li>
</ul>

<div class="tab-content p-3 border border-top-0">
  <div class="tab-pane fade show active" id="pending">
    <table class="table table-bordered" id="pendingTable">
      <thead><tr><th>ID</th><th>Staff</th><th>Status</th><th>Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
  <div class="tab-pane fade" id="myppa">
    <table class="table table-bordered" id="myppaTable">
      <thead><tr><th>ID</th><th>Period</th><th>Status</th><th>Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
  <div class="tab-pane fade" id="approved">
    <table class="table table-bordered" id="approvedTable">
      <thead><tr><th>ID</th><th>Staff</th><th>Period</th><th>Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function () {
  loadTable('myppa');
  loadTable('pending');
  loadTable('approved');

  function loadTable(type) {
    $.get("<?= base_url('performance/fetch_ppas/') ?>" + type, function (data) {
      const tableId = "#" + type + "Table tbody";
      let rows = '';
      $.each(data, function (i, row) {
        rows += `<tr>
          <td>${row.entry_id}</td>
          <td>${row.staff_name || row.performance_period}</td>
          <td>${row.status}</td>
          <td><a href="<?= base_url('performance/view_ppa/') ?>${row.entry_id}" class="btn btn-sm btn-primary">View</a></td>
        </tr>`;
      });
      $(tableId).html(rows);
    }, 'json');
  }
});
</script>
