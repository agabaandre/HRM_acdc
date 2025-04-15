<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Weektasks_mdl extends CI_Model {

    public function get_sub_activities() {
        $division_id = $this->session->userdata('user')->division_id;
        return $this->db
            ->select('wpt.activity_id, wpt.activity_name')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->where('wt.division_id', $division_id)
            ->get()
            ->result();
    }

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
        $this->db->select('
            work_plan_weekly_tasks.*, 
            work_planner_tasks.activity_name AS work_activity_name,
            reports.status AS report_status,
            reports.description AS report,
            reports.report_date
        ');
        $this->db->from('work_plan_weekly_tasks');
        $this->db->join('work_planner_tasks', 'work_plan_weekly_tasks.work_planner_tasks_id = work_planner_tasks.activity_id', 'left');
        $this->db->join('reports', 'work_plan_weekly_tasks.activity_id = reports.activity_id', 'left');

        // Filters
        if (!empty($filters['staff_id'])) {
            $staff_id = $filters['staff_id'];
            $this->db->where("FIND_IN_SET(" . $this->db->escape_str($staff_id) . ", work_plan_weekly_tasks.staff_id) >", 0);
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

        if (!empty($search)) {
            $this->db->group_start()
                ->like('work_plan_weekly_tasks.activity_name', $search)
                ->or_like('work_planner_tasks.activity_name', $search)
                ->or_like('work_plan_weekly_tasks.comments', $search)
                ->group_end();
        }

        $this->db->order_by('work_plan_weekly_tasks.created_at', 'DESC');
        $this->db->limit($length, $start);
        return $this->db->get()->result();
    }

    public function count_tasks($filters = [], $search = '') {
        $this->db->from('work_plan_weekly_tasks');
        $this->db->join('work_planner_tasks', 'work_plan_weekly_tasks.work_planner_tasks_id = work_planner_tasks.activity_id', 'left');

        // Filters
        if (!empty($filters['staff_id'])) {
            $staff_id = $filters['staff_id'];
            $this->db->where("FIND_IN_SET(" . $this->db->escape_str($staff_id) . ", work_plan_weekly_tasks.staff_id) >", 0);
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
            ->select('w.*, p.activity_name AS parent_activity')
            ->from('work_plan_weekly_tasks w')
            ->join('work_planner_tasks p', 'p.activity_id = w.work_planner_tasks_id', 'left')
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

    public function get_tasks_by_staff_and_range($staff_id, $start_date, $end_date) {
        return $this->db
            ->select('w.*, p.activity_name AS parent_activity')
            ->from('work_plan_weekly_tasks w')
            ->join('work_planner_tasks p', 'p.activity_id = w.work_planner_tasks_id', 'left')
            ->where("FIND_IN_SET('$staff_id', w.staff_id) >", 0) // match staff in comma list
            ->where('w.start_date >=', $start_date)
            ->where('w.end_date <=', $end_date)
            ->order_by('w.start_date', 'ASC')
            ->get()
            ->result();
    }
    
    
    
}
