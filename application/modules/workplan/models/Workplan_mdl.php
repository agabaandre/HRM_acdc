<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Workplan_mdl extends CI_Model {

    public function get_activities($division_id, $year) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
        $this->db->where('workplan_tasks.division_id', $division_id);
        $this->db->where('workplan_tasks.year', $year);
        $this->db->order_by('workplan_tasks.created_at', 'DESC');
        
        return $this->db->get()->result();
    }
    

    public function insert($data) {
        $this->db->insert('workplan_tasks', $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $log_message = "Workplan task with id ".$id. ' Updated';
		log_user_action($log_message);
        return $this->db->where('id', $id)->update('workplan_tasks', $data);
    }

    public function delete($id) {
        $log_message = "Workplan task with id ".$id. ' Deleted';
		log_user_action($log_message);
        return $this->db->where('id', $id)->delete('workplan_tasks');
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get('workplan_tasks')->row();
    }

    public function search($query, $division_id, $year = null) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
    
        $this->db->group_start(); // ( ... )
            $this->db->like('activity_name', $query);
            $this->db->or_like('output_indicator', $query);
            $this->db->or_like('broad_activity', $query);
            $this->db->or_like('intermediate_outcome', $query);
            $this->db->or_like('year', $query);
        $this->db->group_end();
    
        $this->db->where('workplan_tasks.division_id', $division_id);
    
        if ($year) {
            $this->db->where('workplan_tasks.year', $year);
        }
    
        $this->db->order_by('workplan_tasks.created_at', 'DESC');
    
        return $this->db->get()->result();
    }

    // Get workplan statistics
    public function get_workplan_statistics($division_id, $year) {
        // Get total activities
        $total = $this->db
            ->where('division_id', $division_id)
            ->where('year', $year)
            ->count_all_results('workplan_tasks');

        // Get sub-activities created (from work_planner_tasks)
        $sub_activities_created = $this->db
            ->select('COUNT(wpt.activity_id) as created')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->where('wt.division_id', $division_id)
            ->where('wt.year', $year)
            ->get()
            ->row()->created ?? 0;

        // Get completed sub-activities (from weekly tasks with status = 2)
        $sub_activities_completed = $this->db
            ->select('COUNT(DISTINCT wpt.activity_id) as completed')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.division_id', $division_id)
            ->where('wt.year', $year)
            ->where('wwt.status', 2) // Completed
            ->get()
            ->row()->completed ?? 0;

        // Get in-progress sub-activities (from weekly tasks with status = 1)
        $sub_activities_in_progress = $this->db
            ->select('COUNT(DISTINCT wpt.activity_id) as in_progress')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.division_id', $division_id)
            ->where('wt.year', $year)
            ->where('wwt.status', 1) // Pending/In Progress
            ->get()
            ->row()->in_progress ?? 0;

        // Get overdue activities (end date < today and status != 2)
        $overdue = $this->db
            ->select('COUNT(DISTINCT wpt.activity_id) as overdue')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.division_id', $division_id)
            ->where('wt.year', $year)
            ->where('wwt.end_date <', date('Y-m-d'))
            ->where('wwt.status !=', 2) // Not completed
            ->get()
            ->row()->overdue ?? 0;

        // Calculate execution rate
        $execution_rate = $sub_activities_created > 0 ? 
            round(($sub_activities_completed / $sub_activities_created) * 100, 1) : 0;

        // Calculate target achievement (based on cumulative targets)
        $total_target = $this->db
            ->select('SUM(COALESCE(CAST(cumulative_target AS UNSIGNED), 0)) as total_target')
            ->from('workplan_tasks')
            ->where('division_id', $division_id)
            ->where('year', $year)
            ->get()
            ->row()->total_target ?? 0;

        $target_achievement = $total_target > 0 ? 
            round(($sub_activities_completed / $total_target) * 100, 1) : 0;

        return [
            'total' => $total,
            'completed' => $sub_activities_completed,
            'in_progress' => $sub_activities_in_progress,
            'overdue' => $overdue,
            'execution_rate' => $execution_rate,
            'target_achievement' => $target_achievement
        ];
    }

    // Get execution tracking data
    public function get_execution_tracking_data($division_id, $year) {
        $this->db
            ->select('
                wt.id,
                wt.activity_name,
                COALESCE(wt.cumulative_target, 0) as cumulative_target,
                COUNT(DISTINCT wpt.activity_id) as created,
                COUNT(DISTINCT CASE WHEN wwt.status = 2 THEN wpt.activity_id END) as completed
            ')
            ->from('workplan_tasks wt')
            ->join('work_planner_tasks wpt', 'wpt.workplan_id = wt.id', 'left')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id', 'left')
            ->where('wt.division_id', $division_id)
            ->where('wt.year', $year)
            ->group_by('wt.id, wt.activity_name, wt.cumulative_target')
            ->order_by('wt.activity_name', 'ASC');

        $result = $this->db->get()->result();
        
        // Debug: Log the query and result
        log_message('debug', 'Execution tracking query: ' . $this->db->last_query());
        log_message('debug', 'Execution tracking result count: ' . count($result));
        
        return $result;
    }

    // Get unit score breakdown
    public function get_unit_score_breakdown($division_id, $year) {
        // Get unit leads for the division
        $unit_leads = $this->db
            ->select('
                u.unit_id,
                u.unit_name,
                s.staff_id,
                s.fname,
                s.lname,
                s.title
            ')
            ->from('units u')
            ->join('staff s', 's.staff_id = u.staff_id')
            ->where('u.division_id', $division_id)
            ->get()
            ->result();

        $unit_scores = [];

        foreach ($unit_leads as $unit_lead) {
            // Get activities for this unit lead
            $activities = $this->db
                ->select('
                    wt.activity_name,
                    COALESCE(wt.cumulative_target, 0) as cumulative_target,
                    COUNT(DISTINCT wpt.activity_id) as sub_activities_created,
                    COUNT(DISTINCT CASE WHEN wwt.status = 2 THEN wpt.activity_id END) as sub_activities_completed
                ')
                ->from('workplan_tasks wt')
                ->join('work_planner_tasks wpt', 'wpt.workplan_id = wt.id', 'left')
                ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id', 'left')
                ->where('wt.division_id', $division_id)
                ->where('wt.year', $year)
                ->where('wpt.created_by', $unit_lead->staff_id)
                ->group_by('wt.id, wt.activity_name, wt.cumulative_target')
                ->get()
                ->result();

            $total_target = 0;
            $total_created = 0;
            $total_completed = 0;

            foreach ($activities as $activity) {
                $total_target += (int)$activity->cumulative_target;
                $total_created += (int)$activity->sub_activities_created;
                $total_completed += (int)$activity->sub_activities_completed;
            }

            $execution_rate = $total_created > 0 ? 
                round(($total_completed / $total_created) * 100, 1) : 0;

            $target_achievement = $total_target > 0 ? 
                round(($total_completed / $total_target) * 100, 1) : 0;

            // Calculate overall score (weighted average)
            $overall_score = ($execution_rate * 0.6) + ($target_achievement * 0.4);

            $unit_scores[] = [
                'unit_id' => $unit_lead->unit_id,
                'unit_name' => $unit_lead->unit_name,
                'unit_lead' => $unit_lead->title . ' ' . $unit_lead->fname . ' ' . $unit_lead->lname,
                'total_activities' => count($activities),
                'total_target' => $total_target,
                'sub_activities_created' => $total_created,
                'sub_activities_completed' => $total_completed,
                'execution_rate' => $execution_rate,
                'target_achievement' => $target_achievement,
                'overall_score' => round($overall_score, 1)
            ];
        }

        // Sort by overall score descending
        usort($unit_scores, function($a, $b) {
            return $b['overall_score'] <=> $a['overall_score'];
        });

        return $unit_scores;
    }
    
}
