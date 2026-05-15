<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$can_edit_divisions = in_array('78', $permissions);
$can_delete_divisions = in_array('77', $permissions);
$is_settings_admin = !empty($is_settings_admin) || (isset($session->role) && (int) $session->role === 10);
?>

<div class="row">
  <!-- Add Division Form -->
  <div class="col-md-12">
    <div class="card shadow-sm">
      <div class="card-header" style="background: linear-gradient(135deg, rgba(52, 143, 65, 1) 0%, rgba(52, 143, 65, 0.8) 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="card-title mb-0" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#divisionForm" aria-expanded="false" aria-controls="divisionForm">
            <i class="fas fa-plus-circle me-2"></i>Add New Division
            <i class="fas fa-chevron-down ms-2" id="collapseIcon"></i>
          </h4>
          <div>
            <a href="<?= base_url('settings/generate_division_short_names') ?>" class="btn btn-light btn-sm" onclick="return confirm('This will generate short names for all divisions that don\'t have them. Continue?')">
              <i class="fas fa-magic me-1"></i>Generate Short Names
            </a>
          </div>
        </div>
      </div>

      <div class="collapse" id="divisionForm">
        <div class="card-body">
          <?= form_open_multipart(base_url('settings/add_content')); ?>
          <input type="hidden" name="table" value="divisions">
          <input type="hidden" name="redirect" value="division">

          <table class="table table-borderless form-table">
            <tbody>
              <!-- Row 1: Basic Information -->
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-building me-1 text-primary"></i>Division Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="division_name" class="form-control" placeholder="Enter division name" required>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-tag me-1 text-info"></i>Short Name
                    </label>
                    <input type="text" name="division_short_name" class="form-control" placeholder="e.g., DHIS" maxlength="50">
                    <small class="form-text text-muted">Optional: Short code</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-layer-group me-1 text-warning"></i>Category <span class="text-danger">*</span>
                    </label>
                    <select name="category" class="form-control" required>
                      <option value="">Select Category</option>
                      <option value="Programs">Programs</option>
                      <option value="Operations">Operations</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </td>
              </tr>

              <!-- Row 2: Key Personnel -->
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-tie me-1 text-primary"></i>Division Head <span class="text-danger">*</span>
                    </label>
                    <select class="form-control select2 staff-select" name="division_head" data-placeholder="Select Division Head" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user me-1 text-info"></i>Focal Person <span class="text-danger">*</span>
                    </label>
                    <select class="form-control select2 staff-select" name="focal_person" data-placeholder="Select Focal Person" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calculator me-1 text-success"></i>Finance Officer <span class="text-danger">*</span>
                    </label>
                    <select class="form-control select2 staff-select" name="finance_officer" data-placeholder="Select Finance Officer" required></select>
                  </div>
                </td>
              </tr>

              <!-- Row 3: Support Staff -->
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-cog me-1 text-warning"></i>Admin Assistant <span class="text-danger">*</span>
                    </label>
                    <select class="form-control select2 staff-select" name="admin_assistant" data-placeholder="Select Admin Assistant" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-crown me-1 text-danger"></i>Director
                    </label>
                    <select class="form-control select2 staff-select" name="director_id" data-placeholder="Select Director (Optional)"></select>
                    <small class="form-text text-muted">Optional: Division director</small>
                  </div>
                </td>
                <td class="form-cell">
                  <!-- Empty cell for alignment -->
                </td>
              </tr>

              <!-- Row 4: Head OIC Information -->
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-clock me-1 text-secondary"></i>Head OIC
                    </label>
                    <select class="form-control select2 staff-select" name="head_oic_id" data-placeholder="Select Head OIC (Optional)"></select>
                    <small class="form-text text-muted">Optional: Officer in charge of division head</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC Start Date
                    </label>
                    <input type="text" name="head_oic_start_date" class="form-control datepicker" placeholder="Select start date">
                    <small class="form-text text-muted">Optional: When OIC period started</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC End Date
                    </label>
                    <input type="text" name="head_oic_end_date" class="form-control datepicker" placeholder="Select end date">
                    <small class="form-text text-muted">Optional: When OIC period ends</small>
                  </div>
                </td>
              </tr>

              <!-- Row 5: Director OIC Information -->
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-shield me-1 text-dark"></i>Director OIC
                    </label>
                    <select class="form-control select2 staff-select" name="director_oic_id" data-placeholder="Select Director OIC (Optional)"></select>
                    <small class="form-text text-muted">Optional: Officer in charge of director</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC Start Date
                    </label>
                    <input type="text" name="director_oic_start_date" class="form-control datepicker" placeholder="Select start date">
                    <small class="form-text text-muted">Optional: When OIC period started</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC End Date
                    </label>
                    <input type="text" name="director_oic_end_date" class="form-control datepicker" placeholder="Select end date">
                    <small class="form-text text-muted">Optional: When OIC period ends</small>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="row mt-4">
            <div class="col-md-12">
              <div class="d-flex gap-2 justify-content-end">
                <button type="reset" class="btn btn-outline-secondary">
                  <i class="fas fa-undo me-1"></i> Reset Form
                </button>
                <button type="submit" class="btn btn-success">
                  <i class="fas fa-save me-1"></i> Save Division
                </button>
              </div>
            </div>
          </div>
          <?= form_close(); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Division List -->
  <div class="col-md-12 mt-4">
    <div class="card shadow-sm">
      <div class="card-header bg-light">
        <h4 class="card-title mb-0">
          <i class="fas fa-list me-2 text-primary"></i>Divisions List
        </h4>
      </div>
      <div class="card-body p-3">
        <div class="table-responsive">
          <table id="divisionsTable" class="table table-striped table-hover" style="width:100%">
            <thead class="table-light">
              <tr>
                <th style="width: 50px;">ID</th>
                <th style="width: 200px;">Division Name</th>
                <th style="width: 100px;">Short Name</th>
                <th style="width: 120px;">Category</th>
                <th style="width: 150px;">Division Head</th>
                <th style="width: 150px;">Focal Person</th>
                <th style="width: 150px;">Finance Officer</th>
                <th style="width: 150px;">Admin Assistant</th>
                <th style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data will be loaded via AJAX -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.form-label {
  margin-bottom: 0.5rem;
}

