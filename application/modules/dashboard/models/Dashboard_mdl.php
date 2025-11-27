<?php
class Dashboard_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
        $this->load->model("Nationality");
        $this->load->model('Funder');
    }

    public function get_all($search=[])
    {
        $query = Employee::orderBy("lname","desc");

        if(@$search['nationality_id'])
            $query->where('nationality_id',1);

        $results = $query->with('contracts','contracts.funder')->take(20)->skip(20)->get()->toArray();
        return $results;
    }


    // function update_contract_status()
    // {

    //     $sqlst = "SELECT staff_contract_id,end_date,staff_id FROM  staff_contracts WHERE status_id=1 || status_id=2";

    //     $resultst = $this->db->query($sqlst)->result_array();


    //     foreach ($resultst as $rowst):

    //         $date1 = date('Y-m-d');
    //         $date2 = $rowst['end_date'];
    //         $staff_contract_id = $rowst['staff_contract_id'];
    //         $staff_id = $rowst['staff_id'];

    //         $dateDiff = $this->dateDiff($date1, $date2);

    //         $SQLSTAFF = $this->db->query("UPDATE staff SET flag=1 WHERE staff_id=$staff_id");

    //         if ($dateDiff > 0 && $dateDiff <= 60) {
    //             //$status= 'Due';
    //             $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=2 WHERE staff_contract_id=$staff_contract_id");
    //         } elseif ($dateDiff < 0) {
    //             //$status= 'Expired';
    //             $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=3 WHERE staff_contract_id=$staff_contract_id");
    //         } elseif ($dateDiff > 60) {
    //             //$status= 'Active';
    //             $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=1 WHERE staff_contract_id=$staff_contract_id");
    //         } else {
    //             $status = '';

    //         }
    //     endforeach;

    // }
    function all_staff()
    {
        //self::update_contract_status();
        $filters = array();
       return  count($this->staff_mdl->get_active_staff_data($filters));

    }
    function staff_renewal()
    {
        $filters['status_id'] =7;	
		
		return count($data['staffs'] = $this->staff_mdl->get_status($filters));

    }
    public function due_contracts()
    {
       
  
        $filters['status_id'] =2;	
		
		return count($data['staffs'] = $this->staff_mdl->get_status($filters));

    }

    function expired_contracts()
    {
        $filters['status_id'] =3;	
		
		return count($data['staffs'] = $this->staff_mdl->get_status($filters));

    }
    function nationalities()
    {
        return Nationality::all()->count();
    }

    function staff_by_gender()
    {
        $sql5 = "SELECT gender as name,COUNT(s.staff_id) AS y FROM  staff s,staff_contracts sc WHERE s.staff_id=sc.staff_id AND sc.status_id IN(1,2) GROUP BY gender ";

        $result5 = $this->db->query($sql5)->result();

    
        return $result5;

    }
    public function staff_by_member_state()
    {
        $sqlms = "SELECT COUNT(s.staff_id) AS tt,n.nationality FROM  staff s,nationalities n,staff_contracts sc WHERE s.nationality_id=n.nationality_id AND s.staff_id=sc.staff_id AND sc.status_id IN(1,2) GROUP BY s.nationality_id";

        $resultms = $this->db->query($sqlms)->result();

        $member_states = array();
        $number = array();

        foreach ($resultms as $rowms) {

            $ms = $rowms->nationality;

            $tt = $rowms->tt;

            $member_states[] = $ms;

             $number[] = $tt;

        }
        return array('member_states' => $member_states, 'value' => $number);


    }
    public function staff_by_contract()
    {

        $sql9 = "SELECT COUNT(s.staff_id) AS no,ct.contract_type FROM  staff s,staff_contracts sc,contract_types ct WHERE s.staff_id=sc.staff_id AND sc.contract_type_id=ct.contract_type_id AND sc.status_id IN(1,2) GROUP BY sc.contract_type_id";

        $result9 = $this->db->query($sql9)->result();


        $contract_type = array();
        $value = array();



        foreach ($result9 as $row9) {

            $ct = $row9->contract_type;

            $no = $row9->no;


            $contract_type[] = $ct;

            $value[] = $no;

        }
        return array('contract_type' => $contract_type, 'value' => $value);

    }
    public function staff_by_division()
    {

        $sql11 = "SELECT COUNT(s.staff_id) AS no,d.division_name FROM  staff s,staff_contracts sc,divisions d WHERE s.staff_id=sc.staff_id AND sc.division_id=d.division_id AND sc.status_id IN(1,2) GROUP BY sc.division_id";

        $result11 = $this->db->query($sql11)->result();
        $staff_by_div=array();

        foreach ($result11 as $row11) {

            $d = $row11->division_name;

            $no = $row11->no;


            $division[] = $d;

             $value2[] = $no;
   


        }
        return array('division' => $division, 'value' => $value2);
    }

    public function search_staff($query)
{
    $this->db->like('fname', $query);
    $this->db->or_like('lname', $query);
    $this->db->or_like('SAPNO', $query);
    $this->db->or_like('work_email', $query);
    $this->db->limit(10);
    return $this->db->get('staff')->result_array();
}

    public function get_dashboard_data($division_id = null, $duty_station_id = null, $funder_id = null, $job_id = null)
    {
        // Get latest contract for each staff
        $subquery = $this->db->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        // Build base query for active staff - match staff table logic exactly
        $this->db->select('s.staff_id');
        $this->db->from('staff s');
        $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in('sc.status_id', [1, 2]); // Active & Due (same as staff table)
        
        // Apply filters
        if ($division_id) {
            $this->db->where('sc.division_id', $division_id);
        }
        if ($duty_station_id) {
            $this->db->where('sc.duty_station_id', $duty_station_id);
        }
        if ($funder_id) {
            $this->db->where('sc.funder_id', $funder_id);
        }
        if ($job_id) {
            $this->db->where('sc.job_id', $job_id);
        }
        
        $staff_ids = array_column($this->db->get()->result(), 'staff_id');
        if (empty($staff_ids)) {
            return [
                'staff' => 0,
                'two_months' => 0,
                'staff_renewal' => 0,
                'expired' => 0,
                'data_points' => [],
                'staff_by_contract' => ['contract_type' => [], 'value' => []],
                'staff_by_division' => ['division' => [], 'value' => []],
                'staff_by_member_state' => ['member_states' => [], 'value' => []],
                'staff_by_funder' => ['funder' => [], 'value' => []]
            ];
        }

        // Total active staff
        $staff = count($staff_ids);

        // Contracts due - use status_id = 2 directly (same as contract_status page)
        $this->db->select('sc.staff_id');
        $this->db->from('staff_contracts sc');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in('sc.staff_id', $staff_ids);
        $this->db->where('sc.status_id', 2); // Due contracts (status_id = 2)
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $two_months = $this->db->count_all_results();

        // Staff under renewal
        $this->db->select('sc.staff_id');
        $this->db->from('staff_contracts sc');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in('sc.staff_id', $staff_ids);
        $this->db->where('sc.status_id', 7);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $staff_renewal = $this->db->count_all_results();

        // Expired contracts
        $this->db->select('sc.staff_id');
        $this->db->from('staff_contracts sc');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in('sc.staff_id', $staff_ids);
        $this->db->where('sc.status_id', 3);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $expired = $this->db->count_all_results();

        // Staff by gender
        $this->db->select("s.gender as name, COUNT(s.staff_id) AS y");
        $this->db->from("staff s");
        $this->db->join("staff_contracts sc", "sc.staff_id = s.staff_id", "left");
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in("sc.status_id", [1, 2]);
        $this->db->where_in("s.staff_id", $staff_ids);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $this->db->group_by("s.gender");
        $data_points = $this->db->get()->result();

        // Staff by contract type
        $this->db->select("ct.contract_type, COUNT(s.staff_id) AS no");
        $this->db->from("staff s");
        $this->db->join("staff_contracts sc", "sc.staff_id = s.staff_id", "left");
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->join("contract_types ct", "ct.contract_type_id = sc.contract_type_id", "left");
        $this->db->where_in("sc.status_id", [1, 2]);
        $this->db->where_in("s.staff_id", $staff_ids);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $this->db->group_by("sc.contract_type_id");
        $contract_result = $this->db->get()->result();
        $contract_type = [];
        $contract_value = [];
        foreach ($contract_result as $row) {
            $contract_type[] = $row->contract_type;
            $contract_value[] = (int)$row->no;
        }

        // Staff by division
        $this->db->select("d.division_name, COUNT(s.staff_id) AS no");
        $this->db->from("staff s");
        $this->db->join("staff_contracts sc", "sc.staff_id = s.staff_id", "left");
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
        $this->db->where_in("sc.status_id", [1, 2]);
        $this->db->where_in("s.staff_id", $staff_ids);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $this->db->group_by("sc.division_id");
        $division_result = $this->db->get()->result();
        $division = [];
        $division_value = [];
        foreach ($division_result as $row) {
            $division[] = $row->division_name;
            $division_value[] = (int)$row->no;
        }

        // Staff by member state (nationality)
        $this->db->select("n.nationality, COUNT(s.staff_id) AS tt");
        $this->db->from("staff s");
        $this->db->join("nationalities n", "n.nationality_id = s.nationality_id", "left");
        $this->db->join("staff_contracts sc", "sc.staff_id = s.staff_id", "left");
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->where_in("sc.status_id", [1, 2]);
        $this->db->where_in("s.staff_id", $staff_ids);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $this->db->group_by("s.nationality_id");
        $member_state_result = $this->db->get()->result();
        $member_states = [];
        $member_state_value = [];
        foreach ($member_state_result as $row) {
            $member_states[] = $row->nationality;
            $member_state_value[] = (int)$row->tt;
        }

        // Staff by funder - include Active (1), Due (2), and Under Renewal (7)
        // Query independently to include all active staff (not limited by $staff_ids)
        $this->db->select("f.funder, COUNT(DISTINCT s.staff_id) AS no");
        $this->db->from("staff s");
        $this->db->join("staff_contracts sc", "sc.staff_id = s.staff_id", "left");
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->join("funders f", "f.funder_id = sc.funder_id", "left");
        $this->db->where_in("sc.status_id", [1, 2, 7]); // Active, Due, and Under Renewal
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($duty_station_id) $this->db->where('sc.duty_station_id', $duty_station_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($job_id) $this->db->where('sc.job_id', $job_id);
        $this->db->group_by("sc.funder_id");
        $funder_result = $this->db->get()->result();
        $funder_names = [];
        $funder_value = [];
        foreach ($funder_result as $row) {
            $funder_names[] = $row->funder;
            $funder_value[] = (int)$row->no;
        }

        return [
            'staff' => $staff,
            'two_months' => $two_months,
            'staff_renewal' => $staff_renewal,
            'expired' => $expired,
            'data_points' => $data_points,
            'staff_by_contract' => ['contract_type' => $contract_type, 'value' => $contract_value],
            'staff_by_division' => ['division' => $division, 'value' => $division_value],
            'staff_by_member_state' => ['member_states' => $member_states, 'value' => $member_state_value],
            'staff_by_funder' => ['funder' => $funder_names, 'value' => $funder_value]
        ];
    }

    public function get_birthday_events($division_id = null, $duty_station_id = null, $funder_id = null, $job_id = null, $start = null, $end = null)
    {
        // Get latest contract for each staff
        $subquery = $this->db->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        // Build base query
        $this->db->select("
            s.staff_id,
            s.fname,
            s.lname,
            s.oname,
            s.title,
            s.date_of_birth,
            s.gender,
            s.photo,
            sc.grade_id,
            g.grade,
            j.job_name,
            ds.duty_station_name,
            d.division_name
        ");
        $this->db->from('staff s');
        $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        $this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
        $this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
        $this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
        $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
        $this->db->where_in('sc.status_id', [1, 2]); // Active & Due (same as staff table)
        $this->db->where('s.date_of_birth IS NOT NULL', null, false);
        $this->db->where("s.date_of_birth NOT LIKE '0000-00-00%'", null, false);
        // Note: Empty string check is done in PHP loop, not in SQL to avoid DATE comparison errors

        // Apply filters - only if not empty
        if (!empty($division_id)) {
            $this->db->where('sc.division_id', $division_id);
        }
        if (!empty($duty_station_id)) {
            $this->db->where('sc.duty_station_id', $duty_station_id);
        }
        if (!empty($funder_id)) {
            $this->db->where('sc.funder_id', $funder_id);
        }
        if (!empty($job_id)) {
            $this->db->where('sc.job_id', $job_id);
        }

        $staff_list = $this->db->get()->result();
        $events = [];
        
        log_message('debug', 'Birthday events query returned ' . count($staff_list) . ' staff members');

        foreach ($staff_list as $staff) {
            if (empty($staff->date_of_birth) || !strtotime($staff->date_of_birth)) {
                continue;
            }

            try {
                $dob_obj = new DateTime($staff->date_of_birth);
                $current_year = date('Y');
                $birthday_this_year = new DateTime($current_year . '-' . $dob_obj->format('m-d'));

                // If birthday has passed this year, use next year
                if ($birthday_this_year < new DateTime('today')) {
                    $birthday_this_year->modify('+1 year');
                }

                // Filter by date range if provided
                if ($start && $end) {
                    $start_obj = new DateTime($start);
                    $end_obj = new DateTime($end);
                    if ($birthday_this_year < $start_obj || $birthday_this_year > $end_obj) {
                        continue;
                    }
                }

                $age = calculate_age($staff->date_of_birth);
                $full_name = trim(($staff->lname ?? '') . ' ' . ($staff->fname ?? '') . ' ' . (@$staff->oname ?? ''));
                
                if (empty($full_name)) {
                    continue; // Skip if no name
                }
                
                $birthday_date = $birthday_this_year->format('Y-m-d');
                
                $events[] = [
                    'id' => 'birthday_' . $staff->staff_id,
                    'title' => $full_name . ' (' . $age . ' years)',
                    'start' => $birthday_date,
                    'end' => $birthday_date, // End date same as start for all-day events
                    'allDay' => true,
                    'color' => '#119A48',
                    'extendedProps' => [
                        'staff_id' => $staff->staff_id,
                        'age' => $age,
                        'grade' => $staff->grade ?? '',
                        'job_name' => $staff->job_name ?? '',
                        'duty_station' => $staff->duty_station_name ?? '',
                        'division' => $staff->division_name ?? '',
                        'photo' => $staff->photo ?? '',
                        'title' => $staff->title ?? ''
                    ]
                ];
            } catch (Exception $e) {
                log_message('error', 'Error parsing date of birth for staff_id ' . $staff->staff_id . ': ' . $e->getMessage());
                continue; // Skip this staff member if date is invalid
            }
        }
        
        log_message('debug', 'Birthday events generated: ' . count($events) . ' events');

        return $events;
    }


}