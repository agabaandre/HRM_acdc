<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Profile completion</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #1e293b; line-height: 1.5;">
  <p>Dear <?= htmlspecialchars($name ?? 'Colleague') ?>,</p>
  <p>Please complete the following items on your <strong>Staff Portal profile</strong> at your earliest convenience:</p>
  <ul>
    <?php foreach ($missing ?? [] as $item): ?>
      <li><?= htmlspecialchars($item) ?></li>
    <?php endforeach; ?>
  </ul>
  <p>
    <a href="<?= htmlspecialchars($profile_url ?? base_url('auth/profile')) ?>" style="display:inline-block;padding:10px 16px;background:#119a48;color:#fff;text-decoration:none;border-radius:6px;">
      Open my profile
    </a>
  </p>
  <p style="font-size:13px;color:#64748b;">Africa CDC Staff Portal — automated reminder. If you have already updated your profile, you may ignore this message after saving.</p>
</body>
</html>