.card {
  border: 1px solid #e3e6f0;
  border-radius: 0.35rem;
}

.card-header {
  border-bottom: 1px solid #e3e6f0;
}

.table th {
  border-top: none;
  font-weight: 600;
  color: #5a5c69;
}

.btn-group .btn {
  border-radius: 0.25rem;
}

.gap-2 > * + * {
  margin-left: 0.5rem;
}

/* Division name column wrapping */
.table td[style*="max-width: 200px"] {
  word-wrap: break-word;
  word-break: break-word;
  white-space: normal;
  line-height: 1.2;
}

.table td[style*="max-width: 200px"] span {
  display: block;
  word-wrap: break-word;
  word-break: break-word;
  white-space: normal;
}

/* Ensure table cells have proper height for wrapped text */
.table tbody tr {
  height: auto;
  min-height: 50px;
}

.table tbody td {
  vertical-align: middle;
  padding: 0.75rem 0.5rem;
}

/* DataTables styling */
.dataTables_wrapper {
  padding: 1rem 0;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
  margin: 0.75rem 0;
  padding: 0 0.5rem;
}

.dataTables_wrapper .dataTables_filter {
  margin-bottom: 1rem;
}

.dataTables_wrapper .dataTables_length {
  margin-bottom: 1rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  padding: 0.5rem 0.75rem;
  margin: 0 0.25rem;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  color: #007bff;
  text-decoration: none;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background-color: #e9ecef;
  border-color: #dee2e6;
  color: #0056b3;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background-color: #007bff;
  border-color: #007bff;
  color: white;
}

.dataTables_wrapper .dataTables_filter input {
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
  padding: 0.375rem 0.75rem;
}

