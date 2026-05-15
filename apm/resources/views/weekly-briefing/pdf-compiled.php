<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 11pt; color: #0f172a; margin: 24px; line-height: 1.45; }
    h1 { text-align: center; font-size: 16pt; color: #0d5c2f; margin: 0 0 6px 0; }
    .meta { text-align: center; font-size: 10pt; color: #64748b; margin-bottom: 20px; }
    /* Cover on its own page; each division brief starts on a new page (mPDF). */
    .compiled-pdf-cover { page-break-after: always; }
    .compiled-division-page { page-break-before: always; }
    .compiled-division-page.first { page-break-before: auto; }
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
<div class="compiled-pdf-cover">
<h1><?php echo htmlspecialchars($compiledPdfHeading ?? 'Weekly brief — compiled', ENT_QUOTES, 'UTF-8'); ?></h1>
<div class="meta"><?php
if (is_string($compiledPdfMetaHtml) && $compiledPdfMetaHtml !== '') {
    echo $compiledPdfMetaHtml;
} else {
    $rangeLine = \App\Models\WeeklyBriefingReport::humanIsoWeekRange((int) $isoYear, (int) $isoWeek, true);
    echo htmlspecialchars($rangeLine, ENT_QUOTES, 'UTF-8').' · <strong>'.(int) count($reports).'</strong> reporting unit(s)';
}
?></div>
</div>

<?php foreach ($reports as $idx => $report) {
    $isFirstDivision = ((int) $idx) === 0;
    $dirName = $report->directorate?->name ?? 'Directorate / Office';
    $unitLabel = $report->contributionEntityLabel();
    ?>
<div class="compiled-division-page<?php echo $isFirstDivision ? ' first' : ''; ?>">
    <h2 style="font-size:13pt;color:#0d5c2f;border-bottom:1px solid #cbd5e1;"><?php echo htmlspecialchars($unitLabel); ?> <span style="font-weight:normal;color:#64748b;">(<?php echo htmlspecialchars($dirName); ?>)</span></h2>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($report->status); ?>
    <?php
    if ($report->submitted_by_staff_id && $report->submittedBy) {
        $sn = trim((string) ($report->submittedBy->name ?? ''));
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
    <p style="font-size:10pt;color:#334155;margin-top:6px;"><strong>Director review:</strong> <?php echo htmlspecialchars($report->directorReviewSummaryLine(), ENT_QUOTES, 'UTF-8'); ?>
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
        $mhPlain = trim(strip_tags((string) ($row['major_happening'] ?? '')));
        $dPlain = trim(strip_tags((string) ($row['description_key_actions'] ?? '')));
        $sPlain = trim(strip_tags((string) ($row['strategic_relevance'] ?? '')));
        if ($mhPlain === '' && $dPlain === '' && $sPlain === '') {
            continue;
        }
        $bodyRows[] = $row;
    }
    if (count($bodyRows) > 0) {
        echo '<table class="happenings"><thead><tr>';
        echo '<th>Major Happening</th><th>Description and Key Actions</th><th>Strategic Relevance to Africa CDC</th>';
        echo '</tr></thead><tbody>';
        foreach ($bodyRows as $rowIdx => $row) {
            $mh = trim((string) ($row['major_happening'] ?? ''));
            $num = $rowIdx + 1;
            $mhBody = trim(strip_tags($mh)) !== '' ? \App\Helpers\PrintHelper::sanitizeRichTextForMpdf($mh) : '<span style="color:#64748b;">—</span>';
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
            $iPlain = trim(strip_tags((string) ($b['issue'] ?? '')));
            $pPlain = trim(strip_tags((string) ($b['impact_risk'] ?? '')));
            $aPlain = trim(strip_tags((string) ($b['required_action'] ?? '')));
            if ($iPlain === '' && $pPlain === '' && $aPlain === '') {
                continue;
            }
            echo '<tr>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.\App\Helpers\PrintHelper::sanitizeRichTextForMpdf((string) ($b['issue'] ?? '')).'</td>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.\App\Helpers\PrintHelper::sanitizeRichTextForMpdf((string) ($b['impact_risk'] ?? '')).'</td>';
            echo '<td style="border:1px solid #ccc;vertical-align:top;">'.\App\Helpers\PrintHelper::sanitizeRichTextForMpdf((string) ($b['required_action'] ?? '')).'</td>';
            echo '</tr>';
        }
    ?>
    </table>
</div>
<?php } ?>
</body>
</html>
