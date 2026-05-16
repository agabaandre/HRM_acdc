<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 10.5pt; color: #0f172a; margin: 16px 18px; line-height: 1.4; }
    h1 { text-align: center; font-size: 15pt; color: #0d5c2f; margin: 0 0 4px 0; }
    .meta { text-align: center; font-size: 9.5pt; color: #64748b; margin: 0; }
    /* Page 1: compiled title only; each division report starts on its own page. */
    .compiled-pdf-cover { page-break-after: always; margin-bottom: 0; }
    .compiled-division-page { page-break-before: always; page-break-inside: auto; }
    .compiled-division-page h2 { font-size: 12pt; color: #0d5c2f; border-bottom: 1px solid #e2e8f0; margin: 0 0 6px 0; padding-bottom: 4px; page-break-after: avoid; }
    .compiled-division-meta { font-size: 9.5pt; color: #475569; margin: 0 0 8px 0; line-height: 1.35; page-break-after: avoid; }
    h3.section-title { font-size: 10.5pt; margin: 10px 0 4px 0; page-break-after: avoid; }
  /* Major happening +10% (22% → 24.2%); remaining width split between the two content columns. */
    table.wb-pdf-table { width: 100%; border-collapse: collapse; margin: 0 0 10px 0; table-layout: fixed; page-break-inside: auto; }
    table.wb-pdf-table thead { display: table-header-group; }
    table.wb-pdf-table tbody { display: table-row-group; }
    table.wb-pdf-table tr { page-break-inside: auto; page-break-after: auto; }
    table.wb-pdf-table th, table.wb-pdf-table td {
        border: 1px solid #94a3b8;
        padding: 4px 5px;
        vertical-align: top;
        font-size: 9.5pt;
        word-wrap: break-word;
        overflow-wrap: break-word;
        page-break-inside: auto;
    }
    table.wb-pdf-table th { background: #f8fafc; font-weight: bold; }
    table.wb-pdf-table td { background: #fdf2f8; }
    table.wb-pdf-table td.cell-rich { font-size: 9.5pt; line-height: 1.35; }
    table.wb-pdf-table td.col-major { font-weight: bold; }
    table.wb-pdf-table col.col-major { width: 24.2%; }
    table.wb-pdf-table col.col-mid { width: 37.9%; }
    table.wb-pdf-table col.col-last { width: 37.9%; }
    table.wb-pdf-table.bottlenecks col.col-major { width: 28%; }
    table.wb-pdf-table.bottlenecks col.col-mid { width: 24%; }
    table.wb-pdf-table.bottlenecks col.col-last { width: 48%; }
</style>
</head>
<body>
<?php
/** @var \Illuminate\Support\Collection<int,\App\Models\WeeklyBriefingReport> $reports */
/** @var int $isoYear */
/** @var int $isoWeek */
/** @var string|null $compiledPdfHeading */
/** @var string|null $compiledPdfMetaHtml */
use App\Helpers\PrintHelper;

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

<?php foreach ($reports as $report) {
    $dirName = $report->directorate?->name ?? 'Directorate / Office';
    $unitLabel = $report->contributionEntityLabel();

    $bodyRows = [];
    foreach ($report->section1_major_happenings ?? [] as $row) {
        $mhPlain = trim(strip_tags((string) ($row['major_happening'] ?? '')));
        $dPlain = trim(strip_tags((string) ($row['description_key_actions'] ?? '')));
        $sPlain = trim(strip_tags((string) ($row['strategic_relevance'] ?? '')));
        if ($mhPlain === '' && $dPlain === '' && $sPlain === '') {
            continue;
        }
        $bodyRows[] = $row;
    }

    $bottleneckRows = [];
    foreach ($report->section2_bottlenecks ?? [] as $b) {
        $iPlain = trim(strip_tags((string) ($b['issue'] ?? '')));
        $pPlain = trim(strip_tags((string) ($b['impact_risk'] ?? '')));
        $aPlain = trim(strip_tags((string) ($b['required_action'] ?? '')));
        if ($iPlain === '' && $pPlain === '' && $aPlain === '') {
            continue;
        }
        $bottleneckRows[] = $b;
    }
    ?>
<div class="compiled-division-page">
    <h2><?php echo htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8'); ?> <span style="font-weight:normal;color:#64748b;">(<?php echo htmlspecialchars($dirName, ENT_QUOTES, 'UTF-8'); ?>)</span></h2>
    <p class="compiled-division-meta"><strong>Status:</strong> <?php echo htmlspecialchars($report->status, ENT_QUOTES, 'UTF-8'); ?>
    <?php
    if ($report->submitted_by_staff_id && $report->submittedBy) {
        $sn = trim((string) ($report->submittedBy->name ?? ''));
        if ($sn === '') {
            $sn = 'Staff #'.(int) $report->submitted_by_staff_id;
        }
        echo ' · Submitted by: <strong>'.htmlspecialchars($sn, ENT_QUOTES, 'UTF-8').'</strong>';
        if ($report->submitted_at) {
            echo ' ('.htmlspecialchars($report->submitted_at->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8').')';
        }
    }
    if ($report->requiresDirectorReview()) {
        echo '<br><strong>Director review:</strong> '.htmlspecialchars($report->directorReviewSummaryLine(), ENT_QUOTES, 'UTF-8');
        $trailSum = $report->directorReviewTrailSummary();
        if ($trailSum !== '—') {
            echo ' · <span style="color:#64748b;">Trail:</span> '.htmlspecialchars($trailSum, ENT_QUOTES, 'UTF-8');
        }
    }
    ?></p>

    <?php if (count($bodyRows) > 0) { ?>
    <h3 class="section-title">Section 1 — Major happenings</h3>
    <table class="wb-pdf-table happenings">
        <colgroup>
            <col class="col-major">
            <col class="col-mid">
            <col class="col-last">
        </colgroup>
        <thead>
            <tr>
                <th>Major Happening</th>
                <th>Description and Key Actions</th>
                <th>Strategic Relevance to Africa CDC</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bodyRows as $rowIdx => $row) {
            $mh = trim((string) ($row['major_happening'] ?? ''));
            $num = $rowIdx + 1;
            $mhBody = trim(strip_tags($mh)) !== '' ? PrintHelper::sanitizeRichTextForMpdf($mh) : '<span style="color:#64748b;">—</span>';
            $mhOut = '<strong>'.(int) $num.'.</strong> '.$mhBody;
            ?>
            <tr>
                <td class="col-major cell-rich"><?php echo $mhOut; ?></td>
                <td class="cell-rich"><?php echo PrintHelper::sanitizeRichTextForMpdf($row['description_key_actions'] ?? ''); ?></td>
                <td class="cell-rich"><?php echo PrintHelper::sanitizeRichTextForMpdf($row['strategic_relevance'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>

    <?php if (count($bottleneckRows) > 0) { ?>
    <h3 class="section-title">Section 2 — Bottlenecks</h3>
    <table class="wb-pdf-table bottlenecks">
        <colgroup>
            <col class="col-major">
            <col class="col-mid">
            <col class="col-last">
        </colgroup>
        <thead>
            <tr>
                <th>Issue</th>
                <th>Impact</th>
                <th>Required action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bottleneckRows as $b) { ?>
            <tr>
                <td class="cell-rich"><?php echo PrintHelper::sanitizeRichTextForMpdf((string) ($b['issue'] ?? '')); ?></td>
                <td class="cell-rich"><?php echo PrintHelper::sanitizeRichTextForMpdf((string) ($b['impact_risk'] ?? '')); ?></td>
                <td class="cell-rich"><?php echo PrintHelper::sanitizeRichTextForMpdf((string) ($b['required_action'] ?? '')); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
<?php } ?>
</body>
</html>
