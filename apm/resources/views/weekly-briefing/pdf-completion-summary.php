<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 10pt; color: #0f172a; margin: 20px; line-height: 1.35; }
    h1 { text-align: center; font-size: 14pt; color: #0d5c2f; margin: 0 0 4px 0; }
    .meta { text-align: center; font-size: 9pt; color: #64748b; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #94a3b8; padding: 5px 6px; vertical-align: top; text-align: left; }
    th { background: #f1f5f9; font-size: 9pt; }
    .st-submitted { color: #15803d; font-weight: bold; }
    .st-draft { color: #b45309; font-weight: bold; }
    .st-locked { color: #475569; font-weight: bold; }
    .st-missing { color: #b91c1c; font-weight: bold; }
    .note { font-size: 8.5pt; color: #64748b; margin-top: 10px; }
</style>
</head>
<body>
<?php
/** @var list<array{key: string, label: string, directorate_name: string, status: string, contacts: string, major_happenings: string}> $rows */
/** @var int $isoYear */
/** @var int $isoWeek */
$submitted = 0;
$total = count($rows);
foreach ($rows as $r) {
    if (($r['status'] ?? '') === 'submitted') {
        $submitted++;
    }
}
?>
<h1>Weekly briefing — completion summary</h1>
<div class="meta">ISO week <strong>W<?php echo (int) $isoWeek; ?> / <?php echo (int) $isoYear; ?></strong>
    · Expected reporting units (from settings): <strong><?php echo (int) $total; ?></strong>
    · Submitted: <strong><?php echo (int) $submitted; ?></strong>
    · Outstanding: <strong><?php echo max(0, $total - $submitted); ?></strong>
</div>

<?php if ($total === 0) { ?>
    <p><em>No contributor rows are configured in weekly briefing settings. Add staff under “Allowed heads / contributors” to populate this summary.</em></p>
<?php } else { ?>
<table>
    <tr>
        <th style="width:18%">Directorate / office</th>
        <th style="width:20%">Reporting unit</th>
        <th style="width:26%">Major happenings (titles)</th>
        <th style="width:12%">Status</th>
        <th style="width:24%">Contributor staff</th>
    </tr>
    <?php foreach ($rows as $row) {
        $st = (string) ($row['status'] ?? '');
        $cls = 'st-missing';
        if ($st === 'submitted') {
            $cls = 'st-submitted';
        } elseif ($st === 'draft') {
            $cls = 'st-draft';
        } elseif ($st === 'locked') {
            $cls = 'st-locked';
        }
        ?>
    <tr>
        <td><?php echo htmlspecialchars((string) ($row['directorate_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string) ($row['major_happenings'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($st !== '' ? $st : 'missing', ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string) ($row['contacts'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php } ?>
</table>
<p class="note">Statuses reflect reports in APM for this ISO week. “missing” means no report row exists yet. “draft” / “locked” / “submitted” follow the weekly briefing record.</p>
<?php } ?>
</body>
</html>
