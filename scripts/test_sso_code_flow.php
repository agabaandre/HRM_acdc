<?php
define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('ENVIRONMENT', 'development');

require APPPATH . 'helpers/sso_launch_helper.php';

$modules = ['approvals_management', 'finance_management', 'helpdesk_itsm'];
$jwt = staff_sso_build_jwt(['staff_id' => 1, 'user_id' => 99, 'permissions' => ['85']]);

echo 'cache_dir=' . staff_sso_cache_dir() . PHP_EOL;
echo 'dir_readable=' . (is_readable(staff_sso_cache_dir()) ? 'yes' : 'no') . PHP_EOL;
echo 'dir_perms=' . substr(sprintf('%o', fileperms(staff_sso_cache_dir())), -4) . PHP_EOL;

foreach ($modules as $mod) {
    $code = staff_sso_create_code($jwt, $mod, 99, 120);
    $path = staff_sso_cache_dir() . '/' . hash('sha256', $code) . '.json';
    echo "{$mod}: file=" . (is_file($path) ? 'yes' : 'no');
    if (is_file($path)) {
        echo ' perms=' . substr(sprintf('%o', fileperms($path)), -4);
        echo ' readable=' . (is_readable($path) ? 'yes' : 'no');
    }
    $r = staff_sso_consume_code($code, $mod);
    echo ' consume=' . ($r ? 'ok' : 'fail') . PHP_EOL;
}
