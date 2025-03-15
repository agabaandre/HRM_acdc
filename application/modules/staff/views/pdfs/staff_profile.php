<style>
    /* Ensures proper formatting for PDF */
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }

    .profile-container {
        padding: 20px;
    }

    .header {
        text-align: center;
        margin-bottom: 20px;
    }

    .header img {
        width: 150px;
    }

    .staff-photo {
        text-align: center;
        margin-bottom: 10px;
    }

    h4 {
        color: #07579A;
        border-bottom: 2px solid #fbb924;
        padding-bottom: 5px;
        margin-bottom: 10px;
    }

    ul {
        list-style: none;
        padding-left: 0;
    }

    ul li {
        margin-bottom: 5px;
    }

    .section {
        margin-bottom: 20px;
    }

    /* Hide buttons when generating PDF */
    .no-print {
        display: none !important;
    }
</style>

<div class="profile-container">
    <div class="header">
        <img src="<?php echo base_url(); ?>/assets/images/AU_CDC_Logo-800.png">
        <h2>Staff Profile</h2>
    </div>

    <div class="staff-photo">
        <?= @$staff_photo ?>
        <h3><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h3>
    </div>

    <div class="row">
        <div class="col-md-6 section">
            <h4>Personal Information</h4>
            <ul>
                <li><strong>SAPNO:</strong> <?= $data->SAPNO ?></li>
                <li><strong>Title:</strong> <?= $data->title ?></li>
                <li><strong>Gender:</strong> <?= $data->gender ?></li>
                <li><strong>Date of Birth:</strong> <?= $data->date_of_birth ?></li>
                <li><strong>Nationality:</strong> <?= $data->nationality ?></li>
                <li><strong>Initiation Date:</strong> <?= $data->initiation_date ?></li>
            </ul>
        </div>

        <div class="col-md-6 section">
            <h4>Contact Information</h4>
            <ul>
                <li><strong>Email:</strong> <?= @$data->work_email ?></li>
                <li><strong>Telephone:</strong> <?= @$data->tel_1 ?>
                    <?php if (!empty($data->tel_2)) { echo ' / ' . $data->tel_2; } ?>
                </li>
                <li><strong>WhatsApp:</strong> <?= @$data->whatsapp ?></li>
                <li><strong>Physical Location:</strong> <?= @$data->physical_location ?></li>
            </ul>
        </div>
    </div>

    <div class="section">
        <h4>Contract Information</h4>
        <ul>
            <li><strong>Duty Station:</strong> <?= $cont->duty_station_name ?></li>
            <li><strong>Division:</strong> <?= $cont->division_name ?></li>
            <li><strong>Job:</strong> <?= @character_limiter($cont->job_name, 30) ?></li>
            <?php if (!empty($cont->job_acting) && $cont->job_acting != 'N/A') { ?>
                <li><strong>Acting Job:</strong> <?= @character_limiter($cont->job_acting, 30) ?></li>
            <?php } ?>
            <li><strong>First Supervisor:</strong> <?= @staff_name($cont->first_supervisor) ?></li>
            <li><strong>Second Supervisor:</strong> <?= @staff_name($cont->second_supervisor) ?></li>
            <li><strong>Funder:</strong> <?= $cont->funder ?></li>
            <li><strong>Contracting Organisation:</strong> <?= $cont->contracting_institution ?></li>
            <li><strong>Grade:</strong> <?= $cont->grade ?></li>
            <li><strong>Contract Type:</strong> <?= $cont->contract_type ?></li>
            <li><strong>Contract Status:</strong> <?= $cont->status ?></li>
            <li><strong>Contract Start Date:</strong> <?= $cont->start_date ?></li>
            <li><strong>Contract End Date:</strong> <?= $cont->end_date ?></li>
            <li><strong>Contract Comments:</strong> <?= $cont->comments ?></li>
        </ul>
    </div>

    <!-- Button Hidden When Printing -->
    <div class="text-center no-print">
        <a href="<?php echo base_url(); ?>staff/staff_contracts/<?php echo $data->staff_id; ?>" class="btn btn-primary">
            Manage Contracts <i class="fa fa-eye"></i>
        </a>
    </div>
</div>
