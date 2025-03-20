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
    <?php $data = $staffs['0']; ?>
        <img src="<?php echo base_url(); ?>assets/images/AU_CDC_Logo-800.png" width="200">
        <h2><?=$data->title.' ' .$data->fname.' '.$data->lname.' '.$data->oname?></h2>
    </div>
     
    <div class="staff-photo">
    <?php 
            $surname=$data->lname;
            $other_name=$data->fname;
            $image_path=base_url().'uploads/staff/'.@$data->photo;
            echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path,$data->photo);
							
	?>
    </div>

    <div class="row">
        <div class="col-md-6 section">
            <h4>Personal Information</h4>
            <ul>
                <li><strong>SAPNO:</strong> <?= $data->SAPNO ?></li>
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
            <li><strong>Duty Station:</strong> <?= $data->duty_station_name ?></li>
            <li><strong>Division:</strong> <?= $data->division_name ?></li>
            <li><strong>Job:</strong> <?= @character_limiter($data->job_name, 30) ?></li>
            <?php if (!empty($data->job_acting) && $data->job_acting != 'N/A') { ?>
                <li><strong>Acting Job:</strong> <?= @character_limiter($data->job_acting, 30) ?></li>
            <?php } ?>
            <li><strong>First Supervisor:</strong> <?= @staff_name($data->first_supervisor) ?></li>
            <li><strong>Second Supervisor:</strong> <?= @staff_name($data->second_supervisor) ?></li>
            <li><strong>Funder:</strong> <?= $data->funder ?></li>
            <li><strong>Contracting Organisation:</strong> <?= $data->contracting_institution ?></li>
            <li><strong>Grade:</strong> <?= $data->grade ?></li>
            <li><strong>Contract Type:</strong> <?= $data->contract_type ?></li>
            <li><strong>Contract Status:</strong> <?= $data->status ?></li>
            <li><strong>Contract Start Date:</strong> <?= $data->start_date ?></li>
            <li><strong>Contract End Date:</strong> <?= $data->end_date ?></li>
            <li><strong>Contract Comments:</strong> <?= $data->comments ?></li>
        </ul>
    </div>

</div>
