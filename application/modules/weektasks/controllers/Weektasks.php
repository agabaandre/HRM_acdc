<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Weektasks extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('weektasks_mdl', 'weektasks_mdl');
        
    }

    public function tasks() {
        $data['title'] = 'Weekly Tasks';
        $data['module'] = 'weektasks';
        $data['team_leads'] = $this->db
        ->select('staff.staff_id, staff.fname, staff.lname')
        ->distinct()
        ->from('staff')
        ->join('units', 'staff.staff_id = units.staff_id')
        ->get()
        ->result();
        $data['divisions'] = $this->db->order_by('division_name','ASC')->get('divisions')->result();
        $division_id = $this->session->userdata('user')->division_id;
        $data['staff_list'] = $this->staff_mdl->get_staff_by_division($division_id);

        render('weekly_tasks', $data);
    }
    public function calendar()
    {
        $data['title'] = "Weekly Task Calendar";
        $data['module'] = 'weektasks';
        render('weekly_calendar', $data);
    }
    
    public function get_sub_activities_by_teamlead()
    {
        $team_lead_id = $this->input->post('team_lead_id');
        $division_id = $this->session->userdata('user')->division_id;
    
        $results = $this->db
            ->select('wpt.activity_id, wpt.activity_name')
            ->from('work_planner_tasks wpt')
            ->join('workplan_tasks wt', 'wt.id = wpt.workplan_id')
            ->where('wt.division_id', $division_id)
            ->where('wpt.created_by', $team_lead_id) // Assuming this column exists
            ->get()
            ->result();
    
        echo json_encode($results);
    }
    
    
    public function fetch() {
        $start  = $this->input->post('start');
        $length = $this->input->post('length');
        $draw   = $this->input->post('draw');
        $search = $this->input->post('search')['value'];
    
        $filters = [
            'division'   => $this->input->post('division'),
            'staff_id'   => $this->input->post('staff_id'),
            'output'     => $this->input->post('output'),
            'start_date' => $this->input->post('start_date'),
            'end_date'   => $this->input->post('end_date'),
            'status'   => $this->input->post('status')
        ];
    
        $total = $this->weektasks_mdl->count_tasks($filters, $search);
        $tasks = $this->weektasks_mdl->fetch_tasks($filters, $start, $length, $search);
    
        foreach ($tasks as &$task) {
            
            $staff_ids = explode(',', $task->staff_id);
            $staff_names = array_map('staff_name', $staff_ids);
            $task->executed_by = '<ul class="mb-0 ps-3"><li>' . implode('</li><li>', $staff_names) . '</li></ul>';
            $task->created_by_name = !empty($task->created_by) ? staff_name($task->created_by) : '—';
            $task->updated_by_name = !empty($task->updated_by) ? staff_name($task->updated_by) : '—';
        }
    
        echo json_encode([
            'draw' => intval($draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $tasks,
            
        ]);
    }
    

    public function save() {


        $created_by = $this->session->userdata('user')->staff_id;
        $staff_ids = implode(',', $this->input->post('staff_ids'));

        $common_data = [
            'staff_id' => $staff_ids,
            'work_planner_tasks_id' => $this->input->post('work_planner_tasks_id'),
            'start_date' => $this->input->post('start_date'),
            'end_date' => $this->input->post('end_date'),
            'week' => $this->get_week_label($this->input->post('start_date')),
            'created_by' => $created_by,
            'updated_by' => $created_by
        ];

        $activity_names = $this->input->post('activity_name');
        $comments = $this->input->post('comments');

        $saved = 0;
        for ($i = 0; $i < count($activity_names); $i++) {
            if (!empty($activity_names[$i])) {
                $data = $common_data;
                $data['activity_name'] = $activity_names[$i];
                $data['comments'] = $comments[$i] ?? '';
                $this->weektasks_mdl->insert_task($data);
                $saved++;
            }
        }

        echo json_encode(['status' => 'success', 'message' => "$saved task(s) saved successfully!"]);
    }

    public function update() {
        $id = $this->input->post('activity_id');
        $task = $this->weektasks_mdl->get_by_id($id);
        $staff_ids = implode(',', $this->input->post('staff_ids'));

        if (!$task || $task->status != 1) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found or not editable']);
            return;
        }

        $updated_by = $this->session->userdata('user')->staff_id;

        $data = [
            'staff_id' => $staff_ids,
            'activity_name' => $this->input->post('activity_name'),
            'comments' => $this->input->post('comments'),
            'status' => $this->input->post('status'),
            'updated_by' => $updated_by
        ];

        $this->weektasks_mdl->update_task($id, $data);

        if ($data['status'] == 3) {
            $original = $this->weektasks_mdl->get_by_id($id);

            $new_start = date('Y-m-d', strtotime($original->start_date . ' +7 days'));
            $new_end = date('Y-m-d', strtotime($original->end_date . ' +7 days'));

            $clone = [
                'staff_id' => $original->staff_id,
                'work_planner_tasks_id' => $original->work_planner_tasks_id,
                'activity_name' => $original->activity_name,
                'start_date' => $new_start,
                'end_date' => $new_end,
                'week' => $this->get_week_label($new_start),
                'comments' => 'Auto-copied from previous week',
                'status' => 1,
                'created_by' => $updated_by,
                'updated_by' => $updated_by
            ];

            $this->weektasks_mdl->insert_task($clone);
        }

        echo json_encode(['status' => 'success', 'message' => 'Task updated successfully!']);
    }

    private function get_week_label($start) {
        $start_fmt = date('M d', strtotime($start));
        $end_fmt = date('M d, Y', strtotime($start . ' +4 days'));
        return "Week: $start_fmt - $end_fmt";
    }

    private function get_week_range($start) {
        return [
            'start' => $start,
            'end' => date('Y-m-d', strtotime($start . ' +4 days'))
        ];
    }

    public function print_staff_report($staff_id, $start_date, $end_date,$status=FALSE) {
        if(empty($staff_id)||($staff_id=='undefined')){
            $staff_id = $this->session->userdata('user')->staff_id;
        }
        $data['module'] = 'weektasks';
        $data['staff'] = $this->weektasks_mdl->get_staff($staff_id);
        $data['week_label'] = $this->get_week_label($start_date, $end_date);
        $data['week_range'] = "$start_date to $end_date";
        $data['tasks'] = $this->weektasks_mdl->get_tasks_by_staff_and_range($staff_id, $start_date, $end_date,$status);
        $log_message = "Printed a weekly task staff report";
		log_user_action($log_message);
        pdf_print_data($data, 'Staff_Weekly_Report.pdf', 'P', 'pdfs/print_staff');
    }
    
    public function print_division_report($division_id, $start_date, $end_date,$status=FALSE) {
        $data['module'] = 'weektasks';
        $staffs = $this->staff_mdl->get_staff_by_division($division_id);
        $data['division_tasks'] = [];
    
        foreach ($staffs as $staff) {
            $tasks = $this->weektasks_mdl->get_tasks_by_staff_and_range($staff->staff_id, $start_date, $end_date,$status);
            if (!empty($tasks)) {
                $data['division_tasks'][$staff->title . ' ' . $staff->fname . ' ' . $staff->lname] = $tasks;
            }
        }
    
        $data['week_label'] = $this->get_week_label($start_date, $end_date);
        $data['week_range'] = "$start_date to $end_date";
        $log_message = "Printed a weekly task division report";
		log_user_action($log_message);
    
        pdf_print_data($data, 'Division_Weekly_Report.pdf', 'L', 'pdfs/division_print');
    }
    


    public function get_staff_events()
{
    $staff_id = $this->session->userdata('user')->staff_id;

    $tasks = $this->weektasks_mdl->get_tasks_for_calendar($staff_id);
    $events = [];

    foreach ($tasks as $task) {
        // Use switch instead of match for PHP 7.x compatibility
        switch ((int)$task->status) {
            case 1:
                $statusColor = '#ffc107'; // Pending - Yellow
                break;
            case 2:
                $statusColor = '#28a745'; // Completed - Green
                break;
            case 3:
                $statusColor = '#007bff'; // Carried Forward - Blue
                break;
            case 4:
                $statusColor = '#dc3545'; // Cancelled - Red
                break;
            default:
                $statusColor = '#6c757d'; // Unknown - Gray
        }

        $events[] = [
            'title' => $task->activity_name,
            'start' => $task->start_date,
            'end'   => date('Y-m-d', strtotime($task->end_date . ' +1 day')), // FullCalendar needs exclusive end
            'color' => $statusColor,
            'allDay' => true,
        ];
    }

    echo json_encode($events);
}

public function print_combined_division_report($division_id, $start_date, $end_date, $status=FALSE)
{
    $this->load->model('weektasks_mdl');

    $tasks = $this->weektasks_mdl->get_combined_tasks_for_division($division_id, $start_date, $end_date, $status);

    $data['module'] = 'weektasks';
    $data['tasks'] = $tasks;
    $data['week_label'] = $this->get_week_label($start_date, $end_date);
    $data['week_range'] = "$start_date to $end_date";

    log_user_action("Printed combined weekly division task report for division ID: $division_id");

    pdf_print_data($data, 'Division_Combined_Weekly_Report.pdf', 'L', 'pdfs/combined_tasks');
}



    
}
