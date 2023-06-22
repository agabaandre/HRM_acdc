<?php
use Illuminate\Database\Eloquent\Builder;
class Staff_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }

    public function get_all()
    {
       
      $query = Employee::orderBy("lname", "desc");
       

        // if (@$search['nationality_id'])
        // $query->where('nationality_id', $search->nationality_id);
        //implement ci-pagination later
        $results = $query->with('contracts', 'contracts.funder')
        ->take(20)->skip(20)->get();
        //$results = $query->with('contracts', 'contracts.funder')->get();

        return $results;
    }
    public function update_staff($data){
        $this->db->where('staff_id', $data['staff_id']);
        $query =$this->db->update("staff",$data);
        return $query;
    }
    public function update_contract($data)
    {
        $this->db->where('staff_id', $data['staff_id']);
        $query = $this->db->update('staff_contracts',$data);
        return $query;
    }

    public function get_status($flag)
    {
        $query = Employee::orderBy("lname", "desc");

        $results = $query->with('contracts', 'contracts.funder')
        ->when($flag, function ($query, $flag) {
            $query->whereHas('contracts', function (Builder $query) use ($flag) {
                $query->where('status_id','=', $flag);
            });
               
        })
            ->take(20)
            ->skip(0)
            ->get();

        return $results;
    }
    public function getBirthdaysForToday()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Retrieve employees with birthdays for today
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$currentDate', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForTomorrow()
    {
        // Get the date for tomorrow
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));

        // Retrieve employees with birthdays for tomorrow
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$tomorrowDate', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForNextSevenDays()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Get the date for 7 days from now
        $nextSevenDays =date('Y-m-d', strtotime('+7 days'));

        // Retrieve employees with birthdays in the next 7 days
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextSevenDays', '%m-%d')")
        ->with('contracts')
        ->get();
    }

    public function getBirthdaysForNextThirtyDays()
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Get the date for 30 days from now
        $nextThirtyDays = date('Y-m-d', strtotime('+30 days'));

        // Retrieve employees with birthdays in the next 30 days
        return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextThirtyDays', '%m-%d')") 
        ->with('contracts')
        ->get();

    }

    public function add_staff($sapno, $title, $fname, $lname, $oname, $dob, $gender, $nationality_id, $initiation_date, $tel_1, $tel_2, $whatsapp, $work_email, $private_email, $physical_location)
    {
        $data = array(
            'SAPNO' => $sapno,
            'title' => $title,
            'fname' => $fname,
            'lname' => $lname,
            'oname' => $oname,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'nationality_id' => $nationality_id,
            'initiation_date' => $initiation_date,
            'tel_1' => $tel_1,
            'tel_2' => $tel_2,
            'whatsapp' => $whatsapp,
            'work_email' => $work_email,
            'private_email' => $private_email,
            'physical_location' => $physical_location,
            'created_at' =>date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $sapold = $this->db->query("SELECT * from staff where SAPNO='$sapno'")->num_rows();
        if ($sapold == 0) {
            $this->db->insert('staff', $data);
        }
        else{
            $this->db->where('SAPNO', $sapno);
            $this->db->update('staff', $data);
        }
        return $this->db->insert_id();
    }

    public function add_contract_information($staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, $duty_station_id, $division_id, $start_date, $end_date, $status_id, $file_name, $comments)
    {
        $data = array(
            'staff_id' => $staff_id,
            'job_id' => $job_id,
            'job_acting_id' => $job_acting_id,
            'grade_id' => $grade_id,
            'contracting_institution_id' => $contracting_institution_id,
            'funder_id' => $funder_id,
            'first_supervisor' => $first_supervisor,
            'second_supervisor' => $second_supervisor,
            'contract_type_id' => $contract_type_id,
            'duty_station_id' => $duty_station_id,
            'division_id' => $division_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status_id' => $status_id,
            'file_name' => $file_name,
            'comments' => $comments,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')

        );

        $this->db->insert('staff_contracts', $data);
        return $this->db->insert_id();
    }

}

 
    
  
