<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>Opening <?= htmlspecialchars($label ?? 'module', ENT_QUOTES, 'UTF-8') ?>…</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f4f6f8; color: #2c3e50; }
    .box { text-align: center; padding: 2rem; }
  </style>
</head>
<body>
  <div class="box">
    <p>Opening <strong><?= htmlspecialchars($label ?? 'application', ENT_QUOTES, 'UTF-8') ?></strong>…</p>
    <p class="text-muted" style="font-size:0.9rem;color:#6c757d;">Please wait.</p>
  </div>
  <form id="cbp-sso-form" method="post" action="<?= htmlspecialchars($accept_url, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="staff_sso_jwt" value="<?= htmlspecialchars($sso_jwt ?? '', ENT_QUOTES, 'UTF-8') ?>">
  </form>
  <script>
    document.getElementById('cbp-sso-form').submit();
  </script>
</body>
</html>
