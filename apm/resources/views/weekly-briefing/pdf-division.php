<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 11pt; color: #0f172a; margin: 24px; line-height: 1.45; }
    h1 { text-align: center; font-size: 16pt; color: #0d5c2f; margin: 0 0 6px 0; }
    h2 { font-size: 12pt; color: #0d5c2f; margin: 16px 0 8px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
    .meta { text-align: center; font-size: 10pt; color: #64748b; margin-bottom: 16px; }
    .tag { display: inline-block; background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 9pt; margin: 2px; }
    table.happenings { width: 100%; border-collapse: collapse; margin: 8px 0 16px 0; }
    table.happenings th, table.happenings td { border: 1px solid #94a3b8; padding: 8px; vertical-align: top; }
    table.happenings th { background: #ffffff; font-size: 10pt; font-weight: bold; }
    table.happenings td { background: #fdf2f8; font-size: 10pt; }
    table.happenings td.major { font-weight: bold; width: 22%; }
    table.happenings td.rich { font-size: 10pt; }
    table.bordered { width: 100%; border-collapse: collapse; margin: 8px 0 16px 0; }
    table.bordered th, table.bordered td { border: 1px solid #94a3b8; padding: 6px; vertical-align: top; }
    table.bordered th { background: #f1f5f9; font-size: 10pt; }
    .rich { font-size: 10pt; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #64748b; border-top: 1px solid #e2e8f0; padding: 6px; }
</style>
</head>
<body>
<?php
/** @var \App\Models\WeeklyBriefingReport $report */
/** @var \App\Models\WeeklyBriefingSetting $settings */
use App\Helpers\PrintHelper;
$dirName = $report->directorate?->name ?? 'Directorate / Office';
$unitLabel = $report->contributionEntityLabel();
$apmDiv = $report->division?->division_name ?? '';
?>
<h1>Weekly Briefing</h1>
<div class="meta">
    <strong><?php echo htmlspecialchars($dirName); ?></strong><br>
    <strong>Reporting unit:</strong> <?php echo htmlspecialchars($unitLabel); ?>
    <?php if ($apmDiv !== '' && strpos((string) ($report->contribution_key ?? ''), 'dr-') === 0) { ?>
        <br><span style="font-size:9pt;color:#64748b;">APM division (context): <?php echo htmlspecialchars($apmDiv); ?></span>
    <?php } ?>
    <br>
    ISO week <strong>W<?php echo (int) $report->report_iso_week; ?> / <?php echo (int) $report->report_iso_week_year; ?></strong>
    · Period <?php echo $report->period_start ? $report->period_start->format('Y-m-d') : ''; ?>
    · Status: <?php echo htmlspecialchars($report->status); ?>
    <?php
    if ($report->submitted_by_staff_id && $report->submittedBy) {
        $sn = trim((string) (($report->submittedBy->fname ?? '').' '.($report->submittedBy->lname ?? '')));
        if ($sn === '') {
            $sn = 'Staff #'.(int) $report->submitted_by_staff_id;
        }
        echo '<br><span style="font-size:9pt;">Submitted by: <strong>'.htmlspecialchars($sn, ENT_QUOTES, 'UTF-8').'</strong>';
        if ($report->submitted_at) {
            echo ' · '.htmlspecialchars($report->submitted_at->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8');
        }
        echo '</span>';
    }
    ?>
</div>

<h2>Section 1 — Major happenings (max 3)</h2>
<?php
$rows = $report->section1_major_happenings ?? [];
$bodyRows = [];
foreach ($rows as $row) {
    $mh = trim((string) ($row['major_happening'] ?? ''));
    $dPlain = trim(strip_tags((string) ($row['description_key_actions'] ?? '')));
    $sPlain = trim(strip_tags((string) ($row['strategic_relevance'] ?? '')));
    if ($mh === '' && $dPlain === '' && $sPlain === '') {
        continue;
    }
    $bodyRows[] = $row;
}
if (count($bodyRows) > 0) {
    echo '<table class="happenings"><thead><tr>';
    echo '<th>Major Happening</th><th>Description and Key Actions</th><th>Strategic Relevance to Africa CDC</th>';
    echo '</tr></thead><tbody>';
    foreach ($bodyRows as $idx => $row) {
        $mh = trim((string) ($row['major_happening'] ?? ''));
        $num = $idx + 1;
        $mhBody = $mh !== '' ? htmlspecialchars($mh, ENT_QUOTES, 'UTF-8') : '<span style="color:#64748b;">—</span>';
        $mhOut = '<strong>'.(int) $num.'.</strong> '.$mhBody;
        echo '<tr>';
        echo '<td class="major">'.$mhOut.'</td>';
        echo '<td class="rich">'.PrintHelper::sanitizeRichTextForMpdf($row['description_key_actions'] ?? '').'</td>';
        echo '<td class="rich">'.PrintHelper::sanitizeRichTextForMpdf($row['strategic_relevance'] ?? '').'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>

<h2>Section 2 — Key bottlenecks &amp; escalation</h2>
<table class="bordered">
    <thead>
        <tr>
            <th style="width:28%">Issue</th>
            <th style="width:22%">Impact / risk level</th>
            <th style="width:50%">Required action / SMT guidance or escalation</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($report->section2_bottlenecks ?? [] as $b) {
        if (trim((string)($b['issue'] ?? '')) === '' && trim((string)($b['impact_risk'] ?? '')) === '' && trim((string)($b['required_action'] ?? '')) === '') {
            continue;
        }
        echo '<tr>';
        echo '<td><div class="rich">'.nl2br(htmlspecialchars((string) ($b['issue'] ?? ''), ENT_QUOTES, 'UTF-8')).'</div></td>';
        echo '<td><div class="rich">'.nl2br(htmlspecialchars((string) ($b['impact_risk'] ?? ''), ENT_QUOTES, 'UTF-8')).'</div></td>';
        echo '<td><div class="rich">'.nl2br(htmlspecialchars((string) ($b['required_action'] ?? ''), ENT_QUOTES, 'UTF-8')).'</div></td>';
        echo '</tr>';
    } ?>
    </tbody>
</table>

<div class="footer">Africa CDC · Weekly briefing · Generated <?php echo date('Y-m-d H:i'); ?></div>
</body>
</html>
