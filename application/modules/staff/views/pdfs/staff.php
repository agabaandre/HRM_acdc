<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Status Report - Africa CDC</title>
    <style>
     
        .header {
        text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            width: 150px;
        }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin-top: 10px; }
        .table-container { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .footer { text-align: center; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
        <img src="<?= FCPATH . 'assets/images/AU_CDC_Logo-800.png' ?>" alt="AU CDC Logo" style="height:120px;">
        </div>
        <div class="title"><?php echo $title?></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SAPNO</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Nationality</th>
                        <th>Duty Station</th>
                        <th>Division</th>
                        <th>Job</th>
                        <th>Contract Status</th>
                        <th>First Supervisor</th>
                        <th>Second Supervisor</th>
                        <th>Funder</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($staffs as $data) : 
                      
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $data->SAPNO ?></td>
                            <td><?= $data->lname . ' ' . $data->fname ?></td>
                            <td><?= $data->gender ?></td>
                            <td><?= $data->nationality ?></td>
                            <td><?= $data->duty_station_name ?></td>
                            <td><?= $data->division_name ?></td>
                            <td><?= $data->job_name ?></td>
                            <td><?= $data->status ?></td>
                            <td><?= staff_name($data->first_supervisor) ?></td>
                            <td><?= staff_name(@$data->second_supervisor) ?></td>
                            <td><?= $data->funder ?></td>
                            <td><?= $data->start_date ?></td>
                            <td><?= $data->end_date ?></td>
                            <td><?= $data->work_email ?></td>
                        </tr>
                    <?php endforeach;
                    //dd($staffs);
                    ?>
                    
                </tbody>
            </table>
        </div>
        
    </div>
</body>
</html>
