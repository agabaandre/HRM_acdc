<?php
// Load all AU competencies grouped by category
$competencies = Modules::run('performance/get_competencies_by_version');
$grouped = [];

foreach ($competencies as $row) {
    $grouped[$row['category']][] = $row;
}

$categories = [
  'values' => 'AU Values',
  'core' => 'Core Competencies',
  'functional' => 'Functional Competencies',
  'leadership' => 'Leadership Competencies'
];

// Decode midterm competencies safely as array
$midterm_competency = [];
if (!empty($ppa->midterm_competency)) {
  if (is_string($ppa->midterm_competency)) {
    $midterm_competency = json_decode($ppa->midterm_competency, true);
  } elseif (is_object($ppa->midterm_competency)) {
    $midterm_competency = (array) $ppa->midterm_competency;
  } elseif (is_array($ppa->midterm_competency)) {
    $midterm_competency = $ppa->midterm_competency;
  }
}
?>

<h4 class="mt-4">D. Competencies</h4>
<p class="text-muted">
  All staff members shall be rated against AU Values and Core/Functional Competencies.
  Staff with managerial responsibilities will also be rated on Leadership Competencies.
</p>

<?php foreach ($categories as $catKey => $catLabel): ?>
  <?php if (isset($grouped[$catKey])): ?>
    <div class="mt-4">
      <h5 class="fw-bold"><?= $catLabel ?></h5>
      <table class="table table-bordered table-sm competency-table">
        <thead class="table-light text-center">
          <tr>
            <th style="width: 35%;">Competency</th>
            <th style="width: 13%;">5</th>
            <th style="width: 13%;">4</th>
            <th style="width: 13%;">3</th>
            <th style="width: 13%;">2</th>
            <th style="width: 13%;">1</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grouped[$catKey] as $item):
            $key = 'competency_' . $item['id'];
            $selected = $midterm_competency[$key] ?? null;
          ?>
          <tr>
            <td>
              <strong><?= $item['id'] . '. ' . $item['description'] ?></strong><br>
              <small class="text-muted"><?= $item['annotation'] ?></small>
            </td>
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <td class="text-center">
                <div class="form-check d-flex flex-column align-items-center">
                  <input type="radio" class="form-check-input"
                         name="midterm_competency[<?= $key ?>]"
                         value="<?= $i ?>"
                         <?= $midreadonly ?>
                         <?= ((string)$selected === (string)$i) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= $item['score_' . $i] ?></label>
                </div>
              </td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endforeach; ?>
