<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff PPA Report</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 11px; }
    h2 { text-align: center; color: #911C39; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
    th { background-color: #f2f2f2; color: #333; }
    .section-title { margin-top: 15px; font-weight: bold; font-size: 13px; color: #119A48; }
  </style>
</head>
<body>

  <h2>All Staff PPA Entries</h2>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Division</th>
        <th>Submission Date</th>
        <th>Period</th>
        <th>Status</th>
        <th>Objectives</th>
        <th>Training</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($plans as $plan): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $plan['staff_name'] ?></td>
          <td><?= $plan['division_name'] ?? 'N/A' ?></td>
          <td><?= date('d M Y', strtotime($plan['created_at'])) ?></td>
          <td><?= str_replace('-', ' ', $plan['performance_period']) ?></td>
          <td><?= $plan['overall_status'] ?></td>
          <td>
            <?php
              $objectives = json_decode($plan['objectives'], true);
              if (is_array($objectives)) {
                foreach ($objectives as $obj) {
                  echo "<strong>Objective:</strong> {$obj['objective']}<br>";
                  echo "<strong>Indicator:</strong> {$obj['indicator']}<br>";
                  echo "<strong>Timeline:</strong> {$obj['timeline']}<br>";
                  echo "<strong>Weight:</strong> {$obj['weight']}%<br><hr>";
                }
              } else {
                echo '<i>No objectives</i>';
              }
            ?>
          </td>
          <td>
            <?php
              if ($plan['training_recommended'] === 'Yes') {
                echo '<strong>Recommended:</strong> Yes<br>';
                echo '<strong>Contribution:</strong> ' . ($plan['training_contributions'] ?: 'N/A') . '<br>';
                echo '<strong>Skills:</strong><ul>';
                $skills = json_decode($plan['required_skills'], true);
                if (!empty($skills)) {
                  $CI =& get_instance();
                  foreach ($skills as $sid) {
                    $skill_name = $CI->db->select('skill')->where('id', $sid)->get('training_skills')->row('skill');
                    echo "<li>$skill_name</li>";
                  }
                }
                echo '</ul>';
              } else {
                echo 'No training recommended';
              }
            ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

</body>
</html>