.dataTables_wrapper .dataTables_length select {
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
  padding: 0.375rem 0.75rem;
}

/* Additional spacing and layout fixes */
.card-body {
  padding: 1.5rem !important;
}

.table-responsive {
  margin: 0;
  border: none;
}

#divisionsTable {
  margin: 0;
  border-collapse: separate;
  border-spacing: 0;
}

#divisionsTable thead th {
  border-bottom: 2px solid #dee2e6;
  padding: 1rem 0.75rem;
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
}

#divisionsTable tbody td {
  padding: 0.75rem;
  border-bottom: 1px solid #dee2e6;
  vertical-align: middle;
}

#divisionsTable tbody tr:hover {
  background-color: #f8f9fa;
}

/* Fix for MutationObserver issues */
.dataTables_wrapper .dataTables_processing {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1000;
}

/* Form Table Styling */
.form-table {
  width: 100%;
  margin: 0;
  border-collapse: separate;
  border-spacing: 0;
}

.form-table .form-cell {
  width: 33.333%;
  padding: 1rem;
  vertical-align: top;
  border: none;
}

.form-table .form-cell .form-group {
  margin-bottom: 0;
}

.form-table .form-cell .form-label {
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.form-table .form-cell .form-control {
  font-size: 0.9rem;
  padding: 0.5rem 0.75rem;
}

.form-table .form-cell .form-text {
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

/* Collapsible Panel Styling */
.card-header[data-bs-toggle="collapse"] {
  transition: all 0.3s ease;
}

.card-header[data-bs-toggle="collapse"]:hover {
  background: linear-gradient(135deg, rgba(42, 123, 55, 1) 0%, rgba(42, 123, 55, 0.8) 100%) !important;
}

#collapseIcon {
  transition: transform 0.3s ease;
}

.collapsed #collapseIcon {
  transform: rotate(180deg);
}

/* Collapse Animation */
.collapse {
  transition: height 0.3s ease;
}

.collapsing {
  transition: height 0.3s ease;
}

/* Form spacing improvements */
.form-table tr:first-child .form-cell {
  padding-top: 0.5rem;
}

.form-table tr:last-child .form-cell {
  padding-bottom: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .form-table .form-cell {
    width: 100%;
    display: block;
    padding: 0.5rem 0;
  }
  
  .form-table tr {
    display: block;
    margin-bottom: 1rem;
  }
}
</style>

