<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class WeeklyTasks extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('WeeklyTasks_mdl', 'mdl');
        
    }

    public function tasks() {
        $data['title'] = 'Weekly Tasks';
        $data['module'] = 'weeklytasks';
        $data['outputs'] = $this->mdl->get_sub_activities();
        $data['divisions'] = $this->db->order_by('division_name','ASC')->get('divisions')->result();

        $division_id = $this->session->userdata('user')->division_id;
        $data['staff_list'] = $this->staff_mdl->get_staff_by_division($division_id);

        render('weekly_tasks', $data);
    }
    public function calendar()
    {
        $data['title'] = "Weekly Task Calendar";
        $data['module'] = 'weeklytasks';
        render('weekly_calendar', $data);
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
            'end_date'   => $this->input->post('end_date')
        ];
    
        $total = $this->mdl->count_tasks($filters, $search);
        $tasks = $this->mdl->fetch_tasks($filters, $start, $length, $search);
    
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
            'data' => $tasks
        ]);
    }
    

    public function save() {
        // $this->form_validation->set_rules('work_planner_tasks_id', 'Sub Activity', 'required');
        // $this->form_validation->set_rules('activity_name[]', 'Activity Name', 'required');
        // $this->form_validation->set_rules('start_date', 'Start Date', 'required');
        // $this->form_validation->set_rules('end_date', 'End Date', 'required');
        // $this->form_validation->set_rules('staff_ids[]', 'Staff', 'required');

        // if ($this->form_validation->run() === FALSE) {
        //     echo json_encode(['status' => 'error', 'message' => strip_tags(validation_errors())]);
        //     return;
        // }

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
                $this->mdl->insert_task($data);
                $saved++;
            }
        }

        echo json_encode(['status' => 'success', 'message' => "$saved task(s) saved successfully!"]);
    }

    public function update() {
        $id = $this->input->post('activity_id');
        $task = $this->mdl->get_by_id($id);

        if (!$task || $task->status != 1) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found or not editable']);
            return;
        }

        $updated_by = $this->session->userdata('user')->staff_id;

        $data = [
            'activity_name' => $this->input->post('activity_name'),
            'comments' => $this->input->post('comments'),
            'status' => $this->input->post('status'),
            'updated_by' => $updated_by
        ];

        $this->mdl->update_task($id, $data);

        if ($data['status'] == 3) {
            $original = $this->mdl->get_by_id($id);

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

            $this->mdl->insert_task($clone);
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

    public function print_staff_report($staff_id, $week_start) {
        $data['staff'] = $this->mdl->get_staff($staff_id);
        $data['week_label'] = $this->get_week_label($week_start);
        $data['week_range'] = $this->get_week_range($week_start);
        $data['tasks'] = $this->mdl->get_tasks_by_staff_and_week($staff_id, $week_start);

        pdf_print_data($data, 'Staff_Weekly_Report.pdf', 'P', 'pdf/print_staff');
    }

    public function print_division_report($division_id, $week_start) {
        $staffs = $this->staff_mdl->get_staff_by_division($division_id);
        $data['division_tasks'] = [];

        foreach ($staffs as $staff) {
            $data['division_tasks'][$staff->fname . ' ' . $staff->lname] = $this->mdl->get_tasks_by_staff_and_week($staff->staff_id, $week_start);
        }

        $data['week_label'] = $this->get_week_label($week_start);
        $data['week_range'] = $this->get_week_range($week_start);

        pdf_print_data($data, 'Division_Weekly_Report.pdf', 'L', 'pdf/division_print');
    }

    public function get_staff_events()
    {
        $staff_id = $this->session->userdata('user')->staff_id;
    
        $tasks = $this->mdl->get_tasks_for_calendar($staff_id);
        $events = [];
    
        foreach ($tasks as $task) {
            $statusColor = match ((int)$task->status) {
                1 => '#ffc107', // Pending - Yellow
                2 => '#28a745', // Completed - Green
                3 => '#007bff', // Carried Forward - Blue
                4 => '#dc3545', // Cancelled - Red
                default => '#6c757d', // Unknown - Gray
            };
    
            $events[] = [
                'title' => $task->activity_name,
                'start' => $task->start_date,
                'end' => date('Y-m-d', strtotime($task->end_date . ' +1 day')), // fullcalendar exclusive end
                'color' => $statusColor,
                'allDay' => true,
            ];
        }
    
        echo json_encode($events);
    }
    
}
