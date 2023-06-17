<div class="card">
  <div class="col-md-12" style="float: right;">
    <button type="button" class="btn   btn-dark btn-sm btn-bordered" data-bs-toggle="modal" data-bs-target="#add_item">+ Add New Staff</button>
  </div>
  <div class="card-body">

    <div class="table-responsive">
      <table class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>SAPNO</th>
            <th>Title</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Nationality</th>
            <th>Job</th>
            <th>Acting Job</th>
            <th>Division</th>
            <th>Duty Station</th>
            <th>Email</th>
            <th>Telephone</th>
            <th>WhatsApp</th>
            <th>Funder</th>
            <th>Contracting Organisation</th>
            <th>Grade</th>
            <th>Contract Type</th>
            <th>Contract Status</th>
            <th>Contract Start Date</th>
            <th>Contract End Date</th>
            <th>Contract Comments</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php
          //dd($staff->toArray());
          $i = 1;
          foreach ($staff as $data) : ?>

            <tr>
              <td><?= $i++ ?></td>
              <td><?= $data->SAPNO ?></td>
              <td><?= $data->title ?></td>
              <td><a href="<?php echo base_url() ?>staff/profile/<?= $data->SAPNO ?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
              <td><?= $data->gender ?></td>
              <td><?= $data->nationality->nationality ?></td>
              <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
              <td><?= @character_limiter($data->contracts[0]->jobacting_name, 15) ?></td>
              <td><?= @$data->contracts[0]->division_name ?></td>
              <td><?= @$data->contracts[0]->station_name ?></td>
              <td><?= @$data->work_email ?></td>
              <td><?= @$data->tel_1 . ' / ' . $data->tel_2 ?></td>
              <td><?= @$data->whatsapp ?></td>
              <td><?= @$data->contracts[0]->funder_name ?></td>
              <td><?= @$data->contracts[0]->contractor_name ?></td>
              <td><?= @$data->contracts[0]->grade_name ?></td>
              <td><?= @$data->contracts[0]->contract_type_name ?></td>
              <td><?= @$data->contracts[0]->status ?></td>
              <td><?= @$data->contracts[0]->start_date ?></td>
              <td><?= @$data->contracts[0]->end_date ?></td>
              <td><?= @$data->contracts[0]->comments ?></td>


              <td><a href="<?= base_url() ?>staff/update">Edit</a> | <a href="<?= base_url() ?>staff/profile">Profie</a></td>


            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>