<script>
$(document).ready(function() {
    // Collapsible panel functionality
    $('#divisionForm').on('show.bs.collapse', function () {
        $('#collapseIcon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });
    
    $('#divisionForm').on('hide.bs.collapse', function () {
        $('#collapseIcon').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });

    // Check if table exists before initializing
    if ($('#divisionsTable').length) {
        $('#divisionsTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "<?= base_url('settings/divisions_datatables') ?>",
                "type": "POST",
                "data": function(d) {
                    d.<?= $this->security->get_csrf_token_name() ?> = "<?= $this->security->get_csrf_hash() ?>";
                },
                "error": function(xhr, error, thrown) {
                    console.error('DataTables AJAX error:', error, thrown);
                    alert('Error loading data. Please refresh the page.');
                }
            },
        "columns": [
            { "data": 0, "orderable": true },
            { 
                "data": 1, 
                "orderable": true,
                "render": function(data, type, row) {
                    return '<span class="fw-semibold" style="line-height: 1.2; font-size: 0.9rem; word-break: break-word; white-space: normal;" title="' + data + '">' + data + '</span>';
                }
            },
            { 
                "data": 2, 
                "orderable": true,
                "render": function(data, type, row) {
                    if (data && data !== '-') {
                        return '<span class="badge bg-primary">' + data + '</span>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { 
                "data": 3, 
                "orderable": true,
                "render": function(data, type, row) {
                    if (data && data !== '-') {
                        return '<span class="badge bg-info">' + data + '</span>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { "data": 4, "orderable": true },
            { "data": 5, "orderable": true },
            { "data": 6, "orderable": true },
            { "data": 7, "orderable": true },
            { 
                "data": 8, 
                "orderable": false,
                "searchable": false,
                "render": function(data, type, row) {
                    return data;
                }
            }
        ],
        "order": [[1, "asc"]],
        "pageLength": 15,
        "lengthMenu": [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
        "responsive": true,
        "language": {
            "processing": "Loading divisions...",
            "lengthMenu": "Show _MENU_ divisions per page",
            "zeroRecords": "No divisions found",
            "info": "Showing _START_ to _END_ of _TOTAL_ divisions",
            "infoEmpty": "Showing 0 to 0 of 0 divisions",
            "infoFiltered": "(filtered from _MAX_ total divisions)",
            "search": "Search:",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "drawCallback": function(settings) {
            // Re-initialize tooltips after table redraw
            try {
                $('[data-bs-toggle="tooltip"]').tooltip();
            } catch(e) {
                console.log('Tooltip initialization skipped:', e);
            }
        },
        "initComplete": function(settings, json) {
            console.log('DataTables initialized successfully');
        }
        });
    } else {
        console.error('Table #divisionsTable not found');
    }
});

</script>

<?php if ($can_edit_divisions): ?>
  <?php include('modals/division_edit_modal.php'); ?>
<?php endif; ?>
<?php if ($can_delete_divisions): ?>
  <?php include('modals/division_delete_modal.php'); ?>
<?php endif; ?>

<script>
(function($) {
    var staffOptionsCache = null;
    var staffOptionsPromise = null;
    var staffSelectsInitialized = false;
    var divisionStaffUrl = '<?= base_url('settings/staff_select_options') ?>';
    var divisionJsonUrl = '<?= base_url('settings/division_json/') ?>';

    function loadStaffOptions() {
        if (staffOptionsCache) {
            return $.Deferred().resolve(staffOptionsCache).promise();
        }
        if (staffOptionsPromise) {
            return staffOptionsPromise;
        }
        staffOptionsPromise = $.getJSON(divisionStaffUrl).then(function(resp) {
            staffOptionsCache = (resp && resp.success) ? (resp.options || []) : [];
            return staffOptionsCache;
        }).fail(function() {
            staffOptionsCache = [];
            staffOptionsPromise = null;
            return staffOptionsCache;
        });
        return staffOptionsPromise;
    }

    function fillStaffSelect($select, selectedId) {
        var placeholder = $select.data('placeholder') || 'Select...';
        $select.empty();
        $select.append($('<option value="">').text(placeholder));
        $.each(staffOptionsCache || [], function(_, opt) {
            var $opt = $('<option>').val(opt.id).text(opt.text);
            if (selectedId && String(opt.id) === String(selectedId)) {
                $opt.prop('selected', true);
            }
            $select.append($opt);
        });
    }

    function destroySelect2In($container) {
        $container.find('.select2').each(function() {
            var $el = $(this);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
        });
    }

    function initSelect2In($container) {
        var $modal = $container.closest('.modal');
        var dropdownParent = $modal.length ? $modal : $('#divisionForm');
        $container.find('.select2').each(function() {
            var $el = $(this);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({
                dropdownParent: dropdownParent,
                placeholder: $el.data('placeholder') || 'Select...',
                allowClear: !$el.prop('required')
            });
        });
    }

    function initDatepickersIn($container) {
        $container.find('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }

    function populateAddFormStaffSelects() {
        if (staffSelectsInitialized) {
            return;
        }
        loadStaffOptions().then(function() {
            $('#divisionForm .staff-select').each(function() {
                fillStaffSelect($(this), null);
            });
            initSelect2In($('#divisionForm'));
            staffSelectsInitialized = true;
        });
    }

    function setVal(id, val) {
        $(id).val(val === null || val === undefined ? '' : val);
    }

    function populateEditModal(division) {
        $('#divisionEditModalTitle').text('Edit Division: ' + (division.division_name || ''));
        $('#divisionEditCallerValue').val(division.division_id);
        setVal('#divisionEditName', division.division_name);
        setVal('#divisionEditShortName', division.division_short_name);
        setVal('#divisionEditCategory', division.category);
        setVal('#divisionEditHeadOicStart', division.head_oic_start_date);
        setVal('#divisionEditHeadOicEnd', division.head_oic_end_date);
        setVal('#divisionEditDirectorOicStart', division.director_oic_start_date);
        setVal('#divisionEditDirectorOicEnd', division.director_oic_end_date);
        $('#divisionEditModal .division-staff-select').each(function() {
            fillStaffSelect($(this), division[$(this).attr('name')] || '');
        });
    }

    function showEditModalLoading() {
        $('#divisionEditModal .division-modal-loading').removeClass('d-none');
        $('#divisionEditModal .division-modal-form').addClass('d-none');
    }

    function showEditModalForm() {
        $('#divisionEditModal .division-modal-loading').addClass('d-none');
        $('#divisionEditModal .division-modal-form').removeClass('d-none');
    }

    function openEditModal(divisionId) {
        var $modal = $('#divisionEditModal');
        if (!$modal.length) {
            return;
        }
        showEditModalLoading();
        var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
        modal.show();

        $.when(loadStaffOptions(), $.getJSON(divisionJsonUrl + divisionId))
            .done(function(staffData, divisionResp) {
                var resp = divisionResp[0];
                if (!resp || !resp.success) {
                    alert(resp && resp.message ? resp.message : 'Could not load division.');
                    modal.hide();
                    return;
                }
                destroySelect2In($modal);
                populateEditModal(resp.division);
                showEditModalForm();
                initSelect2In($modal);
                initDatepickersIn($modal);
            })
            .fail(function() {
                alert('Could not load division. Please try again.');
                modal.hide();
            });
    }

    function openDeleteModal(divisionId) {
        var $modal = $('#divisionDeleteModal');
        if (!$modal.length) {
            return;
        }
        $modal.find('.division-delete-loading').removeClass('d-none');
        $modal.find('.division-delete-content').addClass('d-none');
        var modal = bootstrap.Modal.getOrCreateInstance($modal[0]);
        modal.show();

        $.getJSON(divisionJsonUrl + divisionId)
            .done(function(resp) {
                if (!resp || !resp.success) {
                    alert(resp && resp.message ? resp.message : 'Could not load division.');
                    modal.hide();
                    return;
                }
                var d = resp.division;
                $('#divisionDeleteCallerValue').val(d.division_id);
                $('#divisionDeleteName').text(d.division_name || '');
                if (d.division_short_name) {
                    $('#divisionDeleteShort').text(d.division_short_name);
                    $('#divisionDeleteShortWrap').removeClass('d-none');
                } else {
                    $('#divisionDeleteShortWrap').addClass('d-none');
                }
                if (d.category) {
                    $('#divisionDeleteCategory').text(d.category);
                    $('#divisionDeleteCategoryWrap').removeClass('d-none');
                } else {
                    $('#divisionDeleteCategoryWrap').addClass('d-none');
                }
                $modal.find('.division-delete-loading').addClass('d-none');
                $modal.find('.division-delete-content').removeClass('d-none');
            })
            .fail(function() {
                alert('Could not load division. Please try again.');
                modal.hide();
            });
    }

    $('#divisionForm').on('show.bs.collapse', function() {
        populateAddFormStaffSelects();
    });

    $(document).on('click', '.btn-division-edit', function(e) {
        e.preventDefault();
        openEditModal($(this).data('division-id'));
    });

    $(document).on('click', '.btn-division-delete', function(e) {
        e.preventDefault();
        openDeleteModal($(this).data('division-id'));
    });

    $('#divisionEditModal').on('hidden.bs.modal', function() {
        destroySelect2In($(this));
        showEditModalLoading();
    });
})(jQuery);
</script>
