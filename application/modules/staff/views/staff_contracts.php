<style>
    @media print{
        .hidden{
          display: none;
        }
        @page{
            margin-top: 0;
            margin-bottom: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
    }
    
    /* Contracts table styling */
    #contracts-container .table {
        margin-bottom: 0;
    }
    
    #contracts-container .table thead {
        background-color: #f8f9fa;
    }
    
    #contracts-container .table thead th {
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }
    
    #contracts-container .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    /* Pagination styling */
    #contracts-pagination .pagination-buttons {
        display: flex;
        gap: 0.25rem;
        align-items: center;
    }
</style>





<?php $this->load->view('staff_tab_menu'); ?>

<div class="container-fluid mt-3">
    <!-- Staff Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-2">
                        <?= ($this_staff->title ?? '') . ' ' . ($this_staff->fname ?? '') . ' ' . ($this_staff->lname ?? '') . ' ' . ($this_staff->oname ?? '') ?>
                    </h3>
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        <?php if (!empty($this_staff->SAPNO)): ?>
                            <span><i class="fa fa-id-card me-1"></i><strong>SAPNO:</strong> <?= $this_staff->SAPNO ?></span>
                        <?php endif; ?>
                        <?php if (!empty($this_staff->nationality)): ?>
                            <span><i class="fa fa-globe me-1"></i><strong>Nationality:</strong> <?= $this_staff->nationality ?></span>
                        <?php endif; ?>
                        <?php if (!empty($this_staff->work_email)): ?>
                            <span><i class="fa fa-envelope me-1"></i><strong>Email:</strong> <a href="mailto:<?= $this_staff->work_email ?>" class="text-decoration-none"><?= $this_staff->work_email ?></a></span>
                        <?php endif; ?>
                        <?php if (!empty($this_staff->tel_1)): ?>
                            <span><i class="fa fa-phone me-1"></i><strong>Phone:</strong> <?= $this_staff->tel_1 ?><?= !empty($this_staff->tel_2) ? ' / ' . $this_staff->tel_2 : '' ?></span>
                        <?php endif; ?>
                        <?php if (!empty($this_staff->whatsapp)): ?>
                            <span><i class="fab fa-whatsapp me-1"></i><strong>WhatsApp:</strong> <?= $this_staff->whatsapp ?></span>
                        <?php endif; ?>
                        <?php if (!empty($this_staff->gender)): ?>
                            <span><i class="fa fa-user me-1"></i><strong>Gender:</strong> <?= $this_staff->gender ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit_biodata<?php echo $staff_id; ?>">
                            <i class="fa fa-edit me-1"></i> Edit Biodata
                        </button>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo base_url() ?>staff/new_contract/<?php echo $staff_id; ?>" class="btn btn-dark btn-sm mb-2 d-block">
                        <i class="fa fa-plus me-1"></i> New / Renew Contract
                    </a>
                    <a href="<?php echo base_url() ?>staff/staff_contracts/<?php echo $staff_id; ?>/1" class="btn btn-sm btn-outline-secondary d-block">
                        <i class="fa fa-file-excel me-1"></i> Export to Excel
                    </a>
        </div>
    </div>
        </div>
    </div>
 
    <!-- Contracts Section -->
    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="fa fa-file-contract me-2"></i>Contract History</h5>
        </div>
        <div class="card-body">
            <div id="contracts-container">
                <!-- Contracts table will be loaded here via AJAX -->
                <div class="text-center py-5">
                    <i class="bx bx-loader-alt bx-spin fs-1 text-secondary"></i>
                    <div class="mt-2 text-muted">Loading contracts data...</div>
                </div>
            </div>
            
            <!-- Pagination will be loaded here -->
            <div id="contracts-pagination" class="mt-3"></div>
      
      <!-- Modals container (will be populated by AJAX) -->
      <div id="contracts-modals"></div>
      
      <!-- Initial table load (fallback if JS disabled) -->
      <?php if (!empty($contracts)): ?>
        <div class="table-responsive" id="initial-contracts-table">
          <table class="table table-striped table-bordered">
        <thead>
          <tr>
          <th>#</th>
          <th>Duty Station</th>
          <th>Division</th>
                <th>Other Associated Divisions</th>
          <th>Job</th>
          <th>Acting Job</th>
          <th>First Supervisor</th>
          <th>Second Supervisor</th>
          <th>Funder</th>
          <th>Contracting Institution</th>
          <th>Grade</th>
          <th>Type</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Comment</th>
          <th>Status</th>
          <th>Option</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; ?>
              <?php foreach($contracts as $contract): ?>
            <tr>
                  <td><?=$i++?></td>
                  <td><?= $contract->duty_station_name ?? 'N/A' ?></td>
                  <td><?= $contract->division_name ?? 'N/A' ?></td>
                  <td>
                    <?php
                    if (!empty($contract->other_associated_divisions)) {
                      $divisions = json_decode($contract->other_associated_divisions, true);
                      if (is_array($divisions) && !empty($divisions)) {
                        $division_names = [];
                        foreach ($divisions as $div_id) {
                          $this->db->select('division_name');
                          $this->db->from('divisions');
                          $this->db->where('division_id', $div_id);
                          $div = $this->db->get()->row();
                          if ($div) {
                            $division_names[] = $div->division_name;
                          }
                        }
                        echo implode(', ', $division_names);
                      } else {
                        echo 'N/A';
                      }
                    } else {
                      echo 'N/A';
                    }
                    ?>
                  </td>
                  <td><?= @character_limiter($contract->job_name ?? '', 15) ?></td>
                  <td><?= @character_limiter($contract->job_acting ?? '', 15) ?></td>
                  <td><?= @staff_name($contract->first_supervisor) ?></td>
                  <td><?= @staff_name($contract->second_supervisor) ?></td>
                  <td><?= $contract->funder ?? 'N/A' ?></td>
                  <td><?= $contract->contracting_institution ?? 'N/A' ?></td>
                  <td><?= $contract->grade ?? 'N/A' ?></td>
                  <td><?= $contract->contract_type ?? 'N/A' ?></td>
                  <td><?= $contract->start_date ?? 'N/A' ?></td>
                  <td><?= $contract->end_date ?? 'N/A' ?></td>
                  <td><?= @character_limiter($contract->comments ?? '', 20) ?></td>
                  <td><?= $contract->status ?? 'N/A' ?></td>
              <td class="text text-center">
                    <a class="btn btn-sm btn-outline-primary" href="#" data-bs-toggle="modal" data-bs-target="#renew_contract<?=$contract->staff_contract_id?>">Edit</a>
              </td>
            </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Initial modals (fallback if JS disabled) -->
        <?php foreach($contracts as $contract): ?>
              <!-- edit employee contract -->
              <div class="modal fade" id="renew_contract<?=$contract->staff_contract_id?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">

                      <h5 class="modal-title" id="add_item_label">Edit Contract: <?= $this_staff->lname . ' ' . $this_staff->fname . ' ' . @$this_staff->oname ?> </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>


                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('staff/update_contract'); 
                      $readonly='';
                    
                      
                    
                      
                      ?>

                      <div class="row">
                        <div class="col-md-6">
                          <h4>Contract Information</h4>
                           <input type="hidden" name="staff_contract_id" value="<?php echo $contract->staff_contract_id; ?>">
                           <input type="hidden" name="staff_id" value="<?php echo $contract->staff_id; ?>">
                          <div class="form-group">
                            <label for="job_id">Job: <?php echo asterik()?></label>
                            <select class="form-control select2" name="job_id" id="job_id" required <?=$readonly?>>
                              <option value="">Select Job </option>
                              <?php

                              $jobs = Modules::run('lists/jobs');
                              foreach ($jobs as $job) :

                               
                              ?>

                                <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $contract->job_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $job->job_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="job_acting_id">Job Acting:</label>
                            <select class="form-control select2" name="job_acting_id" id="job_acting_id" <?=$readonly?>>
                              <option value="">Select Job Acting</option>
                              <?php $jobsacting = Modules::run('lists/jobsacting');
                              foreach ($jobsacting as $joba) :
                              ?>

                                <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $contract->job_acting_id) {
                                                                                      echo "selected";
                                                                                    } ?>><?php echo $joba->job_acting; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="grade_id">Grade: <?php echo asterik()?></label>
                            <select class="form-control select2" name="grade_id" id="grade_id" required <?=$readonly?>>
                              <option value="">Select Grade</option>
                              <?php $lists = Modules::run('lists/grades');
                              foreach ($lists as $list) :
                              ?>

                                <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $contract->grade_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->grade; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="contracting_institution_id">Contracting Institution: <?php echo asterik()?></label>
                            <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required <?=$readonly?>>
                              <option value="">Select Contracting Institution</option>
                              <?php $lists = Modules::run('lists/contractors');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $contract->contracting_institution_id) {
                                                                                                    echo "selected";
                                                                                                  } ?>><?php echo $list->contracting_institution; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="funder_id">Funder: <?php echo asterik()?></label>
                            <select class="form-control select2" name="funder_id" id="funder_id" required <?=$readonly?>>
                              <option value="">Select Funder</option>
                              <?php $lists = Modules::run('lists/funder');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $contract->funder_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->funder; ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="first_supervisor">First Supervisor: <?php echo asterik()?></label>
                            <select class="form-control select2" name="first_supervisor" id="first_supervisor" required <?=$readonly?>>
                              <option value="">Select First Supervisor</option>
                              <?php $lists = Modules::run('lists/supervisor');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->first_supervisor) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="second_supervisor">Second Supervisor:</label>
                            <select class="form-control select2" name="second_supervisor" id="second_supervisor" <?=$readonly?>>
                              <option value="">Select Second Supervisor</option>
                              <?php $lists = Modules::run('lists/supervisor');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->second_supervisor) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="contract_type_id">Contract Type: <?php echo asterik()?></label>
                            <select class="form-control select2" name="contract_type_id" id="contract_type_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/contracttype');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $contract->contract_type_id) {
                                                                                          echo "selected";
                                                                                        } ?>><?php echo $list->contract_type; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6" style="margin-top:35px;">
                          <div class="form-group">
                            <label for="duty_station_id">Duty Station: <?php echo asterik()?></label>
                            <select class="form-control select2" name="duty_station_id" id="duty_station_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/stations');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $contract->duty_station_id) {
                                                                                        echo "selected";
                                                                                      } ?>><?php echo $list->duty_station_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="division_id">Division: <?php echo asterik()?></label>
                            <select class="form-control select2" name="division_id" id="division_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/divisions');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $contract->division_id) {
                                                                                    echo "selected";
                                                                                  } ?>><?php echo $list->division_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="other_associated_divisions">Other Associated Divisions:</label>
                            <select class="form-control select2" name="other_associated_divisions[]" id="other_associated_divisions" multiple <?=$readonly?>>
                              <option value="">Select Associated Divisions</option>
                              <?php 
                              $lists = Modules::run('lists/divisions');
                              // Get current associated divisions
                              $current_divisions = [];
                              if (!empty($contract->other_associated_divisions)) {
                                  $current_divisions = json_decode($contract->other_associated_divisions, true);
                                  if (!is_array($current_divisions)) {
                                      $current_divisions = [];
                                  }
                              }
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->division_id; ?>" <?php if (in_array($list->division_id, $current_divisions)) {
                                                                                    echo "selected";
                                                                                  } ?>><?php echo $list->division_name; ?></option>
                              <?php endforeach; ?>
                            </select>
                            <small class="text-muted">You can select multiple divisions. Leave empty if none.</small>
                          </div>

                          <div class="form-group">
                            <label for="start_date">Start Date: <?php echo asterik()?></label>
                            <input type="text" class="form-control datepicker" value="<?php echo $contract->start_date; ?>" name="start_date" id="start_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="end_date">End Date: <?php echo asterik()?></label>
                            <input type="text" class="form-control datepicker" value="<?php echo $contract->end_date; ?>" name="end_date" id="end_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="status_id">Contract Status: <?php echo asterik()?></label>
                            <select class="form-control select2" name="status_id" id="status_id" required>
                            <?php 
                              $lists = Modules::run('lists/status');
                              $current_status_id = isset($contract->status_id) ? (int)$contract->status_id : null;
                              foreach ($lists as $list) :
                                  // Allow editing: Active (1), Separated (4), Under Renewal (7), and also show Expired (3) if it's the current status
                                  $is_allowed = in_array($list->status_id, [1, 4, 7]);
                                  $is_selected = ($current_status_id !== null && (int)$list->status_id === $current_status_id);
                                  // Always show the current status, even if it's not in the allowed list (e.g., expired/separated)
                                  // This ensures expired (3) and separated (4) contracts can be viewed and edited
                                  $should_show = $is_allowed || $is_selected;
                            ?>
                                <?php if ($should_show): ?>
                                <option value="<?= $list->status_id ?>"
                                  <?= $is_selected ? 'selected' : '' ?>
                                    <?= !$is_allowed && !$is_selected ? 'disabled' : '' ?>>
                                  <?= htmlspecialchars($list->status) ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                          </select>


                        

                          </div>

                          <!-- <div class="form-group">
                            <label for="file_name">File Name:</label>
                            <input type="text" class="form-control" name="file_name" id="file_name" required>
                          </div> -->

                          <div class="form-group">
                            <label for="comments">Comments:</label>
                            <textarea class="form-control" name="comments" id="comments" rows="3"><?php echo $contract->comments; ?></textarea>
                          </div>




                          <div class="form-group" style="float:right;">
                            <br>
                            <label for="submit"></label>
                            <input type="submit" class="btn btn-dark"  value="Save">
  
                          </div>

                          <?php echo form_close(); ?>
                        </div>
                      </div>
                    </div>


                  </div>
                </div>




              </div>

              <!-- Edit contract -->
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
  </div>

