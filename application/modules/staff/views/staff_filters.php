<div class="card shadow-sm p-3 mb-4 border rounded" style="background-color: #f9f9f9;">
    <div class="row g-3 align-items-end">

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
            <label class="form-label fw-bold">Nationality</label>
            <select class="form-control select2" name="nationality_id">
                <option value="">Select Nationality</option>
                <?php 
				$nationalities = $this->db->get('nationalities')->result();

				foreach ($nationalities as $n): ?>
                    <option value="<?= $n->nationality_id ?>" <?= ($this->input->get('nationality_id') == $n->nationality_id) ? 'selected' : '' ?>>
                        <?= $n->nationality ?>
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
            'nationality_id' => 'Nationality',
            'division_id' => 'Division',
            'duty_station_id' => 'Duty Station',
            'funder_id' => 'Funder',
        ];

        // Create lookup maps
        $division_map = array_column($divisions, 'division_name', 'division_id');
        $station_map  = array_column($duty_stations, 'duty_station_name', 'duty_station_id');
        $funder_map   = array_column($funders, 'funder', 'funder_id');

        foreach ($this->input->get() as $key => $value) {
            if (!empty($value)) {
                // Convert label
                $label = $label_map[$key] ?? ucwords(str_replace('_', ' ', $key));

                // Handle mappings
                if ($key === 'nationality_id') {
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
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                }

                echo "<span class='badge bg-secondary me-1'>$label: $value</span>";
            }
        }
        ?>
    </div>
<?php endif; ?>
<div class="row mt-0 mb-2 p-1">
    <div class="col-md-12 d-flex justify-content-between align-items-center">

        <!-- Pagination and Total Records (left side) -->
        <div class="d-flex align-items-center flex-wrap gap-2 p-1">
            <div class="pagination-links">
                <?= $links ?>
            </div>
            <div class="record-count text-muted small">
                <?= $records . " Records" ?>
            </div>
        </div>

        <!-- Export buttons (right side) -->
        <div class="export-buttons d-flex gap-2 p-1">
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
            } elseif ($segment2 == '') {
                // Index route fallback
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

