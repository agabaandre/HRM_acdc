
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Today</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Tomorrow</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">Next 7 days</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="month-tab" data-bs-toggle="tab" data-bs-target="#month" type="button" role="tab" aria-controls="month" aria-selected="false">Next 30 days</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Today</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($today as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.@get_photo($data->staff_id);
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->contracts[0]->grade_name ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Tomorrow</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 0;
                        foreach ($tomorrow as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.@get_photo($data->staff_id);
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->contracts[0]->grade_name ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 7 days</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($week as $data) : ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.@get_photo($data->staff_id);
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->contracts[0]->grade_name ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="month" role="tabpanel" aria-labelledby="month-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 30 days</h3>
                <table class="table mydata table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Grade</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Job</th>
                            <th>Duty Station</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                        <?php
                        $i = 1;
                        foreach ($month as $data) : ?> 
                        <tr>

                                <td><?= $i++ ?></td>
                                <td><?= $data->title ?></td>
                                <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
           
                                <td>
                                    <?php 
                                    $surname=$data->lname;
                                    $other_name=$data->fname;
                                    $image_path=base_url().'uploads/staff/'.@get_photo($data->staff_id);
                                    echo  $staff_photo = generate_user_avatar($surname, $other_name, $image_path);
                                    
                                    ?>
                                    
                                </td>
                                <td><?= @$data->contracts[0]->grade_name ?></td>
                                <td><?= $data->date_of_birth ?></td>
                                <td><?= calculate_age($data->date_of_birth) ?></td>
                                <td><?= $data->gender ?></td>
                                <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->station_name, 20) ?></td>
                                <td><?= @character_limiter(@$data->contracts[0]->division_name, 6) ?></td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
