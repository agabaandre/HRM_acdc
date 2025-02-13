<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TaskPlanner extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('TaskPlanner_model');
		
    }

    // Add Activity
    public function add_activity() {
        if ($_POST) {
            $data = array(
                'staff_id' => $this->input->post('staff_id'),
                'deliverable_id' => $this->input->post('deliverable_id'),
                'activity_name' => $this->input->post('activity_name'),
                'start_date' => $this->input->post('start_date'),
                'end_date' => $this->input->post('end_date')
            );
            $this->TaskPlanner_model->add_activity($data);
            redirect('taskplanner/view_activities');
        }
		$data['module'] = 'taskplanner';
		$data['title'] = "Add Activity";
        $data['deliverables'] = $this->TaskPlanner_model->get_deliverables();
       render('add_activity', $data);
    }

    // View Activities
    public function view_activities() {
		$data['title'] = "View Activity";
		$data['module'] = 'taskplanner';
        $data['activities'] = $this->TaskPlanner_model->get_activities($this->session->userdata('staff_id'));
       render('view_activities', $data);
    }

    // Submit Report
    public function submit_report($activity_id) {
		$data['title'] = "Submit Report";
		$data['module'] = 'taskplanner';
        if ($_POST) {
            $data = array(
                'activity_id' => $activity_id,
                'report_date' => date('Y-m-d'),
                'description' => $this->input->post('description'),
                'status' => 'pending'
            );
            $this->TaskPlanner_model->submit_report($data);
            redirect('taskplanner/view_reports');
        }
       render('submit_report',$data);
    }

    // View Reports
    public function view_reports() {
		$data['title'] = "View Reports";
		$data['module'] = 'taskplanner';
        $data['reports'] = $this->TaskPlanner_model->get_reports($this->session->userdata('staff_id'));
       render('view_reports', $data);
    }
}
?>