<div class="card shadow-sm p-3 mb-4 border rounded" style="background-color: #f9f9f9;">
    <div class="row g-3 align-items-end">
        <?php if (!empty($staff_history_mode ?? null)) :
            $pf = $this->input->get('period_from') ?: ($period_from_default ?? date('Y-01-01'));
            $pt = $this->input->get('period_to') ?: ($period_to_default ?? date('Y-m-d'));
            ?>
        <div class="col-md-12 mb-2">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="period_from" class="form-label fw-bold">Report period from</label>
                    <input type="date" id="period_from" name="period_from" class="form-control" required
                           value="<?= html_escape($pf) ?>">
                </div>
                <div class="col-md-3">
                    <label for="period_to" class="form-label fw-bold">Report period to</label>
                    <input type="date" id="period_to" name="period_to" class="form-control" required
                           value="<?= html_escape($pt) ?>">
                </div>
                <div class="col-md-6 small text-muted align-self-end">
                    Staff are included if any contract overlaps these dates (start ≤ period end and end ≥ period start, or open-ended contract).
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $regions_list = $this->db->order_by('region_name', 'ASC')->get('regions')->result();
        $nationalities_list = $this->db->order_by('nationality', 'ASC')->get('nationalities')->result();
        $region_id_get = $this->input->get('region_id');
        ?>

        <div class="col-md-2">
            <label for="lname" class="form-label fw-bold">Name</label>
            <input type="text" name="lname" class="form-control" value="<?= $this->input->get('lname') ?>" placeholder="Enter Name">
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Gender</label>
            <select class="form-control select2" name="gender">
                <option value="">Select Gender</option>
                <option value="Male" <?= ($this->input->get('gender') == 'Male') ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($this->input->get('gender') == 'Female') ? 'selected' : '' ?>>Female</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">SAP NO</label>
            <input type="text" name="SAPNO" class="form-control" value="<?= $this->input->get('SAPNO') ?>" placeholder="SAP Number">
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold" for="staff_filter_region_id">Region</label>
            <select class="form-control select2" name="region_id" id="staff_filter_region_id">
                <option value="">All regions</option>
                <option value="0" <?= ($region_id_get !== null && $region_id_get !== '' && (int) $region_id_get === 0) ? 'selected' : '' ?>>Rest of World</option>
                <?php foreach ($regions_list as $rg) : ?>
                    <option value="<?= (int) $rg->id ?>" <?= ((string) (int) $region_id_get === (string) (int) $rg->id) ? 'selected' : '' ?>>
                        <?= html_escape($rg->region_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold" for="staff_filter_nationality_id">Nationality</label>
            <select class="form-control select2" name="nationality_id" id="staff_filter_nationality_id">
                <option value="">Select Nationality</option>
                <?php foreach ($nationalities_list as $n) : ?>
                    <option value="<?= (int) $n->nationality_id ?>"
                        data-region-id="<?= (int) $n->region_id ?>"
                        <?= ($this->input->get('nationality_id') == $n->nationality_id) ? 'selected' : '' ?>>
                        <?= html_escape($n->nationality) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Division(s)</label>
            <select class="form-control select2" name="division_id[]" multiple>
                <?php foreach ($divisions as $division): ?>
                    <option value="<?= $division->division_id ?>"
                        <?= (!empty($this->input->get('division_id')) && in_array($division->division_id, $this->input->get('division_id'))) ? 'selected' : '' ?>>
                        <?= $division->division_name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Duty Station(s)</label>
            <select class="form-control select2" name="duty_station_id[]" multiple>
                <?php foreach ($duty_stations as $station): ?>
                    <option value="<?= $station->duty_station_id ?>"
                        <?= (!empty($this->input->get('duty_station_id')) && in_array($station->duty_station_id, $this->input->get('duty_station_id'))) ? 'selected' : '' ?>>
                        <?= $station->duty_station_name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Funder(s)</label>
            <select class="form-control select2" name="funder_id[]" multiple>
                <?php 
				$funders = $this->db->get('funders')->result();
				foreach ($funders as $funder): ?>
                    <option value="<?= $funder->funder_id ?>"
                        <?= (!empty($this->input->get('funder_id')) && in_array($funder->funder_id, $this->input->get('funder_id'))) ? 'selected' : '' ?>>
                        <?= $funder->funder ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Job(s)</label>
            <select class="form-control select2" name="job_id[]" multiple>
                <?php 
				if (!empty($jobs)) {
					foreach ($jobs as $job): ?>
                    <option value="<?= $job->job_id ?>"
                        <?= (!empty($this->input->get('job_id')) && in_array($job->job_id, $this->input->get('job_id'))) ? 'selected' : '' ?>>
                        <?= $job->job_name ?>
                    </option>
                <?php endforeach; 
				} ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold">Grade(s)</label>
            <select class="form-control select2" name="grade_id[]" multiple>
                <?php 
				if (!empty($grades)) {
					foreach ($grades as $grade): ?>
                    <option value="<?= $grade->grade_id ?>"
                        <?= (!empty($this->input->get('grade_id')) && in_array($grade->grade_id, $this->input->get('grade_id'))) ? 'selected' : '' ?>>
                        <?= $grade->grade ?>
                    </option>
                <?php endforeach; 
				} ?>
            </select>
        </div>

        <!-- Apply Button -->
        <div class="col-md-2 ms-auto text-end">
            <button type="submit" class="btn btn-success w-100">
                <i class="fa fa-filter me-1"></i> Apply Filters
            </button>
        </div>

    </div>
</div>
<?php if (!empty($this->input->get())): ?>
    <div class="alert alert-info small mt-2">
        <strong>Filters Applied:</strong>
        <?php
        // Mappings for label replacements
        $label_map = [
            'lname' => 'Name',
            'SAPNO' => 'SAP No',
            'gender' => 'Gender',
            'region_id' => 'Region',
            'nationality_id' => 'Nationality',
            'division_id' => 'Division',
            'duty_station_id' => 'Duty Station',
            'funder_id' => 'Funder',
            'job_id' => 'Job',
            'grade_id' => 'Grade',
            'period_from' => 'Period from',
            'period_to' => 'Period to',
        ];

        // Create lookup maps
        $division_map = array_column($divisions, 'division_name', 'division_id');
        $station_map  = array_column($duty_stations, 'duty_station_name', 'duty_station_id');
        $funder_map   = array_column($funders, 'funder', 'funder_id');
        $job_map      = !empty($jobs) ? array_column($jobs, 'job_name', 'job_id') : [];
        $grade_map    = !empty($grades) ? array_column($grades, 'grade', 'grade_id') : [];
        $region_map     = [0 => 'Rest of World'];
        foreach ($regions_list as $rg) {
            $region_map[(int) $rg->id] = $rg->region_name;
        }

        foreach ($this->input->get() as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (is_array($value) && count($value) === 0) {
                continue;
            }
            // Convert label
            $label = $label_map[$key] ?? ucwords(str_replace('_', ' ', $key));

            // Handle mappings
            if ($key === 'region_id') {
                $value = $region_map[(int) $value] ?? $value;
            } elseif ($key === 'nationality_id') {
                $value = getcountry($value);
            } elseif ($key === 'division_id') {
                    $value = array_map(fn($id) => $division_map[$id] ?? $id, (array)$value);
                    $value = implode(', ', $value);
                } elseif ($key === 'duty_station_id') {
                    $value = array_map(fn($id) => $station_map[$id] ?? $id, (array)$value);
                    $value = implode(', ', $value);
                } elseif ($key === 'funder_id') {
                    $value = array_map(fn($id) => $funder_map[$id] ?? $id, (array)$value);
                    $value = implode(', ', $value);
                } elseif ($key === 'job_id') {
                    $value = array_map(fn($id) => $job_map[$id] ?? $id, (array)$value);
                    $value = implode(', ', $value);
                } elseif ($key === 'grade_id') {
                    $value = array_map(fn($id) => $grade_map[$id] ?? $id, (array)$value);
                    $value = implode(', ', $value);
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }

            echo '<span class="badge bg-secondary me-1">' . html_escape($label) . ': ' . html_escape((string) $value) . '</span>';
        }
        ?>
    </div>
<?php endif; ?>
<div class="row mt-0 mb-2 p-1">
    <div class="col-md-12 d-flex justify-content-between align-items-center">

        <!-- Pagination and Total Records (left side) - Hidden for AJAX pages -->
        <div class="d-flex align-items-center flex-wrap gap-2 p-1" style="display: none !important;">
            <div class="pagination-links">
                <?= isset($links) ? $links : '' ?>
            </div>
            <div class="record-count text-muted small">
                <?= (isset($records) ? $records : 0) . " Records" ?>
            </div>
        </div>

        <!-- Export buttons (right side) -->
        <div class="export-buttons d-flex gap-2 p-1" id="originalExportButtons">
            <?php
          
            // Get all current GET parameters
            $query_string = http_build_query($this->input->get());
            
          
            $segment2 = $this->uri->segment(2);
          
            $status = $this->uri->segment(3);
            //dd($status);

            if ($segment2 === 'all_staff') {
                // Main staff list
                ?>
                <a href="<?= base_url('staff/all_staff/1?' . $query_string) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-file-csv me-1"></i> Export CSV
                </a>
                <a href="<?= base_url('staff/all_staff/0/1?' . $query_string) ?>" class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-file-pdf me-1"></i> Export PDF
                </a>
            <?php
            } elseif ($segment2 === 'staff_history') {
                ?>
                <a href="<?= base_url('staff/staff_history/1?' . $query_string) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-file-csv me-1"></i> Export CSV
                </a>
                <a href="<?= base_url('staff/staff_history/0/1?' . $query_string) ?>" class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-file-pdf me-1"></i> Export PDF
                </a>
            <?php
            } elseif ($segment2 === 'contract_status') {
                // Contract status page
                ?>
                <a href="<?= base_url("staff/contract_status/{$status}/1?" . $query_string) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-file-csv me-1"></i> Export CSV
                </a>
                <a href="<?= base_url("staff/contract_status/{$status}/0/1?" . $query_string) ?>" class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-file-pdf me-1"></i> Export PDF
                </a>
            <?php
            } elseif ($segment2 == '' || $segment2 === 'index') {
                // Index (current staff) list
                ?>
                <a href="<?= base_url("staff/index/1?" . $query_string) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-file-csv me-1"></i> Export CSV
                </a>
                <a href="<?= base_url("staff/index/0/1?" . $query_string) ?>" class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-file-pdf me-1"></i> Export PDF
                </a>
            <?php } ?>
        </div>
    </div>
</div>
<script>
(function () {
	function initStaffRegionNationalityChain() {
		var $region = $('#staff_filter_region_id');
		var $nat = $('#staff_filter_nationality_id');
		if (!$region.length || !$nat.length) {
			return;
		}
		if (!window.__staffFilterNationalityMaster) {
			window.__staffFilterNationalityMaster = [];
			$nat.find('option').each(function () {
				var $o = $(this);
				var v = $o.val();
				if (v === '' || v === null) {
					return;
				}
				window.__staffFilterNationalityMaster.push({
					id: String(v),
					text: $o.text(),
					regionId: String($o.attr('data-region-id') != null ? $o.attr('data-region-id') : '')
				});
			});
		}
		var master = window.__staffFilterNationalityMaster;

		function rebuildNationalityOptions() {
			var rid = String($region.val() != null ? $region.val() : '');
			var cur = String($nat.val() != null ? $nat.val() : '');
			$nat.empty();
			$nat.append($('<option></option>').attr('value', '').text('Select Nationality'));
			for (var i = 0; i < master.length; i++) {
				var row = master[i];
				if (rid === '' || row.regionId === rid) {
					$nat.append(
						$('<option></option>')
							.attr('value', row.id)
							.attr('data-region-id', row.regionId)
							.text(row.text)
					);
				}
			}
			var hasCur = false;
			$nat.find('option').each(function () {
				if ($(this).val() === cur) {
					hasCur = true;
				}
			});
			$nat.val(hasCur ? cur : '');
		}

		function refreshNationalitySelect2() {
			if ($nat.hasClass('select2-hidden-accessible')) {
				try {
					$nat.select2('destroy');
				} catch (e) { /* ignore */ }
			}
			$nat.select2({
				theme: 'bootstrap4',
				width: $nat.hasClass('w-100') ? '100%' : 'style',
				placeholder: $nat.data('placeholder'),
				allowClear: Boolean($nat.data('allow-clear'))
			});
		}

		function onRegionChanged() {
			rebuildNationalityOptions();
			refreshNationalitySelect2();
		}

		var chainTimer = null;
		function scheduleChain() {
			clearTimeout(chainTimer);
			chainTimer = setTimeout(onRegionChanged, 0);
		}
		$region.off('.staffRegionNatChain').on('change.staffRegionNatChain select2:select.staffRegionNatChain', scheduleChain);
		onRegionChanged();
	}

	$(function () {
		setTimeout(initStaffRegionNationalityChain, 0);
	});
})();
</script>
