<?php
$objectives = [];
// dd($ppa->entry_id);
@$ppaIsapproved = $this->per_mdl->isapproved($ppa->entry_id);
//check if midterm exists

$midterm_exists = $this->per_mdl->ismidterm_available($ppa->entry_id);

//dd($midterm_exists);
if ($midterm_exists) {
  //dd($ppa->midterm_objectives);
  if (is_string($ppa->midterm_objectives)) {
    $objectives = json_decode($ppa->midterm_objectives, true);

  } elseif (is_object($ppa->midterm_objectives)) {
    // Already decoded as stdClass, convert to array
    $objectives = json_decode(json_encode($ppa->midterm_objectives), true);

  } else {
    // Fallback for other types (e.g., already array or null)
    $objectives = [];
 }
  
   
} else{
 
    if (is_string($ppa->objectives)) {
        $objectives = json_decode($ppa->objectives, true);

    } elseif (is_object($ppa->objectives)) {
        // Already decoded as stdClass, convert to array
        $objectives = json_decode(json_encode($ppa->objectives), true);

    } else {
        // Fallback for other types (e.g., already array or null)
        $objectives = [];
    }

}

?>

<h4 class="mt-4">B. Review of Performance Objectives</h4>
<p class="text-muted">Fill out the objectives, staff self-appraisal, and appraiser ratings. All objectives must total 100% weight.</p>

<div class="table-responsive">
  <table class="table table-bordered align-middle text-sm">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Objective</th>
        <th>Timeline</th>
        <th>Deliverables & KPIs</th>
        <th>Weight (%)</th>
        <th>Staff Self Appraisal</th>
        <th>Appraiser’s Rating</th>
      </tr>
    </thead>
    <tbody id="objectives-table-body">
      <?php 
      $rowNum = 1;
      for ($i = 0; $i < 10; $i++): 
        $val = $objectives[$i] ?? [
          'objective' => '', 'timeline' => '', 'indicator' => '', 'weight' => '', 
          'self_appraisal' => '', 'appraiser_rating' => ''
        ];

        if (trim($val['objective']) === '') continue;
      ?>
      <tr>
        <td><?= $rowNum++ ?></td>

        <td>
          <textarea name="objectives[<?= $i ?>][objective]" rows=5 class="form-control" readonly <?= $midreadonly ?>><?= $val['objective'] ?></textarea>
        </td>

        <td>
          <input type="text" name="objectives[<?= $i ?>][timeline]" rows=5 class="form-control" value="<?= $val['timeline'] ?>" readonly <?= $midreadonly ?>>
        </td>

        <td>
          <textarea name="objectives[<?= $i ?>][indicator]" rows=5 class="form-control" readonly <?= $midreadonly ?>><?= $val['indicator'] ?></textarea>
        </td>

        <td>
          <input type="number" name="objectives[<?= $i ?>][weight]" class="form-control" value="<?= $val['weight'] ?>" readonly <?= $midreadonly ?>>
        </td>

        <td>
          <textarea name="objectives[<?= $i ?>][self_appraisal]" rows=5 class="form-control" <?= $midreadonly ?>><?= $val['self_appraisal'] ?? '' ?></textarea>
        </td>

        <td>
          <select name="objectives[<?= $i ?>][appraiser_rating]" class="form-select" <?= $midreadonly ?>  <?php if($isSupervisor){echo 'required';}?>>
            <option value="">-- Select --</option>
            <?php
              $ratings = [
                5 => '5 Exceptional',
                4 => '4 Exceeds Expectations',
                3 => '3 Meets Expectations',
                2 => '2 Needs Improvement',
                1 => '1 Unsatisfactory'
              ];
              foreach ($ratings as $key => $label):
            ?>
              <option value="<?= $key ?>" <?= (@$val['appraiser_rating'] == $key) ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>
