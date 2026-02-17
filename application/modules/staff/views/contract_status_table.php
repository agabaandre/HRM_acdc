<?php
if (empty($staffs)) {
    echo '<tr><td colspan="25" class="text-center">No staff found</td></tr>';
} else {
    $i = 1;
    $offset = ($page * $per_page);
    if ($offset != "") {
        $i = $offset + 1;
    }

    foreach ($staffs as $data) :
        $cont = Modules::run('staff/latest_staff_contract', $data->staff_id);
        if (!$cont) {
            $cont = (object)['duty_station_name' => '', 'division_name' => '', 'job_acting' => '', 'first_supervisor' => '', 'second_supervisor' => '', 'funder' => '', 'start_date' => '', 'end_date' => ''];
        }
?>
    <tr>
        <td><?= $i++ ?></td>
        <td><?= $data->SAPNO ?></td>
        <td><?= $data->title ?></td>
        <td>
            <?php 
            $surname = $data->lname;
            $other_name = $data->fname;
            $image_path = base_url() . 'uploads/staff/' . @$data->photo;
            echo generate_user_avatar($surname, $other_name, $image_path, $data->photo);
            ?>
        </td>
        <td><a href="#" class="view-staff-profile" data-staff-id="<?php echo $data->staff_id; ?>" data-bs-toggle="modal" data-bs-target="#employeeProfileModal"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
        <td><?= $data->gender ?></td>
        <td><?= !empty($data->date_of_birth) ? $data->date_of_birth : 'N/A' ?></td>
        <td><?php echo !empty($data->date_of_birth) ? calculate_age($data->date_of_birth) : 'N/A'; ?></td>
        <td><?= $data->nationality; ?></td>
        <td><?= @$cont->duty_station_name; ?></td>
        <td><?= @$cont->division_name; ?></td>
        <td><?= @$data->grade; ?></td>
        <td><?= @character_limiter($data->job_name, 30); ?></td>
        <td><?= !empty($data->initiation_date) ? $data->initiation_date : 'N/A'; ?></td>
        <td><?= !empty($cont->start_date) ? $cont->start_date : 'N/A'; ?></td>
        <td><?= !empty($cont->end_date) ? $cont->end_date : 'N/A'; ?></td>
        <td><?= !empty($data->initiation_date) ? years_of_tenure($data->initiation_date) : 'N/A'; ?></td>
        <td><?= @character_limiter($data->status); ?></td>
        <td><?= @character_limiter($cont->job_acting, 30); ?></td>
        <td><?= @staff_name($cont->first_supervisor); ?></td>
        <td><?= @staff_name($cont->second_supervisor); ?></td>
        <td><?= @$cont->funder; ?></td>
        <td><?= $data->work_email; ?></td>
        <td><?= @$data->tel_1 ?> <?php if (!empty($data->tel_2)) {
                                        echo '  ' . $data->tel_2;
                                    } ?></td>
        <td><?= $data->whatsapp ?></td>
    </tr>
<?php 
    endforeach; 
}
?>

