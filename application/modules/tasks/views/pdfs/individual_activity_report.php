<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Individual Activity Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h3 {
            color: #07579A;
            margin: 10px 0;
            font-size: 18px;
        }
        .header img {
            height: 80px;
            margin-bottom: 15px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info-table td:first-child {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #07579A;
            width: 30%;
        }
        .section-title {
            color: #07579A;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0 15px 0;
            border-bottom: 2px solid #07579A;
            padding-bottom: 5px;
        }
        .report-content {
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #07579A;
            margin: 15px 0;
            min-height: 100px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .no-report {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        .footer-info {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="AU CDC Logo" style="height:100px;">
    <h3>Activity Report</h3>
</div>

<!-- Activity Information -->
<table class="info-table">
    <tr>
        <td>Activity Name:</td>
        <td><?= $activity->activity_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Activity ID:</td>
        <td><?= $activity->activity_id ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Team Member:</td>
        <td><?= $activity->member_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Job Title:</td>
        <td><?= $activity->job_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Division:</td>
        <td><?= $activity->division_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Email:</td>
        <td><?= $activity->work_email ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Start Date:</td>
        <td><?= $activity->start_date ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>End Date:</td>
        <td><?= $activity->end_date ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Work Plan:</td>
        <td><?= $activity->work_plan_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Status:</td>
        <td>
            <?php if (!empty($report)): ?>
                <span class="status-badge status-completed">Completed</span>
            <?php else: ?>
                <span class="status-badge status-pending">In Progress</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td>Report Status:</td>
        <td>
            <?php if (!empty($report)): ?>
                <span class="status-badge status-completed">Report Submitted</span>
            <?php else: ?>
                <span class="status-badge status-pending">No Report</span>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- Activity Description/Comments -->
<?php if (!empty($activity->comments)): ?>
<div class="section-title">Activity Description</div>
<div class="report-content">
    <?= nl2br(htmlspecialchars($activity->comments)) ?>
</div>
<?php endif; ?>

<!-- Work Plan Details -->
<?php if (!empty($work_plan)): ?>
<div class="section-title">Work Plan Details</div>
<table class="info-table">
    <tr>
        <td>Work Plan Name:</td>
        <td><?= $work_plan->activity_name ?? 'N/A' ?></td>
    </tr>
    <tr>
        <td>Work Plan ID:</td>
        <td><?= $work_plan->id ?? 'N/A' ?></td>
    </tr>
    <?php if (!empty($work_plan->description)): ?>
    <tr>
        <td>Description:</td>
        <td><?= nl2br(htmlspecialchars($work_plan->description)) ?></td>
    </tr>
    <?php endif; ?>
</table>
<?php endif; ?>

<!-- Report Section -->
<div class="section-title">Activity Report</div>
<?php if (!empty($report)): ?>
    <table class="info-table">
        <tr>
            <td>Report ID:</td>
            <td><?= $report->report_id ?? 'N/A' ?></td>
        </tr>
        <tr>
            <td>Report Date:</td>
            <td><?= $report->report_date ?? 'N/A' ?></td>
        </tr>
        <tr>
            <td>Submitted At:</td>
            <td><?= $report->created_at ?? 'N/A' ?></td>
        </tr>
        <tr>
            <td>Report Status:</td>
            <td>
                <span class="status-badge status-completed"><?= ucfirst($report->report_status ?? 'Submitted') ?></span>
            </td>
        </tr>
    </table>
    
    <div class="section-title">Report Content</div>
    <div class="report-content">
        <?= $report->description ?? 'No report content available' ?>
    </div>
<?php else: ?>
    <div class="no-report">
        <p>No report has been submitted for this activity yet.</p>
        <p>The activity is still in progress.</p>
    </div>
<?php endif; ?>

<!-- Footer Information -->
<div class="footer-info">
    <p><strong>Report Generated:</strong> <?= $generated_date ?? date('M d, Y H:i:s') ?></p>
    <p><strong>Generated By:</strong> <?= $this->session->userdata('user')->fname . ' ' . $this->session->userdata('user')->lname ?></p>
    <p><strong>System:</strong> Activity Management System - AU CDC</p>
</div>

</body>
</html>
