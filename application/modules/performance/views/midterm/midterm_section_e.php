<h4 class="mt-4">E. Personal Development Plan – Progress Review</h4>

<!-- Read-Only Previous Training Plan Table -->
<div class="card mb-4">
  <div class="card-header bg-light">
    <strong>Original PDP Training Plan</strong>
  </div>
  <div class="card-body">
    <table class="table table-sm table-bordered">
      <tbody>
        <tr>
          <th style="width: 35%">Recommended?</th>
          <td><?= htmlspecialchars($ppa->training_recommended ?? 'No') ?></td>
        </tr>
        <tr>
          <th>Required Skills</th>
          <td>
            <?php
              $skills_map = array_column($skills, 'skill', 'id');
              $selected_skills = [];

              if (!empty($ppa->required_skills)) {
                $decoded = is_string($ppa->required_skills)
                    ? json_decode($ppa->required_skills, true)
                    : (is_array($ppa->required_skills) ? $ppa->required_skills : []);
                $selected_skills = is_array($decoded) ? $decoded : [];
              }

              if (!empty($selected_skills)) {
                echo '<ul class="mb-0">';
                foreach ($selected_skills as $id) {
                  echo '<li>' . htmlspecialchars($skills_map[$id] ?? "Unknown") . '</li>';
                }
                echo '</ul>';
              } else {
                echo '<em>None listed</em>';
              }
            ?>
          </td>
        </tr>
        <tr>
          <th>Training Contributions</th>
          <td><?= nl2br(htmlspecialchars($ppa->training_contributions ?? '')) ?></td>
        </tr>
        <tr>
          <th>Recommended AUC Courses</th>
          <td><?= nl2br(htmlspecialchars($ppa->recommended_trainings ?? '')) ?></td>
        </tr>
        <tr>
          <th>Other Courses</th>
          <td><?= nl2br(htmlspecialchars($ppa->recommended_trainings_details ?? '')) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- New Midterm PDP Entry Fields -->
<div class="mb-3">
  <label class="form-label fw-semibold">1. Comments on progress made against employee’s PDP</label>
  <textarea name="midterm_training_review" class="form-control" rows="4" <?= $midreadonly ?>>
    <?= htmlspecialchars($ppa->midterm_training_review ?? '') ?>
  </textarea>
</div>

<?php
$mid_skills = [];

if (!empty($ppa->midterm_recommended_skills)) {
  $decoded = is_string($ppa->midterm_recommended_skills)
      ? json_decode($ppa->midterm_recommended_skills, true)
      : (is_array($ppa->midterm_recommended_skills) ? $ppa->midterm_recommended_skills : []);
  $mid_skills = is_array($decoded) ? $decoded : [];
}

$isMidtermRecommended = !empty($mid_skills);
?>

<div class="mb-3">
  <label class="form-label fw-semibold">2. Is additional training recommended at midterm?</label><br>
  <div class="form-check form-check-inline">
    <input type="radio" class="form-check-input" name="midterm_training_recommended" value="Yes" id="midtermTrainingYes"
      <?= $midreadonly ?> <?= $isMidtermRecommended ? 'checked' : '' ?>
      onchange="toggleMidtermTraining(true)">
    <label for="midtermTrainingYes" class="form-check-label">Yes</label>
  </div>
  <div class="form-check form-check-inline">
    <input type="radio" class="form-check-input" name="midterm_training_recommended" value="No" id="midtermTrainingNo"
      <?= $midreadonly ?> <?= !$isMidtermRecommended ? 'checked' : '' ?>
      onchange="toggleMidtermTraining(false)">
    <label for="midtermTrainingNo" class="form-check-label">No</label>
  </div>
</div>

<div id="midterm-training-section" style="display: <?= $isMidtermRecommended ? 'block' : 'none' ?>;">
  <!-- 3. Recommended Midterm Skills -->
  <div class="mb-3">
    <label class="form-label">3. Subject/skill area(s) recommended during midterm</label>
    <select name="midterm_recommended_skills[]" class="form-control select2" multiple <?= $midreadonly ?>>
      <?php foreach ($skills as $skill): ?>
        <option value="<?= $skill->id ?>" <?= in_array($skill->id, $mid_skills) ? 'selected' : '' ?>>
          <?= htmlspecialchars($skill->skill) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- 4. Contributions -->
  <div class="mb-3">
    <label class="form-label">4. How will the recommended training(s) contribute to the staff member’s development and the department’s work?</label>
    <textarea name="midterm_training_contributions" class="form-control" rows="3" <?= $midreadonly ?>>
      <?= $ppa->midterm_training_contributions ?? '' ?>
    </textarea>
  </div>

  <!-- 5.1 AUC L&D Courses -->
  <div class="mb-3">
    <label class="form-label">5.1 With reference to the AUC L&D Catalogue, list recommended course(s)</label>
    <textarea name="midterm_recommended_trainings" class="form-control" rows="3" <?= $midreadonly ?>>
      <?= htmlspecialchars($ppa->midterm_recommended_trainings ?? '') ?>
    </textarea>
  </div>

  <!-- 5.2 External Courses -->
  <div class="mb-3">
    <label class="form-label">5.2 Highly recommendable course(s) not listed in AUC L&D Catalogue</label>
    <textarea name="midterm_recommended_trainings_details" class="form-control" rows="3" <?= $midreadonly ?>>
      <?= htmlspecialchars($ppa->midterm_recommended_trainings_details ?? '') ?>
    </textarea>
  </div>
</div>

<script>
function toggleMidtermTraining(show) {
  document.getElementById('midterm-training-section').style.display = show ? 'block' : 'none';
}
</script>
