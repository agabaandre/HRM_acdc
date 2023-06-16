<div class="card">
  <div class="card-body">

    <div class="table-responsive">
      <table id="example2" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>SAPNO</th>
            <th>Title</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Nationality</th>
            <th>Job</th>
            <th>Division</th>
            <th>Duty Station</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          //dd($staff->toArray());
          foreach ($staff as $data) : ?>
            <tr>

              <td>#</td>
              <td><?= $data->SAPNO ?></td>
              <td><?= $data->title ?></td>
              <td><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
              <td><?= $data->gender ?></td>
              <td><?= $data->nationality->nationality ?></td>
              <td><?= $data->contracts[0]->job_name ?></td>
              <td><?= $data->contracts[0]->division_name ?></td>
              <td><?= $data->contracts[0]->station_name ?></td>
              <td>Actions</td>


            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>