<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: freesans, dejavusans, sans-serif; font-size: 11pt; color: #0f172a; margin: 24px; line-height: 1.45; }
    h1 { text-align: center; font-size: 16pt; color: #0d5c2f; margin: 0 0 6px 0; }
    .meta { text-align: center; font-size: 10pt; color: #64748b; margin-bottom: 20px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>
<?php
/** @var \Illuminate\Support\Collection<int,\App\Models\WeeklyBriefingReport> $reports */
/** @var int $isoYear */
/** @var int $isoWeek */
use App\Helpers\PrintHelper;
?>
<h1>Weekly Briefing — Compiled</h1>
<div class="meta">ISO week <strong>W<?php echo (int) $isoWeek; ?> / <?php echo (int) $isoYear; ?></strong> · <?php echo count($reports); ?> reporting unit(s) · Grouped by directorate / office</div>

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
    <p><strong>Status:</strong> <?php echo htmlspecialchars($report->status); ?></p>

    <h3 style="font-size:11pt;">Section 1 — Major happenings</h3>
    <?php
    $rows = $report->section1_major_happenings ?? [];
    $i = 1;
    foreach ($rows as $row) {
        if (empty(trim(strip_tags((string)($row['description_key_actions'] ?? '')))) && empty(trim(strip_tags((string)($row['strategic_relevance'] ?? ''))))) {
            continue;
        }
        echo '<p><em>Happening '.$i.'</em></p>';
        echo '<p><strong>Description &amp; key actions</strong></p><div>'.PrintHelper::sanitizeRichTextForMpdf($row['description_key_actions'] ?? '').'</div>';
        echo '<p><strong>Strategic relevance</strong></p><div>'.PrintHelper::sanitizeRichTextForMpdf($row['strategic_relevance'] ?? '').'</div>';
        $i++;
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
