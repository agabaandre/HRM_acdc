<div class="nav-container primary-menu">
    <div class="mobile-topbar-header">
        <div>
            <img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" class="logo-icon" alt="Africa CDC Logo" style="filter: brightness(0) invert(1);">
        </div>
        <div>
            <h5 class="logo-text">Staff Portal</h5>
        </div>
        <div class="toggle-icon ms-auto"><i class='bx bx-arrow-to-left'></i>
        </div>
    </div>
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start">
            <!-- Dashboard -->
            <?php if (in_array('76', $permissions)) : ?>
                <li class="nav-item">
                    <a href="<?= base_url('dashboard') ?>" class="nav-link <?= activelink('dashboard', $this->uri->segment(1)) ?>">
                        <div class=""><i class="bx bx-home"></i></div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Staff Profile -->
            <?php if (in_array('72', $permissions) || in_array('41', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('staff', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-user"></i></div>
                        <div class="menu-title">Staff Profile</div>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (in_array('72', $permissions)) : ?>
                            <?php if (in_array('71', $permissions)) : ?>
                                <li><a class="dropdown-item" href="<?= base_url('staff/search') ?>"><i class="bx bx-right-arrow-alt"></i>Quick Search</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/new') ?>"><i class="bx bx-right-arrow-alt"></i>Add New Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/all_staff') ?>"><i class="bx bx-right-arrow-alt"></i>All Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/index') ?>"><i class="bx bx-right-arrow-alt"></i>Current Staff List</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/2') ?>"><i class="bx bx-right-arrow-alt"></i>Contracts Due</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/3') ?>"><i class="bx bx-right-arrow-alt"></i>Contracts Expired</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/4') ?>"><i class="bx bx-right-arrow-alt"></i>Former Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/7') ?>"><i class="bx bx-right-arrow-alt"></i>Under Renewal</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (in_array('41', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('staff/staff_birthday') ?>"><i class="bx bx-right-arrow-alt"></i>Staff Birthdays</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Attendance -->
            <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('83', $permissions)) : ?>
                <!-- <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('attendance', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-clock"></i></div>
                        <div class="menu-title">Attendance</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('attendance/upload') ?>"><i class="bx bx-right-arrow-alt"></i>Upload Data</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/person') ?>"><i class="bx bx-right-arrow-alt"></i>Person Attendance</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/status') ?>"><i class="bx bx-right-arrow-alt"></i>Time Logs</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/time_sheet') ?>"><i class="bx bx-right-arrow-alt"></i>Time Sheet</a></li>
                    </ul>
                </li> -->
            <?php endif; ?>

            <!-- Leave -->
            <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('37', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('leave', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="bx bx-calendar-check"></i></div>
                        <div class="menu-title">Leave Application</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('leave/request') ?>"><i class="bx bx-right-arrow-alt"></i>Apply</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('leave/approve_leave') ?>"><i class="bx bx-right-arrow-alt"></i>Approve Leave</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('leave/status') ?>"><i class="bx bx-right-arrow-alt"></i>My Leave Status</a></li>
                        <?php if (in_array('77', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('leave/status/all') ?>"><i class="bx bx-right-arrow-alt"></i>Leave Status</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Performance -->
            <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('74', $permissions)) : ?>
                <?php
                // Calculate pending approvals count for badge
                $staff_id = $this->session->userdata('user')->staff_id;
                $pendings = $this->per_mdl->get_all_pending_approvals($staff_id);
                $approvals_count = count($pendings);
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('performance', $this->uri->segment(1)) ?>" href="<?php echo base_url() ?>/performance/ppa_dashboard" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-line-chart"></i></div>
                        <div class="menu-title">Performance<?php if ($approvals_count > 0): ?><span class="badge bg-danger ms-2"><?= $approvals_count ?></span><?php endif; ?></div>
                    </a>
                    <?php //dd($ppa_exists); ?>
                    <ul class="dropdown-menu">
                        <?php if (in_array('38', $permissions) && !$ppa_exists) : ?>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#navCreatePPAModal">
                                    <i class="bx bx-right-arrow-alt"></i>Create PPA
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (in_array('38', $permissions) && $ppa_exists) : ?>
                            <li><a class="dropdown-item" href="<?= base_url("performance/recent_ppa/{$ppa_entryid}/" . $this->session->userdata('user')->staff_id) ?>"><i class="bx bx-right-arrow-alt"></i>My Current PPA</a></li>
                        <?php endif; ?>
                        
                        <?php
                           // Show Mid Term menu link if user has at least one approved PPA (allows creating midterms for any approved period)
                           // Get periods for dropdown - only approved PPAs for this staff (draft_status = 2 means approved)
                           // draft_status: 0 = submitted, 1 = draft, 2 = approved
                           $nav_midterm_staff_id = $this->session->userdata('user')->staff_id;
                           $nav_midterm_periods = $this->db->query(
                               'SELECT DISTINCT performance_period 
                               FROM ppa_entries 
                               WHERE staff_id = ? 
                               AND draft_status = 2
                               ORDER BY performance_period DESC', 
                               [$nav_midterm_staff_id]
                           )->result();
                           
                           // Show button if user has at least one approved PPA (regardless of current period)
                           if (!empty($nav_midterm_periods)): ?>
                              <li>
                                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#navMidtermModal">
                                      <i class="fa fa-plus"></i> Create Midterm
                                  </a>
                              </li>
                           <?php endif; ?>

                        <?php if (in_array('38', $permissions) && $midterm_exists) : ?>
                            <li><a class="dropdown-item" href="<?= base_url("performance/midterm/recent_midterm/{$ppa_entryid}/" . $this->session->userdata('user')->staff_id) ?>"><i class="bx bx-right-arrow-alt"></i>My Current Midterm</a></li>
                     <?php endif; ?>
                        
                        <?php
                           // Show End Term menu link if end_term_start date has passed (allows creating endterms for previous periods)
                           // Note: We allow showing this even if endterm exists for current period, as user may need to create for previous periods
                           $endterm_start_passed = isset($ppa_settings->end_term_start) && $today >= $ppa_settings->end_term_start;
                           
                           // Get periods for dropdown - only approved PPAs with approved midterms for this staff
                           // draft_status: 0 = submitted, 1 = draft, 2 = approved
                           // midterm_draft_status: 0 = submitted, 1 = draft, 2 = approved
                           $nav_staff_id = $this->session->userdata('user')->staff_id;
                           $nav_periods = $this->db->query(
                               'SELECT DISTINCT performance_period 
                               FROM ppa_entries 
                               WHERE staff_id = ? 
                               AND draft_status = 2
                               AND midterm_draft_status = 2
                               ORDER BY performance_period DESC', 
                               [$nav_staff_id]
                           )->result();
                           
                           if (
                               $endterm_start_passed 
                           ): ?>
                              <li>
                                  <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#navEndtermModal">
                                      <i class="fa fa-plus"></i> Create Endterm
                                  </a>
                              </li>
                           <?php endif; ?>
                        
                        <?php 
                        $endterm_exists = $this->per_mdl->isendterm_available($ppa_entryid);
                        if (in_array('38', $permissions) && $endterm_exists) : ?>
                            <li><a class="dropdown-item" href="<?= base_url("performance/endterm/recent_endterm/{$ppa_entryid}/" . $this->session->userdata('user')->staff_id) ?>"><i class="bx bx-right-arrow-alt"></i>My Current Endterm</a></li>
                     <?php endif; ?>
                        
                        <?php if (in_array('82', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('performance/all_ppas') ?>"><i class="bx bx-right-arrow-alt"></i>All PPAs Status</a></li>
                        <?php endif; ?>

                 
                        <li><a class="dropdown-item" href="<?= base_url('performance/my_ppas') ?>"><i class="bx bx-right-arrow-alt"></i>My PPAs</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('performance/pending_approval') ?>"><i class="bx bx-right-arrow-alt"></i>Pending Action <span class="badge bg-danger ms-1"><?= isset($approvals_count) ? $approvals_count : 0 ?></span></a></li>
                        <li><a class="dropdown-item" href="<?= base_url('performance/approved_by_me') ?>"><i class="bx bx-right-arrow-alt"></i>All Approved PPAs</a></li>


                    </ul>
                </li>
                
                <!-- Endterm Period Selection Modal (for nav menu) -->
                <?php if ($endterm_start_passed): ?>
                <div class="modal fade" id="navEndtermModal" tabindex="-1" aria-labelledby="navEndtermModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="navEndtermModalLabel">Select Period for Endterm</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <?php echo form_open('performance/endterm/create_for_period', ['id' => 'navEndtermForm']); ?>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="nav_endterm_period" class="form-label">Performance Period</label>
                                        <select name="period" id="nav_endterm_period" class="form-control" required>
                                            <option value="">-- Select Period --</option>
                                            <?php if (!empty($nav_periods)): ?>
                                                <?php 
                                                $current_period_formatted = str_replace(' ', '-', current_period());
                                                foreach ($nav_periods as $period): 
                                                    $is_selected = ($period->performance_period == $current_period_formatted) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $period->performance_period ?>" <?= $is_selected ?>>
                                                        <?= str_replace('-', ' ', $period->performance_period) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <input type="hidden" name="staff_id" value="<?= $nav_staff_id ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-info">Create Endterm</button>
                                </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Move modal to body immediately to avoid z-index issues with nav-container
                        var modalElement = document.getElementById('navEndtermModal');
                        if (modalElement && modalElement.closest('.nav-container')) {
                            document.body.appendChild(modalElement);
                        }
                        
                        // Clean up backdrop when modal is hidden
                        if (modalElement) {
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                // Remove any lingering backdrop elements
                                var backdrops = document.querySelectorAll('.modal-backdrop');
                                backdrops.forEach(function(backdrop) {
                                    backdrop.remove();
                                });
                                // Remove modal-open class from body
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';
                            });
                        }
                        
                        // Handle modal opening from dropdown
                        var createEndtermLink = document.querySelector('a[data-bs-target="#navEndtermModal"]');
                        if (createEndtermLink) {
                            createEndtermLink.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                // Close dropdown menu
                                var dropdownToggle = document.querySelector('.nav-link.dropdown-toggle[data-bs-toggle="dropdown"]');
                                if (dropdownToggle) {
                                    var dropdownInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                                    if (dropdownInstance) {
                                        dropdownInstance.hide();
                                    }
                                }
                                
                                // Close all open dropdowns
                                document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                                    menu.classList.remove('show');
                                });
                                
                                // Small delay to ensure dropdown closes before modal opens
                                setTimeout(function() {
                                    var modalEl = document.getElementById('navEndtermModal');
                                    if (modalEl) {
                                        // Ensure modal is in body (in case it wasn't moved earlier)
                                        if (modalEl.closest('.nav-container')) {
                                            document.body.appendChild(modalEl);
                                        }
                                        
                                        var modal = new bootstrap.Modal(modalEl, {
                                            backdrop: true,
                                            keyboard: true,
                                            focus: true
                                        });
                                        
                                        modal.show();
                                    }
                                }, 150);
                            });
                        }
                    });
                </script>
                <?php endif; ?>
                
                <!-- Create Midterm Period Selection Modal (for nav menu) -->
                <?php if (!empty($nav_midterm_periods)): ?>
                <div class="modal fade" id="navMidtermModal" tabindex="-1" aria-labelledby="navMidtermModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="navMidtermModalLabel">Select Period for Midterm</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <?php echo form_open('performance/midterm/create_for_period', ['id' => 'navMidtermForm']); ?>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="nav_midterm_period" class="form-label">Performance Period</label>
                                        <select name="period" id="nav_midterm_period" class="form-control" required>
                                            <option value="">-- Select Period --</option>
                                            <?php if (!empty($nav_midterm_periods)): ?>
                                                <?php 
                                                $current_period_formatted = str_replace(' ', '-', current_period());
                                                foreach ($nav_midterm_periods as $period): 
                                                    $is_selected = ($period->performance_period == $current_period_formatted) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $period->performance_period ?>" <?= $is_selected ?>>
                                                        <?= str_replace('-', ' ', $period->performance_period) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <input type="hidden" name="staff_id" value="<?= $nav_midterm_staff_id ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-info">Create Midterm</button>
                                </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Move modal to body immediately to avoid z-index issues with nav-container
                        var modalElement = document.getElementById('navMidtermModal');
                        if (modalElement && modalElement.closest('.nav-container')) {
                            document.body.appendChild(modalElement);
                        }
                        
                        // Clean up backdrop when modal is hidden
                        if (modalElement) {
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                // Remove any lingering backdrop elements
                                var backdrops = document.querySelectorAll('.modal-backdrop');
                                backdrops.forEach(function(backdrop) {
                                    backdrop.remove();
                                });
                                // Remove modal-open class from body
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';
                            });
                        }
                        
                        // Handle modal opening from dropdown
                        var createMidtermLink = document.querySelector('a[data-bs-target="#navMidtermModal"]');
                        if (createMidtermLink) {
                            createMidtermLink.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                // Close dropdown menu
                                var dropdownToggle = document.querySelector('.nav-link.dropdown-toggle[data-bs-toggle="dropdown"]');
                                if (dropdownToggle) {
                                    var dropdownInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                                    if (dropdownInstance) {
                                        dropdownInstance.hide();
                                    }
                                }
                                
                                // Close all open dropdowns
                                document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                                    menu.classList.remove('show');
                                });
                                
                                // Small delay to ensure dropdown closes before modal opens
                                setTimeout(function() {
                                    var modalEl = document.getElementById('navMidtermModal');
                                    if (modalEl) {
                                        // Ensure modal is in body (in case it wasn't moved earlier)
                                        if (modalEl.closest('.nav-container')) {
                                            document.body.appendChild(modalEl);
                                        }
                                        
                                        var modal = new bootstrap.Modal(modalEl, {
                                            backdrop: true,
                                            keyboard: true,
                                            focus: true
                                        });
                                        
                                        modal.show();
                                    }
                                }, 150);
                            });
                        }
                    });
                </script>
                <?php endif; ?>
                
                <!-- Create PPA Period Selection Modal (for nav menu) -->
                <?php if (in_array('38', $permissions) && !$ppa_exists): ?>
                    <?php
                    // Get all available periods for PPA creation dropdown
                    $nav_ppa_staff_id = $this->session->userdata('user')->staff_id;
                    // Get all distinct periods from ppa_entries, plus current period if not in list
                    $nav_ppa_periods = $this->db->query(
                        'SELECT DISTINCT performance_period 
                        FROM ppa_entries 
                        ORDER BY performance_period DESC'
                    )->result();
                    
                    // Add current period if not already in the list
                    $current_period_formatted = str_replace(' ', '-', current_period());
                    $period_exists = false;
                    foreach ($nav_ppa_periods as $p) {
                        if ($p->performance_period == $current_period_formatted) {
                            $period_exists = true;
                            break;
                        }
                    }
                    if (!$period_exists) {
                        $current_period_obj = new stdClass();
                        $current_period_obj->performance_period = $current_period_formatted;
                        array_unshift($nav_ppa_periods, $current_period_obj);
                    }
                    ?>
                    <div class="modal fade" id="navCreatePPAModal" tabindex="-1" aria-labelledby="navCreatePPAModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="navCreatePPAModalLabel">Select Period for PPA</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="get" action="<?= base_url('performance') ?>" id="navCreatePPAForm">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="nav_ppa_period" class="form-label">Performance Period</label>
                                            <select name="period" id="nav_ppa_period" class="form-control" required>
                                                <option value="">-- Select Period --</option>
                                                <?php if (!empty($nav_ppa_periods)): ?>
                                                    <?php 
                                                    foreach ($nav_ppa_periods as $period): 
                                                        $is_selected = ($period->performance_period == $current_period_formatted) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $period->performance_period ?>" <?= $is_selected ?>>
                                                            <?= str_replace('-', ' ', $period->performance_period) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Create PPA</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Move PPA modal to body immediately to avoid z-index issues with nav-container
                            var ppaModalElement = document.getElementById('navCreatePPAModal');
                            if (ppaModalElement && ppaModalElement.closest('.nav-container')) {
                                document.body.appendChild(ppaModalElement);
                            }
                            
                            // Clean up backdrop when modal is hidden
                            if (ppaModalElement) {
                                ppaModalElement.addEventListener('hidden.bs.modal', function() {
                                    // Remove any lingering backdrop elements
                                    var backdrops = document.querySelectorAll('.modal-backdrop');
                                    backdrops.forEach(function(backdrop) {
                                        backdrop.remove();
                                    });
                                    // Remove modal-open class from body
                                    document.body.classList.remove('modal-open');
                                    document.body.style.overflow = '';
                                    document.body.style.paddingRight = '';
                                });
                            }
                            
                            // Handle PPA modal opening from dropdown
                            var createPPALink = document.querySelector('a[data-bs-target="#navCreatePPAModal"]');
                            if (createPPALink) {
                                createPPALink.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    
                                    // Close dropdown menu
                                    var dropdownToggle = document.querySelector('.nav-link.dropdown-toggle[data-bs-toggle="dropdown"]');
                                    if (dropdownToggle) {
                                        var dropdownInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                                        if (dropdownInstance) {
                                            dropdownInstance.hide();
                                        }
                                    }
                                    
                                    // Close all open dropdowns
                                    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                                        menu.classList.remove('show');
                                    });
                                    
                                    // Small delay to ensure dropdown closes before modal opens
                                    setTimeout(function() {
                                        var modalEl = document.getElementById('navCreatePPAModal');
                                        if (modalEl) {
                                            // Ensure modal is in body (in case it wasn't moved earlier)
                                            if (modalEl.closest('.nav-container')) {
                                                document.body.appendChild(modalEl);
                                            }
                                            
                                            var modal = new bootstrap.Modal(modalEl, {
                                                backdrop: true,
                                                keyboard: true,
                                                focus: true
                                            });
                                            
                                            modal.show();
                                        }
                                    }, 150);
                                });
                            }
                        });
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Weekly Task Planner -->
            <?php if (in_array('78', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('weektasks', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="bx bx-task"></i></div>
                        <div class="menu-title">Weekly Task Planner</div>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (in_array('79', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('workplan') ?>"><i class="bx bx-right-arrow-alt"></i>Workplan</a></li>
                        <?php endif; ?>
                        <?php if (in_array('81', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('tasks/activity') ?>"><i class="bx bx-right-arrow-alt"></i>Sub Activities</a></li>
                        <?php endif; ?>
                        <?php if (in_array('75', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('weektasks/tasks') ?>"><i class="bx bx-right-arrow-alt"></i>Weekly Tasks</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Domain Controller -->
            <?php if (in_array('77', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-shield-alt"></i></div>
                        <div class="menu-title">Domain Controller</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('admanager/expired_accounts') ?>"><i class="bx bx-right-arrow-alt"></i>Accounts to Disable</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('admanager/report') ?>"><i class="bx bx-right-arrow-alt"></i>Disabled Accounts</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Settings -->
            <?php if (in_array('15', $permissions)) : ?>
                <li class="nav-item">
                    <a href="<?= base_url('settings') ?>" class="nav-link <?= activelink('settings', $this->uri->segment(1)) ?>">
                        <div class="parent-icon"><i class="fa fa-cog"></i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Users -->
            <?php if (in_array('17', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('auth', $this->uri->segment(1), 'permissions') ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-users"></i></div>
                        <div class="menu-title">Users</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('auth/users') ?>"><i class="bx bx-right-arrow-alt"></i>Manage Users</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('permissions') ?>"><i class="bx bx-right-arrow-alt"></i>Access Permissions</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('auth/logs') ?>"><i class="bx bx-right-arrow-alt"></i>Audit Logs</a></li>
                    </ul>
                </li>
            <?php endif; ?>

        </ul>
    </nav>
</div>