<?php
/**
 * CLI security checks for CBP SSO one-time code flow.
 * Run: php scripts/test_sso_launch_security.php
 */
define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('ENVIRONMENT', 'testing');

require APPPATH . 'helpers/auth_security_helper.php';
require APPPATH . 'helpers/sso_launch_helper.php';

$_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'test-secret-for-cli-sso-tests-only';

$pass = 0;
$fail = 0;

function assert_test(string $name, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "[PASS] {$name}\n";
    } else {
        $fail++;
        echo "[FAIL] {$name}\n";
    }
}

$jwt = staff_sso_build_jwt(['staff_id' => 1, 'user_id' => 99, 'permissions' => ['85']]);
$code = staff_sso_create_code($jwt, 'approvals_management', 99, 120);
$r = staff_sso_consume_code($code, 'approvals_management');
assert_test('Valid code redeems for matching module', $r !== null && $r['jwt'] === $jwt);
assert_test('Replay blocked', staff_sso_consume_code($code, 'approvals_management') === null);
assert_test('Invalid format rejected', staff_sso_consume_code('bad') === null);

$code2 = staff_sso_create_code($jwt, 'approvals_management', 99, 120);
assert_test('Wrong module rejected', staff_sso_consume_code($code2, 'finance_management') === null);
$r2 = staff_sso_consume_code($code2, 'approvals_management');
assert_test('Code still valid after wrong-module attempt', $r2 !== null);

$codeExp = bin2hex(random_bytes(32));
$path = staff_sso_cache_dir() . '/' . hash('sha256', $codeExp) . '.json';
file_put_contents($path, json_encode([
    'jwt' => $jwt,
    'module_key' => 'approvals_management',
    'user_id' => 99,
    'exp' => time() - 10,
]));
assert_test('Expired code rejected', staff_sso_consume_code($codeExp, 'approvals_management') === null);

staff_sso_prune_expired_codes();
assert_test('Prune removes expired files', !is_file($path));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
