<?php
/**
 * Standalone smoke test for Signature Manager DB queries (no CI bootstrap).
 * Usage: php scripts/test_signature_manager.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->load();

$host = trim((string) ($_ENV['DB_HOST'] ?? '127.0.0.1'));
$user = (string) ($_ENV['DB_USER'] ?? 'root');
$pass = (string) ($_ENV['DB_PASS'] ?? '');
$staffDb = (string) ($_ENV['DB_NAME'] ?? 'staff');
$apmDb = (string) ($_ENV['APM_DB_NAME'] ?? 'bms_new');

$staff = new mysqli($host, $user, $pass, $staffDb);
$apm = new mysqli($host, $user, $pass, $apmDb);
if ($staff->connect_error || $apm->connect_error) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

function activeStaffIds(mysqli $staff): array
{
    $sql = '
        SELECT sc.staff_id
        FROM staff_contracts sc
        INNER JOIN (
            SELECT staff_id, MAX(staff_contract_id) AS latest_contract_id
            FROM staff_contracts
            GROUP BY staff_id
        ) latest ON sc.staff_id = latest.staff_id
            AND sc.staff_contract_id = latest.latest_contract_id
        WHERE sc.status_id IN (1, 2, 7)
    ';
    $ids = [];
    $r = $staff->query($sql);
    while ($row = $r->fetch_assoc()) {
        $ids[(int) $row['staff_id']] = true;
    }
    return $ids;
}

function approverStaffIds(mysqli $staff, mysqli $apm): array
{
    $active = activeStaffIds($staff);
    $wf = $apm->query('SELECT id FROM workflows WHERE is_active=1 LIMIT 1')->fetch_assoc();
    if (!$wf) {
        return [];
    }
    $wid = (int) $wf['id'];
    $ids = [];
    $r = $apm->query("
        SELECT DISTINCT a.staff_id
        FROM approvers a
        JOIN workflow_definition wd ON wd.id = a.workflow_dfn_id
        WHERE wd.workflow_id = {$wid} AND wd.is_enabled = 1 AND wd.is_division_specific = 0
    ");
    while ($row = $r->fetch_assoc()) {
        $id = (int) $row['staff_id'];
        if (isset($active[$id])) {
            $ids[$id] = true;
        }
    }
    $allowed = ['division_head', 'focal_person', 'admin_assistant', 'finance_officer', 'director_id', 'head_oic_id', 'director_oic_id'];
    $roles = $apm->query("SELECT division_reference_column FROM workflow_definition WHERE workflow_id = {$wid} AND is_enabled = 1 AND is_division_specific = 1");
    while ($role = $roles->fetch_assoc()) {
        $col = $role['division_reference_column'];
        if (!in_array($col, $allowed, true)) {
            continue;
        }
        $r = $apm->query("SELECT DISTINCT d.{$col} AS staff_id FROM divisions d WHERE d.{$col} IS NOT NULL AND d.{$col} > 0");
        while ($row = $r->fetch_assoc()) {
            $id = (int) $row['staff_id'];
            if (isset($active[$id])) {
                $ids[$id] = true;
            }
        }
    }
    return array_keys($ids);
}

function countStaffInScope(mysqli $staff, string $scope, array $approverIds): int
{
    if ($scope === 'approvers') {
        if ($approverIds === []) {
            return 0;
        }
        $in = implode(',', array_map('intval', $approverIds));
        $r = $staff->query("SELECT COUNT(*) c FROM staff WHERE staff_id IN ({$in})");
        return (int) $r->fetch_assoc()['c'];
    }
    $sql = '
        SELECT COUNT(DISTINCT s.staff_id) c
        FROM staff s
        INNER JOIN staff_contracts sc ON sc.staff_id = s.staff_id
        INNER JOIN (
            SELECT staff_id, MAX(staff_contract_id) lid FROM staff_contracts GROUP BY staff_id
        ) x ON sc.staff_id = x.staff_id AND sc.staff_contract_id = x.lid
        WHERE sc.status_id IN (1, 2, 7)
    ';
    return (int) $staff->query($sql)->fetch_assoc()['c'];
}

$approvers = approverStaffIds($staff, $apm);
$currentCount = countStaffInScope($staff, 'current', $approvers);
$approverCount = countStaffInScope($staff, 'approvers', $approvers);

echo "Active staff (current scope): {$currentCount}\n";
echo "APM approvers in scope: {$approverCount}\n";
echo 'Staff 558 in approvers: ' . (in_array(558, $approvers, true) ? 'yes' : 'no') . "\n";

$sig = $staff->query('SELECT signature FROM staff WHERE staff_id = 558')->fetch_assoc();
$path = $root . '/uploads/staff/signature/' . ($sig['signature'] ?? '');
echo 'Staff 558 signature file exists: ' . (is_file($path) ? 'yes' : 'no') . "\n";
echo "OK\n";
