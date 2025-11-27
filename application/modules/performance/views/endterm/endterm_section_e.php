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

<!-- New Endterm PDP Entry Fields -->
<div class="mb-3">
  <label class="form-label fw-semibold">1. Comments on progress made against employee's PDP</label>
  <textarea name="endterm_training_review" class="form-control" rows="4" <?= $endreadonly ?>><?= htmlspecialchars($ppa->endterm_training_review ?? '') ?></textarea>
</div>

