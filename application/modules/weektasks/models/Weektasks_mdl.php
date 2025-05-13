<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Weektasks_mdl extends CI_Model {

    public function insert_task($data) {
        $log_message = "Added weekly activities";
		log_user_action($log_message);
        return $this->db->insert('work_plan_weekly_tasks', $data);
    }

    public function update_task($id, $data) {
        $log_message = "Updated weekly task ". $id;
		log_user_action($log_message);
        return $this->db
            ->where('activity_id', $id)
            ->update('work_plan_weekly_tasks', $data);
    }

    public function get_by_id($id) {
        return $this->db
            ->where('activity_id', $id)
            ->get('work_plan_weekly_tasks')
            ->row();
    }
    public function fetch_tasks($filters = [], $start = 0, $length = 10, $search = '') {
        $logged_in_user_id = $this->session->userdata('user')->staff_id;
    
        // CASE expressions for prioritization
        $priority_case = "
            CASE
                WHEN FIND_IN_SET(?, work_plan_weekly_tasks.staff_id) > 0 THEN 1
                ELSE 0
            END AS user_priority
        ";
    
        $status_case = "
            CASE
                WHEN work_plan_weekly_tasks.status = 1 THEN 1
                ELSE 0
            END AS status_priority
        ";
    
        $this->db->select("
            work_plan_weekly_tasks.*, 
            work_planner_tasks.activity_name AS work_activity_name,
            reports.status AS report_status,
            reports.description AS report,
            reports.report_date,
            divisions.division_name,
            $priority_case,
            $status_case
        ", false); // disable escaping to keep CASE expressions
    
        $this->db->from('work_plan_weekly_tasks');
        $this->db->join('work_planner_tasks', 'work_plan_weekly_tasks.work_planner_tasks_id = work_planner_tasks.activity_id', 'left');
        $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id', 'left');
        $this->db->join('divisions', 'workplan_tasks.division_id = divisions.division_id', 'left');
        $this->db->join('reports', 'work_plan_weekly_tasks.activity_id = reports.activity_id', 'left');
    
        // Filter by multiple staff_id
        if (!empty($filters['staff_id']) && is_array($filters['staff_id'])) {
            $this->db->group_start();
            foreach ($filters['staff_id'] as $sid) {
                $this->db->or_where("FIND_IN_SET(" . $this->db->escape($sid) . ", work_plan_weekly_tasks.staff_id) >", 0);
            }
            $this->db->group_end();
        }
    
        if (!empty($filters['output'])) {
            $this->db->where('work_plan_weekly_tasks.work_planner_tasks_id', $filters['output']);
        }
    
        if (!empty($filters['start_date'])) {
            $this->db->where('work_plan_weekly_tasks.start_date >=', $filters['start_date']);
        }
    
        if (!empty($filters['end_date'])) {
            $this->db->where('work_plan_weekly_tasks.end_date <=', $filters['end_date']);
        }
    
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $this->db->where('work_plan_weekly_tasks.status', $filters['status']);
        }
    
        if (!empty($filters['teamlead']) && $filters['teamlead'] !== 'all') {
            $this->db->where('work_planner_tasks.created_by', $filters['teamlead']);
        }
    
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('work_plan_weekly_tasks.activity_name', $search);
            $this->db->or_like('work_planner_tasks.activity_name', $search);
            $this->db->or_like('work_plan_weekly_tasks.comments', $search);
            $this->db->group_end();
        }
    
        // Prioritize pending tasks first, then user-assigned tasks, then recent
        $this->db->order_by('status_priority', 'DESC');
        $this->db->order_by('user_priority', 'DESC');
        $this->db->order_by('work_plan_weekly_tasks.created_at', 'DESC');
    
        $this->db->limit($length, $start);
    
        $query = $this->db->get_compiled_select();
        return $this->db->query($query, [$logged_in_user_id])->result();
    }
    
    
    
    
    public function count_tasks($filters = [], $search = '') {
        $this->db->from('work_plan_weekly_tasks');
        $this->db->join('work_planner_tasks', 'work_plan_weekly_tasks.work_planner_tasks_id = work_planner_tasks.activity_id', 'left');
    
        // Handle multiple staff IDs
        if (!empty($filters['staff_id']) && is_array($filters['staff_id'])) {
            $this->db->group_start();
            foreach ($filters['staff_id'] as $staff_id) {
                $this->db->or_where("FIND_IN_SET(" . $this->db->escape($staff_id) . ", work_plan_weekly_tasks.staff_id) >", 0);
            }
            $this->db->group_end();
        }
    
        if (!empty($filters['output'])) {
            $this->db->where('work_plan_weekly_tasks.work_planner_tasks_id', $filters['output']);
        }
    
        if (!empty($filters['start_date'])) {
            $this->db->where('work_plan_weekly_tasks.start_date >=', $filters['start_date']);
        }
    
        if (!empty($filters['end_date'])) {
            $this->db->where('work_plan_weekly_tasks.end_date <=', $filters['end_date']);
        }
        if (!empty($filters['status']) && ($filters['status']!='all')) {
            $this->db->where('work_plan_weekly_tasks.status', $filters['status']);
        }

        if (!empty($filters['teamlead']) && ($filters['teamlead']!='all')) {
            $this->db->where('work_planner_tasks.created_by', $filters['teamlead']);
        }
    
        if (!empty($search)) {
            $this->db->group_start()
                ->like('work_plan_weekly_tasks.activity_name', $search)
                ->or_like('work_planner_tasks.activity_name', $search)
                ->or_like('work_plan_weekly_tasks.comments', $search)
                ->group_end();
        }
    
        return $this->db->count_all_results();
    }
    
    public function get_tasks_by_staff_and_week($staff_id, $week_start_date) {
        $week_end_date = date('Y-m-d', strtotime($week_start_date . ' +4 days'));

        return $this->db
            ->select('w.*, p.activity_name AS parent_activity, divisions.division_name')
            ->from('work_plan_weekly_tasks w')
            ->join('work_planner_tasks p', 'p.activity_id = w.work_planner_tasks_id', 'left')
            ->join('workplan_tasks', 'workplan_tasks.id=p.workplan_id','left')
            ->join('divisions', 'workplan_tasks.division_id=divisions.division_id')
            ->where("FIND_IN_SET('$staff_id', w.staff_id) >", 0)
            ->where('w.start_date >=', $week_start_date)
            ->where('w.end_date <=', $week_end_date)
            ->order_by('w.start_date', 'ASC')
            ->get()
            ->result();
    }

    public function get_staff($staff_id) {
        return $this->db
            ->where('staff_id', $staff_id)
            ->get('staff')
            ->row();
    }
    public function get_tasks_for_calendar($staff_id)
    {
        return $this->db
            ->select('activity_name, start_date, end_date, status')
            ->from('work_plan_weekly_tasks')
            ->where("FIND_IN_SET($staff_id, staff_id) >", 0)
            ->order_by('start_date', 'ASC')
            ->get()
            ->result();
    }
    public function get_tasks_by_staff_and_range($staff_id, $start_date, $end_date, $teamlead, $status=null) {
        // Subquery to get the latest contract ID for the staff
    
        $latest_contract_subquery = $this->db
            ->select('MAX(staff_contract_id)', false)
            ->from('staff_contracts')
            ->where('staff_id', $staff_id)
            ->get_compiled_select();
    
        $this->db
            ->select('
                w.*, 
                p.activity_name AS parent_activity,
                jobs.job_name,
                divisions.division_name
            ')
            ->from('work_plan_weekly_tasks w')
            ->join('work_planner_tasks p', 'p.activity_id = w.work_planner_tasks_id', 'left')
            ->join('workplan_tasks', 'workplan_tasks.id = p.workplan_id', 'left')
            ->join("(
                SELECT * FROM staff_contracts 
                WHERE staff_contract_id = ($latest_contract_subquery)
            ) sc", "FIND_IN_SET('$staff_id', w.staff_id) > 0", 'left')
            ->join('jobs', 'jobs.job_id = sc.job_id', 'left')
            ->join('divisions', 'divisions.division_id = sc.division_id', 'left')
            ->where("FIND_IN_SET('$staff_id', w.staff_id) >", 0)
            ->where('w.start_date <=', $start_date)
            ->where('w.end_date <=', $end_date);
    
        if (!empty($status) && ($status!='all')) {
            $this->db->where('w.status', $status);
        }
        if (!empty($teamlead) && ($teamlead!='all')) {
            $this->db->where('p.created_by', $teamlead);
        }
        return $this->db
            ->order_by('w.start_date', 'ASC')
            ->get()
            ->result();
    }
    
    
    
    public function get_combined_tasks_for_division($division_id, $start_date, $end_date, $teamlead, $status = null)
{
    $this->db
        ->select('
            w.activity_name, 
            w.start_date, 
            w.end_date, 
            w.comments, 
            w.status, 
            p.activity_name AS parent_activity, 
            wp.activity_name AS workplan_activity, 
            d.division_name
        ')
        ->from('work_plan_weekly_tasks w')
        ->join('work_planner_tasks p', 'p.activity_id = w.work_planner_tasks_id', 'left')
        ->join('workplan_tasks wp', 'wp.id = p.workplan_id', 'left')
        ->join('divisions d', 'd.division_id = wp.division_id', 'left')
        ->where('wp.division_id', $division_id)
        ->where('w.start_date >=', $start_date)
        ->where('w.end_date <=', $end_date);

        if (!empty($status) && ($status!='all')) {
            $this->db->where('w.status', $status);
        }
        if (!empty($teamlead) && ($teamlead!='all')) {
            $this->db->where('p.created_by', $teamlead);
        }

    return $this->db
        ->group_by('w.activity_name, w.start_date, w.end_date') // ensures uniqueness
        ->order_by('w.start_date', 'ASC')
        ->get()
        ->result();
}

    

}
