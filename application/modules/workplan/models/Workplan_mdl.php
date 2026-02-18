<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Workplan_mdl extends CI_Model {

    /**
     * @param int|null $division_id Division ID, or null for all divisions
     */
    public function get_activities($division_id, $year) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
        if ($division_id !== null && $division_id !== '') {
            $this->db->where('workplan_tasks.division_id', $division_id);
        }
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

    /**
     * @param int|null $division_id Division ID, or null for all divisions
     */
    public function search($query, $division_id, $year = null) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');

        $this->db->group_start();
            $this->db->like('activity_name', $query);
            $this->db->or_like('output_indicator', $query);
            $this->db->or_like('broad_activity', $query);
            $this->db->or_like('intermediate_outcome', $query);
            $this->db->or_like('year', $query);
        $this->db->group_end();

        if ($division_id !== null && $division_id !== '') {
            $this->db->where('workplan_tasks.division_id', $division_id);
        }
        if ($year) {
            $this->db->where('workplan_tasks.year', $year);
        }

        $this->db->order_by('workplan_tasks.created_at', 'DESC');

        return $this->db->get()->result();
    }

    /**
     * Get workplan statistics.
     * @param int|null $division_id Division ID, or null for all divisions
     */
    public function get_workplan_statistics($division_id, $year) {
        $year = (string) (int) $year; // ensure consistent type for DB comparison
        $div_where = ($division_id !== null && $division_id !== '');
        $this->db->from('workplan_tasks');
        $this->db->where('workplan_tasks.year', $year);
        if ($div_where) {
            $this->db->where('division_id', $division_id);
        }
        $total = $this->db->count_all_results();

        $sub_activities_created = $this->db
            ->select('COALESCE(COUNT(wpt.activity_id), 0) as created')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->where('wt.year', $year);
        if ($div_where) {
            $sub_activities_created->where('wt.division_id', $division_id);
        }
        $sub_activities_created = $sub_activities_created->get()->row()->created ?? 0;

        $this->db->select('COALESCE(COUNT(DISTINCT wpt.activity_id), 0) as completed')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.year', $year)
            ->where('wwt.status', 2);
        if ($div_where) {
            $this->db->where('wt.division_id', $division_id);
        }
        $sub_activities_completed = (int)($this->db->get()->row()->completed ?? 0);

        $this->db->select('COALESCE(COUNT(DISTINCT wpt.activity_id), 0) as in_progress')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.year', $year)
            ->where('wwt.status', 1);
        if ($div_where) {
            $this->db->where('wt.division_id', $division_id);
        }
        $sub_activities_in_progress = (int)($this->db->get()->row()->in_progress ?? 0);

        $this->db->select('COALESCE(COUNT(DISTINCT wpt.activity_id), 0) as overdue')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id')
            ->where('wt.year', $year)
            ->where('wwt.end_date <', date('Y-m-d'))
            ->where('wwt.status !=', 2);
        if ($div_where) {
            $this->db->where('wt.division_id', $division_id);
        }
        $overdue = (int)($this->db->get()->row()->overdue ?? 0);

        $sub_activities_created = (int)$sub_activities_created;
        $sub_activities_completed = (int)$sub_activities_completed;
        $sub_activities_in_progress = (int)$sub_activities_in_progress;
        $overdue = (int)$overdue;

        $execution_rate = $sub_activities_created > 0 ?
            round(($sub_activities_completed / $sub_activities_created) * 100, 1) : 0.0;

        $this->db->select('SUM(COALESCE(CAST(cumulative_target AS UNSIGNED), 0)) as total_target');
        $this->db->from('workplan_tasks');
        $this->db->where('year', $year);
        if ($div_where) {
            $this->db->where('division_id', $division_id);
        }
        $total_target = (int)($this->db->get()->row()->total_target ?? 0);

        $total_target = (int)$total_target;
        $target_achievement = $total_target > 0 ? 
            round(($sub_activities_completed / $total_target) * 100, 1) : 0.0;

        // Debug: Log the statistics values
        log_message('debug', 'Statistics calculation: created=' . $sub_activities_created . ', completed=' . $sub_activities_completed . ', execution_rate=' . $execution_rate . ', target_achievement=' . $target_achievement);

        return [
            'total' => (int)$total,
            'completed' => $sub_activities_completed,
            'in_progress' => $sub_activities_in_progress,
            'overdue' => $overdue,
            'execution_rate' => $execution_rate,
            'target_achievement' => $target_achievement
        ];
    }

    /**
     * Get execution tracking data.
     * @param int|null $division_id Division ID, or null for all divisions
     */
    public function get_execution_tracking_data($division_id, $year) {
        $this->db
            ->select('
                wt.id,
                wt.activity_name,
                COALESCE(wt.cumulative_target, 0) as cumulative_target,
                COALESCE(COUNT(DISTINCT wpt.activity_id), 0) as created,
                COALESCE(COUNT(DISTINCT CASE WHEN wwt.status = 2 THEN wpt.activity_id END), 0) as completed
            ')
            ->from('workplan_tasks wt')
            ->join('work_planner_tasks wpt', 'wpt.workplan_id = wt.id', 'left')
            ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id', 'left')
            ->where('wt.year', $year);
        if ($division_id !== null && $division_id !== '') {
            $this->db->where('wt.division_id', $division_id);
        }
        $this->db
            ->group_by('wt.id, wt.activity_name, wt.cumulative_target')
            ->order_by('wt.activity_name', 'ASC');

        $result = $this->db->get()->result();
        
        // Debug: Log the query and result
        log_message('debug', 'Execution tracking query: ' . $this->db->last_query());
        log_message('debug', 'Execution tracking result count: ' . count($result));
        
        // Process results to ensure proper data types
        foreach ($result as $row) {
            $row->created = (int)$row->created;
            $row->completed = (int)$row->completed;
            $row->cumulative_target = (int)$row->cumulative_target;
        }
        
        return $result;
    }

    /**
     * Get unit score breakdown.
     * @param int|null $division_id Division ID, or null for all divisions
     */
    public function get_unit_score_breakdown($division_id, $year) {
        $unit_leads_q = $this->db
            ->select('
                u.unit_id,
                u.unit_name,
                s.staff_id,
                s.fname,
                s.lname,
                s.title
            ')
            ->from('units u')
            ->join('staff s', 's.staff_id = u.staff_id');
        if ($division_id !== null && $division_id !== '') {
            $unit_leads_q->where('u.division_id', $division_id);
        }
        $unit_leads = $unit_leads_q->get()->result();

        $unit_scores = [];

        foreach ($unit_leads as $unit_lead) {
            $act_q = $this->db
                ->select('
                    wt.activity_name,
                    COALESCE(wt.cumulative_target, 0) as cumulative_target,
                    COALESCE(COUNT(DISTINCT wpt.activity_id), 0) as sub_activities_created,
                    COALESCE(COUNT(DISTINCT CASE WHEN wwt.status = 2 THEN wpt.activity_id END), 0) as sub_activities_completed
                ')
                ->from('workplan_tasks wt')
                ->join('work_planner_tasks wpt', 'wpt.workplan_id = wt.id', 'left')
                ->join('work_plan_weekly_tasks wwt', 'wwt.work_planner_tasks_id = wpt.activity_id', 'left')
                ->where('wt.year', $year)
                ->where('wpt.created_by', $unit_lead->staff_id)
                ->group_by('wt.id, wt.activity_name, wt.cumulative_target');
            if ($division_id !== null && $division_id !== '') {
                $act_q->where('wt.division_id', $division_id);
            }
            $activities = $act_q->get()->result();

            $total_target = 0;
            $total_created = 0;
            $total_completed = 0;

            foreach ($activities as $activity) {
                $total_target += (int)($activity->cumulative_target ?? 0);
                $total_created += (int)($activity->sub_activities_created ?? 0);
                $total_completed += (int)($activity->sub_activities_completed ?? 0);
            }

            $execution_rate = $total_created > 0 ? 
                round(($total_completed / $total_created) * 100, 1) : 0.0;

            $target_achievement = $total_target > 0 ? 
                round(($total_completed / $total_target) * 100, 1) : 0.0;

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

        // Debug: Log the unit scores data
        log_message('debug', 'Unit scores data: ' . json_encode($unit_scores));

        return $unit_scores;
    }
    
}
