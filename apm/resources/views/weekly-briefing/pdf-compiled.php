<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 11pt; color: #0f172a; margin: 24px; line-height: 1.45; }
    h1 { text-align: center; font-size: 16pt; color: #0d5c2f; margin: 0 0 6px 0; }
    .meta { text-align: center; font-size: 10pt; color: #64748b; margin-bottom: 20px; }
    .page-break { page-break-before: always; }
    table.happenings { width: 100%; border-collapse: collapse; margin: 8px 0 12px 0; }
    table.happenings th, table.happenings td { border: 1px solid #94a3b8; padding: 6px; vertical-align: top; font-size: 10pt; }
    table.happenings th { background: #ffffff; font-weight: bold; }
    table.happenings td { background: #fdf2f8; }
    table.happenings td.major { font-weight: bold; width: 22%; }
</style>
</head>
<body>
<?php
/** @var \Illuminate\Support\Collection<int,\App\Models\WeeklyBriefingReport> $reports */
/** @var int $isoYear */
/** @var int $isoWeek */
/** @var string|null $compiledPdfHeading Optional; default is organisation-wide compiled title. */
/** @var string|null $compiledPdfMetaHtml Optional inner HTML for the meta line (trusted server-side only). */
$compiledPdfHeading = $compiledPdfHeading ?? null;
$compiledPdfMetaHtml = $compiledPdfMetaHtml ?? null;
?>
<h1><?php echo htmlspecialchars($compiledPdfHeading ?? 'Weekly Briefing — Compiled', ENT_QUOTES, 'UTF-8'); ?></h1>
<div class="meta"><?php
if (is_string($compiledPdfMetaHtml) && $compiledPdfMetaHtml !== '') {
    echo $compiledPdfMetaHtml;
} else {
    echo 'ISO week <strong>W'.(int) $isoWeek.' / '.(int) $isoYear.'</strong> · '.(int) count($reports).' reporting unit(s) · Grouped by directorate / office';
}
?></div>

<?php $first = true; ?>
<?php foreach ($reports as $report) {
    if (!$first) {
        echo '<div class="page-break"></div>';
    }
    $first = false;
    $dirName = $report->directorate?->name ?? 'Directorate / Office';
    $unitLabel = $report->contributionEntityLabel();
    ?>
    <h2 style="font-size:13pt;color:#0d5c2f;border-bottom:1px solid #cbd5e1;"><?php echo htmlspecialchars($unitLabel); ?> <span style="font-weight:normal;color:#64748b;">(<?php echo htmlspecialchars($dirName); ?>)</span></h2>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($report->status); ?>
    <?php
    if ($report->submitted_by_staff_id && $report->submittedBy) {
        $sn = trim((string) (($report->submittedBy->fname ?? '').' '.($report->submittedBy->lname ?? '')));
        if ($sn === '') {
            $sn = 'Staff #'.(int) $report->submitted_by_staff_id;
        }
        echo ' · <span style="font-size:10pt;color:#475569;">Submitted by: <strong>'.htmlspecialchars($sn, ENT_QUOTES, 'UTF-8').'</strong>';
        if ($report->submitted_at) {
            echo ' ('.htmlspecialchars($report->submitted_at->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8').')';
        }
        echo '</span>';
    }
    ?></p>
    <?php if ($report->requiresDirectorReview()) { ?>
    <p style="font-size:10pt;color:#334155;margin-top:6px;"><strong>Director review (divisions table):</strong> <?php echo htmlspecialchars($report->directorReviewSummaryLine(), ENT_QUOTES, 'UTF-8'); ?>
        <?php
        $trailSum = $report->directorReviewTrailSummary();
        if ($trailSum !== '—') {
            echo '<br><span style="color:#64748b;">Trail:</span> '.htmlspecialchars($trailSum, ENT_QUOTES, 'UTF-8');
        }
        ?></p>
    <?php } ?>

    <h3 style="font-size:11pt;">Section 1 — Major happenings</h3>
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
            echo '<td>'.\App\Helpers\PrintHelper::sanitizeRichTextForMpdf($row['description_key_actions'] ?? '').'</td>';
            echo '<td>'.\App\Helpers\PrintHelper::sanitizeRichTextForMpdf($row['strategic_relevance'] ?? '').'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    ?>
    <h3 style="font-size:11pt;">Section 2 — Bottlenecks</h3>
    <table style="width:100%;border-collapse:collapse;" cellpadding="4">
        <tr style="background:#f1f5f9;"><th>Issue</th><th>Impact</th><th>Required action</th></tr>
        <?php
        foreach ($report->section2_bottlenecks ?? [] as $b) {
            if (trim((string)($b['issue'] ?? '')) === '' && trim((string)($b['impact_risk'] ?? '')) === '' && trim((string)($b['required_action'] ?? '')) === '') {
                continue;
            }
            echo '<tr>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.nl2br(htmlspecialchars((string)($b['issue'] ?? ''), ENT_QUOTES, 'UTF-8')).'</td>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.nl2br(htmlspecialchars((string)($b['impact_risk'] ?? ''), ENT_QUOTES, 'UTF-8')).'</td>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.nl2br(htmlspecialchars((string)($b['required_action'] ?? ''), ENT_QUOTES, 'UTF-8')).'</td>';
            echo '</tr>';
        }
        ?>
    </table>
<?php } ?>
</body>
</html>
