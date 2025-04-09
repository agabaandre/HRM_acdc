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
        $sql1 = "SELECT s.staff_id FROM  staff s,staff_contracts sc WHERE s.staff_id=sc.staff_id AND sc.status_id IN(1,2)";

        return $result1 = $this->db->query($sql1)->num_rows();

    }
    function staff_renewal()
    {
        //self::update_contract_status();
        $sql1 = "SELECT s.staff_id FROM  staff s,staff_contracts sc WHERE s.staff_id=sc.staff_id AND sc.status_id IN(7)";

        return $result1 = $this->db->query($sql1)->num_rows();

    }
    public function due_contracts()
    {
        $sql3 = "SELECT staff_id AS due FROM  staff_contracts WHERE status_id=2 ";
        $result3 = $this->db->query($sql3);
        return $row3 = $result3->num_rows();

    }

    function expired_contracts()
    {
        $sql5 = "SELECT staff_id AS exp FROM  staff_contracts WHERE status_id=3 ";

        return $result5 = $this->db->query($sql5)->num_rows();

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


}