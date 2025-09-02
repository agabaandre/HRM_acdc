<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tasks_mdl extends CI_Model {

    // Add Activity
    public function add_activity($data) {
        $log_message = "Added and linked tasks to the work plan";
		log_user_action($log_message);
        return $this->db->insert('work_planner_tasks', $data);
    }

    // Get Deliverables
    public function get_deliverables() {
        return $this->db->get('deliverables')->result();
    }

 

    // Submit Report
    public function submit_report($data) {
        $log_message = "Added work a Report on ". $data['activity_id'];
		log_user_action($log_message);
        return $this->db->insert('reports', $data);
    }

    // Get Reports
    public function get_reports($staff_id) {
        $this->db->select('reports.*, work_planner_tasks.activity_name');
        $this->db->from('reports');
        $this->db->join('work_planner_tasks', 'work_planner_tasks.activity_id = reports.activity_id');
        $this->db->where('work_planner_tasks.staff_id', $staff_id);
        return $this->db->get()->result();
    }

    public function get_output($output_id=FALSE,$division_id=FALSE) {
       
        if($division_id){
            $this->db->where('division_id',$division_id);
            }
        if($output_id){
        $this->db->where('workplan_tasks.id', $output_id);
        }
          // Apply filters if provided
       // $this->db->where('workplan_tasks.has_budget',1);
        $this->db->order_by('year', 'DESC');
        $this->db->order_by('created_at','DESC');
       // dd($this->db->last_query());
        return $this->db->get('workplan_tasks')->result();
    }
    public function get_work_planner_tasks($staff_id = null, $output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = 0) {
        $this->db->select('
            work_planner_tasks.activity_id, 
            work_planner_tasks.activity_name, 
            work_planner_tasks.workplan_id, 
            CONCAT(staff.fname, " ", staff.lname) AS `team_lead`,
            created_by AS `staff_id`,
            workplan_tasks.activity_name AS work_activity_name, 
            work_planner_tasks.start_date, 
            work_planner_tasks.end_date, 
            reports.report_id,
            reports.status AS report_status,
            reports.description AS report,
            reports.report_date,
            work_planner_tasks.comments, 
            work_planner_tasks.status,
            DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date) + 1 AS activity_days
        ');
        
        $this->db->from('work_planner_tasks');
    
        // Filters
        if (!empty($output_id)) {
            $this->db->where('work_planner_tasks.workplan_id', $output_id);
        }
    
        if (!empty($start_date)) {
            $this->db->where('work_planner_tasks.start_date >=', $start_date);
        }
    
        if (!empty($end_date)) {
            $this->db->where('work_planner_tasks.end_date <=', $end_date);
        }
    
        if (!empty($staff_id)) {
            $this->db->where('work_planner_tasks.created_by', $staff_id);
        }
    
        // Joins
        $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id', 'left');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id', 'left');
        $this->db->join('staff', 'staff.staff_id = work_planner_tasks.created_by', 'left');
        $this->db->join('reports', 'reports.activity_id = work_planner_tasks.activity_id', 'left');
    
        // Pagination
        if (!is_null($limit)) {
            $this->db->limit($limit, $offset);
        }
    
        $this->db->order_by('work_planner_tasks.end_date', 'ASC');
    
        $query = $this->db->get();
        return $query->result();
    }
    
    public function get_pending_work_planner_tasks($staff_id=null, $output_id = null, $start_date = null, $end_date = null,$limit = null, $offset = null) {
       $division_id = $this->session->userdata('user')->division_id;
       $this->db->select('
       work_planner_tasks.activity_id, 
       work_planner_tasks.activity_name, 
       work_planner_tasks.workplan_id, 
       workplan_tasks.activity_name AS work_activity_name, 
       work_planner_tasks.start_date, 
       work_planner_tasks.end_date, 
       reports.report_id,
       reports.status AS report_status,
       reports.description AS report,
       reports.report_date,
       work_planner_tasks.comments, 
       work_planner_tasks.status,
       DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date) + 1 AS activity_days
   ');
   
    $this->db->where()('status',0);
    $this->db->where('division_id',$division_id);
    $this->db->from('work_planner_tasks');
    
    // Apply filters if provided
    if (!empty($output_id)) {
        $this->db->where('work_planner_tasks.workplan_id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('work_planner_tasks.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('work_planner_tasks.end_date <=', $end_date);
    }
    
    // Join the workplan_tasks table

    $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id');
    $this->db->join('divisions','workplan_tasks.division_id=divisions.division_id' );
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    $this->db->order_by('work_planner_tasks.end_date','ASC');
    
    $query = $this->db->get();
    return $query->result();
    
    }
    public function get_work_planner_tasks_count($output_id = null, $start_date = null, $end_date = null) {
        $this->db->select('COUNT(*) as total');
        $this->db->from('work_planner_tasks');
        
        if (!empty($output_id)) {
            $this->db->where('workplan_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('end_date <=', $end_date);
        }
        
        $query = $this->db->get();
        return $query->row()->total;
    }

    public function get_outputs($output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = null) {
        $this->db->select('*','divisions.division_name');
    $this->db->from('workplan_tasks');
    
  
    $division_id = $this->session->userdata('user')->division_id;
    if (!empty($division_id)) {
        $this->db->where('divisions.division_id', $division_id);
    }
    
    if (!empty($output_id)) {
        $this->db->where('workplan_tasks.id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('workplan_tasks.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('workplan_tasks.end_date <=', $end_date);
    }
    
   
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    $this->db->join('divisions','divisions.division_id=workplan_tasks.division_id');
  
    $query = $this->db->get();

    //dd($this->db->last_query());
    return $query->result();
    
    }
    public function get_reports_full(
        $staff_id = null,
        $output_id = null,
        $start_date = null,
        $end_date = null,
        $activity_name = null,
        $report_status = null,
        $employee_name = null,
        $period = null,
        $quarter = null,
        $limit = null,
        $offset = null
    ) {
        $this->db->select('
            work_planner_tasks.activity_id, 
            work_planner_tasks.activity_name, 
            work_planner_tasks.workplan_id, 
            workplan_tasks.activity_name AS activity_name, 
            work_planner_tasks.start_date, 
            work_planner_tasks.end_date, 
            work_planner_tasks.comments, 
            work_planner_tasks.staff_id, 
            work_planner_tasks.status AS activity_status,
            DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date)+1 AS activity_days,
            reports.report_id,
            reports.report_date,
            reports.week,
            reports.supervisor_comment,
            divisions.division_head as unit_head,
            reports.description AS report_description,
            reports.status AS report_status,
            reports.created_at AS report_created_at,
            reports.updated_at AS report_updated_at,
            
        ');
    
        $this->db->from('work_planner_tasks');
    
        // Join the staff table for employee name filter
        $this->db->join('staff', 'staff.staff_id = work_planner_tasks.staff_id', 'left');
        // Join other related tables
        $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id');
        $this->db->join('divisions', 'workplan_tasks.division_id = divisions.division_id');
        $this->db->join('reports', 'reports.activity_id = work_planner_tasks.activity_id', 'left');
    
        // Access filter based on logged in staff
        if (!empty($staff_id)) {
            $this->db->group_start();
                $this->db->where('work_planner_tasks.staff_id', $staff_id);
                $this->db->or_where('divisions.division_head', $staff_id);
            $this->db->group_end();
        }
    
        // Existing filters
        if (!empty($output_id)) {
            $this->db->where('work_planner_tasks.workplan_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('work_planner_tasks.start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('work_planner_tasks.end_date <=', $end_date);
        }
        if (!empty($activity_name)) {
            $this->db->like('work_planner_tasks.activity_name', $activity_name);
        }
        if (!empty($report_status)) {
            $this->db->where('reports.status', $report_status);
        }
        if (!empty($employee_name)) {
            $this->db->like('staff.name', $employee_name);
        }
        // New filters
     
        // Apply limit and offset for pagination if provided
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }
        $this->db->order_by('work_planner_tasks.end_date','ASC');
        $query = $this->db->get();
        return $query->result();
    }

    // Get team members for a division
    public function get_team_members($division_id) {
        // Subquery: Get latest contract per staff
        $subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        $this->db->select('
            s.staff_id, s.SAPNO, s.title, s.fname, s.lname, s.oname, 
            s.gender, s.date_of_birth, s.work_email, s.tel_1, s.tel_2, 
            s.whatsapp, s.photo, s.private_email, s.physical_location,
            sc.division_id, sc.job_id, j.job_name, sc.job_acting_id, ja.job_acting,
            sc.start_date, sc.end_date, sc.status_id,
            d.division_name, ds.duty_station_name,
            g.grade, st.status, f.funder
        ');

        $this->db->from('staff s');
        $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
        $this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
        $this->db->join('funders f', 'f.funder_id = sc.funder_id', 'left');
        $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
        $this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
        $this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
        $this->db->join('jobs_acting ja', 'ja.job_acting_id = sc.job_acting_id', 'left');
        $this->db->join('status st', 'st.status_id = sc.status_id', 'left');

        // Where latest contract only
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);

        // Active contracts only
        $this->db->where_in('sc.status_id', [1, 2, 3, 7]);

        // Filter by division
        if (!empty($division_id)) {
            $this->db->where('sc.division_id', $division_id);
        }

        // Order by name
        $this->db->order_by('s.fname', 'ASC');

        return $this->db->get()->result();
    }

    // Get work plans for a division
    public function get_work_plans($division_id) {
        return $this->db
            ->select('id, activity_name, division_id')
            ->from('workplan_tasks')
            ->where('division_id', $division_id)
            ->order_by('activity_name', 'ASC')
            ->get()
            ->result();
    }

    // Get filtered activities with server-side pagination
    public function get_activities_filtered_paginated($division_id, $start_date = null, $end_date = null, $team_members = null, $work_plan = null, $start = 0, $length = 10, $search_value = '', $order_column = 0, $order_dir = 'asc') {
        // Subquery: Get latest contract per staff
        $subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        // Column mapping for ordering
        $columns = [
            'wpt.activity_name',
            'CONCAT(s.fname, " ", s.lname)',
            'wpt.start_date',
            'wpt.end_date',
            'wpt.status',
            'wpt.comments'
        ];

        // Base query
        $this->db
            ->select('
                wpt.activity_id,
                wpt.activity_name,
                wpt.start_date,
                wpt.end_date,
                wpt.status,
                wpt.comments,
                s.fname,
                s.lname,
                s.title,
                j.job_name,
                CONCAT(s.fname, " ", s.lname) as member_name,
                wt.activity_name as work_plan_name,
                r.report_id,
                r.description as report,
                r.report_date,
                r.status as report_status,
                d.division_name
            ')
            ->from('work_planner_tasks wpt')
            ->join('staff s', 's.staff_id = wpt.created_by', 'left')
            ->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left')
            ->join('jobs j', 'j.job_id = sc.job_id', 'left')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id', 'left')
            ->join('reports r', 'r.activity_id = wpt.activity_id', 'left')
            ->join('divisions d', 'd.division_id = sc.division_id', 'left')
            ->where("sc.staff_contract_id IN ($subquery)", null, false)
            ->where_in('sc.status_id', [1, 2, 3, 7])
            ->where('sc.division_id', $division_id);

        // Apply filters
        if (!empty($start_date)) {
            $this->db->where('wpt.start_date >=', $start_date);
        }
        
        if (!empty($end_date)) {
            $this->db->where('wpt.end_date <=', $end_date);
        }
        
        if (!empty($team_members) && is_array($team_members)) {
            $this->db->where_in('wpt.created_by', $team_members);
        }
        
        if (!empty($work_plan)) {
            $this->db->where('wpt.workplan_id', $work_plan);
        }

        // Global search
        if (!empty($search_value)) {
            $this->db->group_start();
            $this->db->like('wpt.activity_name', $search_value);
            $this->db->or_like('s.fname', $search_value);
            $this->db->or_like('s.lname', $search_value);
            $this->db->or_like('wt.activity_name', $search_value);
            $this->db->or_like('j.job_name', $search_value);
            $this->db->or_like('d.division_name', $search_value);
            $this->db->group_end();
        }

        // Get total records count (before pagination)
        $total_records = $this->db->count_all_results('', false);

        // Apply ordering
        if (isset($columns[$order_column])) {
            $this->db->order_by($columns[$order_column], $order_dir);
        } else {
            $this->db->order_by('wpt.start_date', 'DESC');
        }

        // Apply pagination
        if ($length > 0) {
            $this->db->limit($length, $start);
        }

        $data = $this->db->get()->result();

        return [
            'data' => $data,
            'total_records' => $total_records,
            'filtered_records' => $total_records // Same as total since we're not doing separate filtered count
        ];
    }

    // Get filtered activities (legacy method for backward compatibility)
    public function get_activities_filtered($division_id, $start_date = null, $end_date = null, $team_members = null, $work_plan = null) {
        $result = $this->get_activities_filtered_paginated($division_id, $start_date, $end_date, $team_members, $work_plan, 0, 0);
        return $result['data'];
    }

    // Get activity with all details for individual report
    public function get_activity_with_details($activity_id) {
        // Subquery: Get latest contract per staff
        $subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        return $this->db
            ->select('
                wpt.activity_id,
                wpt.activity_name,
                wpt.start_date,
                wpt.end_date,
                wpt.status,
                wpt.comments,
                wpt.workplan_id,
                wpt.created_by,
                s.fname,
                s.lname,
                s.title,
                s.work_email,
                j.job_name,
                CONCAT(s.fname, " ", s.lname) as member_name,
                wt.activity_name as work_plan_name,
                d.division_name
            ')
            ->from('work_planner_tasks wpt')
            ->join('staff s', 's.staff_id = wpt.created_by', 'left')
            ->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left')
            ->join('jobs j', 'j.job_id = sc.job_id', 'left')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id', 'left')
            ->join('divisions d', 'd.division_id = sc.division_id', 'left')
            ->where("sc.staff_contract_id IN ($subquery)", null, false)
            ->where_in('sc.status_id', [1, 2, 3, 7])
            ->where('wpt.activity_id', $activity_id)
            ->get()
            ->row();
    }

    // Get activity report details
    public function get_activity_report($activity_id) {
        return $this->db
            ->select('
                r.report_id,
                r.description,
                r.report_date,
                r.status as report_status,
                r.created_at
            ')
            ->from('reports r')
            ->where('r.activity_id', $activity_id)
            ->get()
            ->row();
    }

    // Get team performance data
    public function get_team_performance_data($division_id, $start_date = null, $end_date = null, $team_members = null, $work_plan = null) {
        // Subquery: Get latest contract per staff
        $subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        $this->db
            ->select('
                s.staff_id,
                s.fname,
                s.lname,
                s.title,
                j.job_name,
                COUNT(wpt.activity_id) as total_activities,
                SUM(CASE WHEN wpt.status = 2 THEN 1 ELSE 0 END) as completed_activities,
                SUM(CASE WHEN wpt.status = 1 THEN 1 ELSE 0 END) as pending_activities,
                SUM(CASE WHEN wpt.status = 3 THEN 1 ELSE 0 END) as carried_forward_activities,
                SUM(CASE WHEN wpt.end_date < CURDATE() AND wpt.status = 1 THEN 1 ELSE 0 END) as overdue_activities
            ')
            ->from('staff s')
            ->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left')
            ->join('jobs j', 'j.job_id = sc.job_id', 'left')
            ->join('work_planner_tasks wpt', 'wpt.created_by = s.staff_id', 'left')
            ->where("sc.staff_contract_id IN ($subquery)", null, false)
            ->where_in('sc.status_id', [1, 2, 3, 7])
            ->where('sc.division_id', $division_id);

        // Apply filters
        if (!empty($start_date)) {
            $this->db->where('wpt.start_date >=', $start_date);
        }
        
        if (!empty($end_date)) {
            $this->db->where('wpt.end_date <=', $end_date);
        }
        
        if (!empty($team_members) && is_array($team_members)) {
            $this->db->where_in('s.staff_id', $team_members);
        }
        
        if (!empty($work_plan)) {
            $this->db->where('wpt.workplan_id', $work_plan);
        }

        return $this->db
            ->group_by('s.staff_id')
            ->order_by('s.fname', 'ASC')
            ->get()
            ->result();
    }

    // Get activity statistics
    public function get_activity_statistics($division_id, $start_date = null, $end_date = null, $team_members = null, $work_plan = null) {
        // Subquery: Get latest contract per staff
        $subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->group_by('staff_id')
            ->get_compiled_select();

        $this->db
            ->select('
                COUNT(wpt.activity_id) as total,
                SUM(CASE WHEN wpt.status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN wpt.status = 1 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN wpt.end_date < CURDATE() AND wpt.status = 1 THEN 1 ELSE 0 END) as overdue
            ')
            ->from('work_planner_tasks wpt')
            ->join('staff s', 's.staff_id = wpt.created_by', 'left')
            ->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left')
            ->where("sc.staff_contract_id IN ($subquery)", null, false)
            ->where_in('sc.status_id', [1, 2, 3, 7])
            ->where('sc.division_id', $division_id);

        // Apply filters
        if (!empty($start_date)) {
            $this->db->where('wpt.start_date >=', $start_date);
        }
        
        if (!empty($end_date)) {
            $this->db->where('wpt.end_date <=', $end_date);
        }
        
        if (!empty($team_members) && is_array($team_members)) {
            $this->db->where_in('wpt.created_by', $team_members);
        }
        
        if (!empty($work_plan)) {
            $this->db->where('wpt.workplan_id', $work_plan);
        }

        $result = $this->db->get()->row();
        
        return [
            'total' => (int)$result->total,
            'completed' => (int)$result->completed,
            'pending' => (int)$result->pending,
            'overdue' => (int)$result->overdue
        ];
    }
    
    
    
}