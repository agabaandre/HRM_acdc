<style>
    /* Ensures proper formatting for PDF */
    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Arial', 'Helvetica', sans-serif;
        font-size: 11px;
        line-height: 1.6;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .profile-container {
        padding: 25px;
        max-width: 100%;
    }

    .header {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid #119a48;
    }

    .header img {
        width: 120px;
        height: auto;
        margin-bottom: 10px;
    }

    .header h2 {
        margin: 10px 0 5px 0;
        color: #119a48;
        font-size: 22px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .staff-photo {
        text-align: center;
        margin: 20px 0 25px 0;
    }

    .staff-photo img,
    .staff-photo div {
        border: 3px solid #119a48;
        border-radius: 8px;
        padding: 5px;
    }

    .info-grid {
        display: table;
        width: 100%;
        margin-bottom: 20px;
    }

    .info-section {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding: 0 15px;
    }

    .info-section:first-child {
        padding-left: 0;
    }

    .info-section:last-child {
        padding-right: 0;
    }

    h4 {
        color: #119a48;
        border-bottom: 2px solid #119a48;
        padding-bottom: 8px;
        margin-bottom: 12px;
        margin-top: 0;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    ul {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }

    ul li {
        margin-bottom: 8px;
        padding: 4px 0;
        border-bottom: 1px dotted #e0e0e0;
    }

    ul li:last-child {
        border-bottom: none;
    }

    ul li strong {
        color: #555;
        display: inline-block;
        min-width: 140px;
        font-weight: 600;
    }

    .section {
        margin-bottom: 25px;
        page-break-inside: avoid;
    }

    .section.full-width {
        width: 100%;
    }

    .contract-grid {
        display: table;
        width: 100%;
    }

    .contract-row {
        display: table-row;
    }

    .contract-cell {
        display: table-cell;
        width: 50%;
        padding: 0 15px 8px 0;
        vertical-align: top;
    }

    .contract-cell:nth-child(even) {
        padding-right: 0;
    }

    .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .status-active {
        background-color: #d4edda;
        color: #155724;
    }

    .status-expired {
        background-color: #f8d7da;
        color: #721c24;
    }

    .status-due {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-renewal {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    /* Hide buttons when generating PDF */
    .no-print {
        display: none !important;
    }

    @media print {
        .profile-container {
            padding: 15px;
        }
        
        .section {
            page-break-inside: avoid;
        }
    }
</style>

<div class="profile-container">
    <div class="header">
    <?php $data = $staffs['0']; ?>
        <img src="<?php echo base_url(); ?>assets/images/AU_CDC_Logo-800.png" alt="AU CDC Logo" style="width: 120px; height: auto;">
        <h2><?= trim($data->title . ' ' . $data->fname . ' ' . $data->lname . ' ' . ($data->oname ?? '')) ?></h2>
        <?php if (!empty($data->SAPNO)): ?>
            <p style="margin: 5px 0; color: #666; font-size: 12px;"><strong>SAP Number:</strong> <?= $data->SAPNO ?></p>
        <?php endif; ?>
    </div>
     
    <div class="staff-photo">
    <?php 
        $surname = $data->lname;
        $other_name = $data->fname;
        $image_path = base_url() . 'uploads/staff/' . @$data->photo;
        echo generate_user_avatar($surname, $other_name, $image_path, $data->photo);
	?>
    </div>

    <div class="info-grid">
        <div class="info-section">
            <div class="section">
            <h4>Personal Information</h4>
            <ul>
                    <?php if (!empty($data->SAPNO)): ?>
                        <li><strong>SAP Number:</strong> <?= $data->SAPNO ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->gender)): ?>
                <li><strong>Gender:</strong> <?= $data->gender ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->date_of_birth)): ?>
                        <li><strong>Date of Birth:</strong> <?= $data->date_of_birth ?>
                            <?php if (function_exists('calculate_age')): ?>
                                <span style="color: #666;">(Age: <?= calculate_age($data->date_of_birth) ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($data->nationality)): ?>
                <li><strong>Nationality:</strong> <?= $data->nationality ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->initiation_date)): ?>
                <li><strong>Initiation Date:</strong> <?= $data->initiation_date ?></li>
                    <?php endif; ?>
            </ul>
            </div>
        </div>

        <div class="info-section">
            <div class="section">
            <h4>Contact Information</h4>
            <ul>
                    <?php if (!empty($data->work_email)): ?>
                        <li><strong>Work Email:</strong> <?= $data->work_email ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->private_email)): ?>
                        <li><strong>Private Email:</strong> <?= $data->private_email ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->tel_1) || !empty($data->tel_2)): ?>
                        <li><strong>Telephone:</strong> 
                            <?php 
                            $phones = array_filter([$data->tel_1 ?? '', $data->tel_2 ?? '']);
                            echo implode(' / ', $phones);
                            ?>
                </li>
                    <?php endif; ?>
                    <?php if (!empty($data->whatsapp)): ?>
                        <li><strong>WhatsApp:</strong> <?= $data->whatsapp ?></li>
                    <?php endif; ?>
                    <?php if (!empty($data->physical_location)): ?>
                        <li><strong>Physical Location:</strong> <?= $data->physical_location ?></li>
                    <?php endif; ?>
            </ul>
            </div>
        </div>
    </div>

    <div class="section full-width">
        <h4>Contract Information</h4>
        <div style="display: table; width: 100%;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding-right: 15px; vertical-align: top;">
        <ul>
                        <?php if (!empty($data->duty_station_name)): ?>
            <li><strong>Duty Station:</strong> <?= $data->duty_station_name ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->job_name)): ?>
                            <li><strong>Job Title:</strong> <?= $data->job_name ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->first_supervisor)): ?>
                            <li><strong>First Supervisor:</strong> <?= @staff_name($data->first_supervisor) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->funder)): ?>
                            <li><strong>Funder:</strong> <?= $data->funder ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->grade)): ?>
                            <li><strong>Grade:</strong> <?= $data->grade ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->status)): ?>
                            <li><strong>Contract Status:</strong> 
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $data->status)) ?>">
                                    <?= $data->status ?>
                                </span>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($data->start_date)): ?>
                            <li><strong>Current Contract Start Date:</strong> <?= $data->start_date ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div style="display: table-cell; width: 50%; vertical-align: top;">
                    <ul>
                        <?php if (!empty($data->division_name)): ?>
            <li><strong>Division:</strong> <?= $data->division_name ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->job_acting) && $data->job_acting != 'N/A'): ?>
                            <li><strong>Acting Job:</strong> <?= $data->job_acting ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->second_supervisor)): ?>
            <li><strong>Second Supervisor:</strong> <?= @staff_name($data->second_supervisor) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->contracting_institution)): ?>
            <li><strong>Contracting Organisation:</strong> <?= $data->contracting_institution ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->contract_type)): ?>
            <li><strong>Contract Type:</strong> <?= $data->contract_type ?></li>
                        <?php endif; ?>
                        <?php if (!empty($data->end_date)): ?>
            <li><strong>Current Contract End Date:</strong> <?= $data->end_date ?></li>
                        <?php endif; ?>
        </ul>
                </div>
            </div>
        </div>
        <?php if (!empty($data->comments)): ?>
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                <strong>Contract Comments:</strong>
                <p style="margin-top: 5px; color: #666; font-style: italic;"><?= nl2br(htmlspecialchars($data->comments)) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top: 30px; padding-top: 15px; border-top: 2px solid #119a48; text-align: center; color: #666; font-size: 10px;">
        <p>Generated on <?= date('F d, Y \a\t H:i') ?> | Africa CDC Staff Management System</p>
    </div>

</div>
