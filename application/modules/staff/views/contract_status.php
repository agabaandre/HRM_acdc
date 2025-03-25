<style>
@media print {
    .hidden, .no-print, .btn, .modal-footer, .toolbar {
        display: none !important;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        border: 1px solid #000 !important;
        padding: 4px !important;
    }

    body {
        margin: 0;
        padding: 0;
    }

    @page {
        margin: 0;
    }
}
</style>

<div class="card">
    <div class="card-header d-flex justify-content-end">
        <a href="<?= base_url('staff/new') ?>" class="btn btn-dark btn-sm">+ Add New Staff</a>
    </div>

    <div class="card-body">
        <?php 
        $status = $this->uri->segment(3);
        echo form_open_multipart(base_url("staff/contract_status/$status"), ['id' => 'staff_form', 'class' => 'staff']);
        ?>

        <div class="row">
            <div class="col-md-2">
                <label>Name</label>
                <input type="text" name="lname" class="form-control" value="<?= set_value('lname') ?>">
            </div>

            <div class="col-md-2">
                <label>Gender</label>
                <select class="form-control select2" name="gender">
                    <option value="">Select Gender</option>
                    <option value="Male" <?= set_select('gender', 'Male') ?>>Male</option>
                    <option value="Female" <?= set_select('gender', 'Female') ?>>Female</option>
                </select>
            </div>

            <div class="col-md-2">
                <label>SAP NO</label>
                <input type="text" name="SAPNO" class="form-control" value="<?= set_value('SAPNO') ?>">
            </div>

            <div class="col-md-2">
                <label>Nationality</label>
                <select class="form-control select2" name="nationality_id">
                    <option value="">Select Nationality</option>
                    <?php foreach ($this->db->get('nationalities')->result() as $nat): ?>
                        <option value="<?= $nat->nationality_id ?>" <?= set_select('nationality_id', $nat->nationality_id) ?>>
                            <?= $nat->nationality ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label>Division(s)</label>
                <select name="division_ids[]" class="form-control select2" multiple>
                    <?php foreach ($divisions as $division): ?>
                        <option value="<?= $division->id ?>" <?= set_select('division_ids[]', $division->id) ?>>
                            <?= $division->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label>Duty Station(s)</label>
                <select name="station_ids[]" class="form-control select2" multiple>
                    <?php foreach ($duty_stations as $station): ?>
                        <option value="<?= $station->id ?>" <?= set_select('station_ids[]', $station->id) ?>>
                            <?= $station->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12 mt-3 d-flex">
                <button type="submit" class="btn btn-sm btn-success me-2">
                    <i class="fa fa-filter"></i> Apply Filters
                </button>
                <a href="<?= base_url("staff/contract_status/$status/1") ?>" class="btn btn-sm btn-secondary me-2">
                    <i class="fa fa-file-csv"></i> Export
                </a>
                <a href="<?= base_url("staff/contract_status/$status/0/1") ?>" class="btn btn-sm btn-secondary">
                    <i class="fa fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <?= form_close(); ?>

        <hr>

        <?php if (!empty($this->input->post())): ?>
            <p>Result Limited By:
                <?php 
                foreach ($this->input->post() as $key => $value):
                    if (!empty($value)) {
                        if (is_array($value)) {
                            echo ucwords(str_replace('_', ' ', $key)) . ': ' . implode(', ', $value) . '; ';
                        } else {
                            if ($key == 'nationality_id') {
                                $value = getcountry($value);
                                $key = 'Nationality';
                            }
                            echo ucwords(str_replace('_', ' ', $key)) . ': ' . $value . '; ';
                        }
                    }
                endforeach;
                ?>
            </p>
        <?php endif; ?>

        <p><strong><?= $records ?> Total Staff</strong></p>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SAPNO</th>
                        <th>Title</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Nationality</th>
                        <th>Duty Station</th>
                        <th>Division</th>
                        <th>Job</th>
                        <th>Status</th>
                        <th>Acting Job</th>
                        <th>1st Supervisor</th>
                        <th>2nd Supervisor</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>WhatsApp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = ($this->uri->segment(4) != "") ? $this->uri->segment(4) : 1;
                    foreach ($staffs as $data): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $data->SAPNO ?></td>
                            <td><?= $data->title ?></td>
                            <td><?= generate_user_avatar($data->lname, $data->fname, base_url("uploads/staff/{$data->photo}"), $data->photo) ?></td>
                            <td><a href="#" data-bs-toggle="modal" data-bs-target="#add_profile<?= $data->staff_id ?>">
                                <?= $data->lname . ' ' . $data->fname . ' ' . $data->oname ?>
                            </a></td>
                            <td><?= $data->gender ?></td>
                            <td><?= $data->nationality ?></td>
                            <td><?= $data->duty_station_name ?></td>
                            <td><?= $data->division_name ?></td>
                            <td><?= character_limiter($data->job_name, 30) ?></td>
                            <td><?= $data->status ?></td>
                            <td><?= character_limiter($data->job_acting, 30) ?></td>
                            <td><?= staff_name($data->first_supervisor) ?></td>
                            <td><?= staff_name($data->second_supervisor) ?></td>
                            <td><?= $data->work_email ?></td>
                            <td><?= $data->tel_1 . ' ' . $data->tel_2 ?></td>
                            <td><?= $data->whatsapp ?></td>
                        </tr>
                        <!-- Modal(s) can be included here as needed -->
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?= $links ?>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Passport Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid rounded" style="width:150px;">
            </div>
        </div>
    </div>
</div>

<!-- JS to handle modal image view -->
<script>
function openImageModal(imageSrc) {
    document.getElementById("modalImage").src = imageSrc;
    var myModal = new bootstrap.Modal(document.getElementById("imageModal"), {});
    myModal.show();
}

</script>