<script>
$(document).ready(function() {
    const staffId = <?= $staff_id ?? 0 ?>;
    let currentPage = 0;
    
    // CSRF token variables (can be updated)
    const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    let csrfHash = '<?= $this->security->get_csrf_hash(); ?>';
    
    // Hide initial table if JS is enabled
    $('#initial-contracts-table').hide();
    
    // Load contracts data
    function loadContracts(page = 0) {
        currentPage = page;
        $('#contracts-container').html(`
            <div class="text-center py-4">
                <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                <div class="mt-2">Loading contracts data...</div>
</div>
        `);
        
        // Prepare data with CSRF token
        const requestData = {};
        requestData.page = page;
        requestData[csrfName] = csrfHash;
        
        $.ajax({
            url: '<?php echo base_url() ?>staff/get_contracts_data_ajax/' + staffId,
            type: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                // Update CSRF token if regenerated
                if (response.csrf_hash) {
                    csrfHash = response.csrf_hash;
                }
                if (response.html) {
                    $('#contracts-container').html(response.html);
                    renderPagination(response.total, response.page, response.per_page);
                    // Reinitialize select2 and datepicker if needed
                    if (typeof $().select2 === 'function') {
                        // Initialize Select2 for all selects in the container
                        $('#contracts-container .select2').each(function() {
                            var $select = $(this);
                            if (!$select.hasClass('select2-hidden-accessible')) {
                                if ($select.prop('multiple')) {
                                    $select.select2({
                                        theme: 'bootstrap4',
                                        width: '100%',
                                        placeholder: 'Select Associated Divisions',
                                        allowClear: true
                                    });
                                } else {
                                    $select.select2({
                                        theme: 'bootstrap4',
                                        width: '100%'
                                    });
                                }
                            }
                        });
                    }
                    if (typeof $().datepicker === 'function') {
                        $('#contracts-container .datepicker').datepicker({
                            format: 'yyyy-mm-dd',
                            autoclose: true,
                            todayHighlight: true
                        });
                    }
                } else {
                    $('#contracts-container').html('<div class="alert alert-info">No contracts found.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#contracts-container').html('<div class="alert alert-danger">Error loading contracts. Please refresh the page.</div>');
            }
        });
    }
    
    // Render pagination
    function renderPagination(total, page, perPage) {
        const totalPages = Math.ceil(total / perPage);
        if (totalPages <= 1) {
            $('#contracts-pagination').html('<div class="text-muted small">Total: ' + total + ' contracts</div>');
            return;
        }
        
        let html = '<div class="d-flex justify-content-between align-items-center">';
        html += '<div class="text-muted small">Total: ' + total + ' contracts</div>';
        html += '<div class="pagination-buttons">';
        
        // Previous button
        html += `<button type="button" class="btn btn-outline-secondary btn-sm me-1" 
                ${page === 0 ? 'disabled' : ''} 
                onclick="loadContractsPage(${page - 1})">
            <i class="bx bx-chevron-left"></i> Previous
        </button>`;
        
        // Page numbers
        const startPage = Math.max(0, page - 2);
        const endPage = Math.min(totalPages - 1, page + 2);
        
        if (startPage > 0) {
            html += `<button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="loadContractsPage(0)">1</button>`;
            if (startPage > 1) {
                html += `<span class="btn btn-outline-secondary btn-sm disabled me-1">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button type="button" class="btn ${i === page ? 'btn-primary' : 'btn-outline-secondary'} btn-sm me-1" 
                    onclick="loadContractsPage(${i})">${i + 1}</button>`;
        }
        
        if (endPage < totalPages - 1) {
            if (endPage < totalPages - 2) {
                html += `<span class="btn btn-outline-secondary btn-sm disabled me-1">...</span>`;
            }
            html += `<button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="loadContractsPage(${totalPages - 1})">${totalPages}</button>`;
        }
        
        // Next button
        html += `<button type="button" class="btn btn-outline-secondary btn-sm" 
                ${page >= totalPages - 1 ? 'disabled' : ''} 
                onclick="loadContractsPage(${page + 1})">
            Next <i class="bx bx-chevron-right"></i>
        </button>`;
        
        html += '</div></div>';
        $('#contracts-pagination').html(html);
    }
    
    // Global function for pagination
    window.loadContractsPage = function(page) {
        loadContracts(page);
    };
    
    // Initial load
    loadContracts(0);
    
    // Initialize Select2 for modals when they are shown (for dynamically loaded modals)
    $(document).on('shown.bs.modal', '.modal', function() {
        var $modal = $(this);
        // Initialize all Select2 fields in the modal
        $modal.find('.select2').each(function() {
            var $select = $(this);
            // Destroy existing Select2 if already initialized
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            // Reinitialize Select2
            if ($select.prop('multiple')) {
                $select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    dropdownParent: $modal,
                    placeholder: 'Select Associated Divisions',
                    allowClear: true
                });
            } else {
                $select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    dropdownParent: $modal
                });
            }
        });
        
        // Initialize datepicker for modals
        if (typeof $().datepicker === 'function') {
            $modal.find('.datepicker').each(function() {
                var $datepicker = $(this);
                // Destroy existing datepicker if initialized
                if ($datepicker.data('datepicker')) {
                    $datepicker.datepicker('destroy');
                }
                // Reinitialize datepicker
                $datepicker.datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true
                });
            });
        }
    });
    
    // Also initialize Select2 for initial modals (fallback if JS disabled)
    $(document).ready(function() {
        // Initialize Select2 for modals that are already in the DOM
        $('.modal .select2').each(function() {
            var $select = $(this);
            var $modal = $select.closest('.modal');
            if ($select.prop('multiple')) {
                $select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    dropdownParent: $modal.length ? $modal : $('body'),
                    placeholder: 'Select Associated Divisions',
                    allowClear: true
                });
            } else {
                $select.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    dropdownParent: $modal.length ? $modal : $('body')
                });
            }
        });
    });
});
</script>

<!-- Edit Biodata Modal -->
<div class="modal fade" id="edit_biodata<?php echo $staff_id; ?>" tabindex="-1" aria-labelledby="editBiodataLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBiodataLabel">Edit Employee Biodata: <?= ($this_staff->title ?? '') . ' ' . ($this_staff->fname ?? '') . ' ' . ($this_staff->lname ?? '') . ' ' . ($this_staff->oname ?? '') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php echo validation_errors(); ?>
                <?php echo form_open('staff/update_staff'); ?>
                <div class="row">
                    <div class="col-md-6">
                        <h4>Personal Information</h4>

                        <div class="form-group">
                            <label for="SAPNO">SAP Number:<?=asterik()?></label>
                            <input type="text" class="form-control" value="<?= $this_staff->SAPNO ?? '' ?>" name="SAPNO" id="SAPNO">
                        </div>

                        <div class="form-group">
                            <label for="title">Title:<?=asterik()?></label>
                            <select class="form-control select2" name="title" id="title" required>
                                <?php if (!empty($this_staff->title)) { ?>
                                    <option value="<?php echo $this_staff->title ?>"><?php echo $this_staff->title ?></option>
                                <?php } ?>
                                <option value="">Select Title</option>
                                <option value="Dr">Dr</option>
                                <option value="Prof">Prof</option>
                                <option value="Rev">Rev</option>
                                <option value="Mr">Mr</option>
                                <option value="Mrs">Mrs</option>
                                <option value="Ms">Ms</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fname">First Name:<?=asterik()?></label>
                            <input type="text" class="form-control" value="<?php echo $this_staff->fname ?? ''; ?>" name="fname" id="fname" required>
                        </div>
                        <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">

                        <div class="form-group">
                            <label for="lname">Last Name:<?=asterik()?></label>
                            <input type="text" class="form-control" name="lname" value="<?php echo $this_staff->lname ?? ''; ?>" id="lname" required>
                        </div>

                        <div class="form-group">
                            <label for="oname">Other Name:</label>
                            <input type="text" class="form-control" value="<?php echo $this_staff->oname ?? ''; ?>" name="oname" id="oname">
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth:<?=asterik()?></label>
                            <input type="text" class="form-control datepicker" value="<?php echo $this_staff->date_of_birth ?? ''; ?>" name="date_of_birth" id="date_of_birth" required>
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender:<?=asterik()?></label>
                            <select class="form-control select2" name="gender" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php if (($this_staff->gender ?? '') == 'Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if (($this_staff->gender ?? '') == 'Female') echo 'selected'; ?>>Female</option>
                                <option value="Other" <?php if (($this_staff->gender ?? '') == 'Other') echo 'selected'; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="nationality_id">Nationality:<?=asterik()?></label>
                            <select class="form-control select2" name="nationality_id" id="nationality_id" required>
                                <option value="">Select Nationality</option>
                                <?php $lists = Modules::run('lists/nationality');
                                foreach ($lists as $list) :
                                ?>
                                    <option value="<?php echo $list->nationality_id; ?>" <?php if (($this_staff->nationality_id ?? '') == $list->nationality_id) {
                                        echo "selected";
                                    } ?>><?php echo $list->status; ?><?php echo $list->nationality; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="initiation_date">Initiation Date: <?=asterik()?></label>
                            <input type="text" class="form-control datepicker" value="<?php echo $this_staff->initiation_date ?? ''; ?>" name="initiation_date" id="initiation_date" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h4>Contact Information</h4>

                        <div class="form-group">
                            <label for="tel_1">Telephone 1: <?=asterik()?></label>
                            <input type="text" class="form-control" value="<?php echo $this_staff->tel_1 ?? ''; ?>" name="tel_1" id="tel_1" required>
                        </div>

                        <div class="form-group">
                            <label for="tel_2">Telephone 2:</label>
                            <input type="text" class="form-control" value="<?php echo $this_staff->tel_2 ?? ''; ?>" name="tel_2" id="tel_2">
                        </div>

                        <div class="form-group">
                            <label for="whatsapp">WhatsApp:</label>
                            <input type="text" class="form-control" name="whatsapp" value="<?php echo $this_staff->whatsapp ?? ''; ?>" id="whatsapp">
                        </div>

                        <div class="form-group">
                            <label for="work_email">Work Email:<?=asterik()?></label>
                            <input type="email" class="form-control" name="work_email" value="<?php echo $this_staff->work_email ?? ''; ?>" id="work_email" required>
                        </div>
                        <br>
                        <div class="form-group">
                            <label for="private_email">Private Email:</label>
                            <input type="email" class="form-control" name="private_email" value="<?php echo $this_staff->private_email ?? ''; ?>" id="private_email">
                        </div>

                        <div class="form-group">
                            <label for="physical_location">Physical Location:</label>
                            <textarea class="form-control" name="physical_location" id="physical_location" rows="2"><?php echo $this_staff->physical_location ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-group text-end mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Changes</button>
                </div>

                <?php echo form_close(); ?>
            </div>
    </div>
  </div>
</div>

<script>
// Initialize Select2 and datepicker for the edit biodata modal
$(document).on('shown.bs.modal', '#edit_biodata<?php echo $staff_id; ?>', function() {
    var $modal = $(this);
    
    // Initialize Select2 in the modal
    $modal.find('.select2').each(function() {
        var $select = $(this);
        if (!$select.hasClass('select2-hidden-accessible')) {
            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: $modal
            });
        }
    });
    
    // Initialize datepicker in the modal
    if (typeof $().datepicker === 'function') {
        $modal.find('.datepicker').each(function() {
            var $datepicker = $(this);
            if (!$datepicker.data('datepicker')) {
                $datepicker.datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true
                });
            }
        });
    }
});
</script>


