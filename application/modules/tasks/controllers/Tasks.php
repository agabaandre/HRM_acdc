<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tasks extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('tasks_mdl', 'tasks_mdl');
		
    }

    // Add Activity
    public function activity() {
 
		$data['module'] = 'tasks';
		$data['title'] = "Add Activity";
        $data['outputs'] = $this->tasks_mdl->get_quarterly_output();
       render('add_activity', $data);
    }

	public function add_activity() {
        // Validate form inputs
        $this->form_validation->set_rules('quarterly_output_id[]', 'Quarterly Output', 'required');
        $this->form_validation->set_rules('activity_name[]', 'Activity Name', 'required');
        $this->form_validation->set_rules('start_date[]', 'Start Date', 'required');
        $this->form_validation->set_rules('end_date[]', 'End Date', 'required');

        if ($this->form_validation->run() === FALSE) {
            // If validation fails, return error response
            $response = [
                'status' => 'error',
                'message' => validation_errors()
            ];
        } else {
            // Get form data
            $quarterly_output_id = $this->input->post('quarterly_output_id');
            $activity_names = $this->input->post('activity_name');
            $start_dates = $this->input->post('start_date');
            $end_dates = $this->input->post('end_date');
			// Get the posted dates from the form
			$start_date = $this->input->post('start_date'); // Single value
			$end_date = $this->input->post('end_date'); // Single value

			// Convert the dates to MySQL format (YYYY-MM-DD)
			$formatted_start_date = date('Y-m-d', strtotime($start_date));
			$formatted_end_date = date('Y-m-d', strtotime($end_date));

			// Now $formatted_start_date and $formatted_end_date contain valid MySQL dates
            $comments = $this->input->post('comments');

        
                $activity_data = array(
					'staff_id' =>$this->session->userdata('user')->staff_id,
                    'quarterly_output_id' => $quarterly_output_id,
                    'activity_name' => $activity_names,
                    'start_date' => $formatted_start_date,
                    'end_date' => $formatted_end_date,
                    'comments' => $comments,
				);

                // Save activity to the database
                $this->tasks_mdl->add_activity($activity_data);
            

            // Return success response
            $response = [
                'status' => 'success',
                'message' => 'Activities added successfully!'
            ];
        }

        // Send JSON response
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    // View Activities
    public function view_activities() {
		$data['title'] = "View Activity";
		$data['module'] = 'tasks';
        $data['activities'] = $this->tasks_mdl->get_activities();
       render('view_activities', $data);
    }

      // View Activities
      public function approve_activities($id=false) {
 
            $data['module'] = 'tasks';
            $data['title'] = "Approve Activities";
            $data['outputs'] = $this->tasks_mdl->get_quarterly_output();
            if (!empty($id)) {
                $data = array(
                    'status' => 1
                );
                 $this->db->where('id', $id);
                if ($this->db->update('activities', $data)) {
                    // Successfully updated the activity status
                } else {
                    // Handle update error (optional)
                    log_message('error', 'Failed to update activity status for ID: ' . $id);
                }
            }
            
           render('add_activity', $data);
        }

    // Submit Report
    public function submit_report($activity_id) {
		$data['title'] = "Submit Report";
		$data['module'] = 'tasks';
        if ($_POST) {
            $data = array(
                'activity_id' => $activity_id,
                'report_date' => date('Y-m-d'),
                'description' => $this->input->post('description'),
                'status' => 'pending'
            );
            $this->tasks_mdl->submit_report($data);
            redirect('tasks/view_reports');
        }
       render('submit_report',$data);
    }


    public function fetch_activities() {
        // Get filter values from AJAX request
        $output_id = $this->input->get('output');
        $start_date = $this->input->get('start_date');
        $end_date = $this->input->get('end_date');
        $staff_id = $this->session->userdata('user')->staff_id;

        // Fetch activities from the model
        $activities = $this->tasks_mdl->get_activities($staff_id, $output_id, $start_date, $end_date);

        // Return JSON response
       // dd($activities);
        echo json_encode($activities);
    }
    public function fetch_pending_activities() {
        // Get filter values from AJAX request
        $output_id = $this->input->get('output');
        $start_date = $this->input->get('start_date');
        $end_date = $this->input->get('end_date');
        $staff_id = $this->session->userdata('user')->staff_id;
        // Fetch activities from the model
        $activities = $this->tasks_mdl->get_pending_activities($staff_id, $output_id, $start_date, $end_date);
        // Return JSON response
        echo json_encode($activities);
    }
    public function update_activity() {
        // Get all POST data
        $data = $this->input->post();
    
        // Extract the activity ID and remove it from the update data
        $id = $data['activity_id'];
        unset($data['activity_id']);
        
        // Optionally, remove CSRF token from data if present
        $csrf_token = $this->security->get_csrf_token_name();
        if(isset($data[$csrf_token])){
            unset($data[$csrf_token]);
        }
    
        // Update the activity record where activity_id equals $id
        $this->db->where('activity_id', $id);
        $this->db->update('activities', $data);
    
        // Return JSON response with a success message
        echo json_encode(['status' => 'success', 'message' => 'Activity updated successfully', 'data' => $data]);
    }

    //update report status

    public function update_status() {
        // Get all POST data
        $data = $this->input->post();
    
        // Extract the activity ID and remove it from the update data
        $id = $data['report_id'];
        unset($data['report_id']);
        
        // Optionally, remove CSRF token from data if present
        $csrf_token = $this->security->get_csrf_token_name();
        if(isset($data[$csrf_token])){
            unset($data[$csrf_token]);
        }
    
        // Update the activity record where activity_id equals $id
        $this->db->where('report_id', $id);
        $this->db->update('reports', $data);
    
        // Return JSON response with a success message
        echo json_encode(['status' => 'success', 'message' => 'Activity updated successfully', 'data' => $data]);
    }



      // Add Activity
      public function outputs($output_id=NULL, $start_date=NULL, $end_date=NULL) {
 
		$data['module'] = 'tasks';
		$data['title'] = "Quarterly Outputs";
        $data['quarterly_outputs'] = $this->tasks_mdl->get_outputs($output_id);
        
       render('add_outputs', $data);
    }

    public function add_outputs() {
        // Get all POST data
        $data = $this->input->post();
        $csrf_token = $this->security->get_csrf_token_name();
        if (isset($data[$csrf_token])) {
            unset($data[$csrf_token]);
        }
        // $data ['unit_id'] = $this->session->userdata('user_id')->unit_id;
        // Insert the data into the 'quarterly_outputs' table
        $this->db->insert('quarterly_outputs', $data);
        
        // Return JSON response with a success message
        echo json_encode([
            'status'  => 'success',
            'message' => 'Saved successfully',
            'data'    => $data
        ]);
    }
    public function add_report() {
        
        $csrf_token = $this->security->get_csrf_token_name();
        if (isset($data[$csrf_token])) {
            unset($data[$csrf_token]);
        }
       
            $data['activity_id']= $this->input->post('activity_id');
        	$data['report_date'] = date('Y-m-d');
            $data['description']= $this->input->post('description');
            $data['status']	= 'pending';
        
        // Insert the data into the 'quarterly_outputs' table
        $this->db->insert('reports', $data);
        
        // Return JSON response with a success message
        echo json_encode([
            'status'  => 'success',
            'message' => 'Saved successfully',
            'data'    => $data
        ]);
    }
    public function edit_outputs() {
        // Get all POST data
        $data = $this->input->post();
        $csrf_token = $this->security->get_csrf_token_name();
        if(isset($data[$csrf_token])){
            unset($data[$csrf_token]);
        }
        if (!empty($data['start_date'])) {
            $data['start_date'] = date('Y-m-d', strtotime($data['start_date']));
        }
        if (!empty($data['end_date'])) {
            $data['end_date'] = date('Y-m-d', strtotime($data['end_date']));
        }
    
    
        // Extract the activity ID and remove it from the update data
        
        // Optionally, remove CSRF token from data if present
        $id = $data['quarterly_output_id'];
        // Update the activity record where activity_id equals $id
        $this->db->where('quarterly_output_id', $id);
        $this->db->update('quarterly_outputs', $data);
    
        // Return JSON response with a success message
        echo json_encode(['status' => 'success', 'message' => 'Output updated successfully', 'data' => $data]);
    }
    public function delete_outputs() {
        // Get all POST data
        $data = $this->input->post();

        // Optionally, remove CSRF token from data if present
        $csrf_token = $this->security->get_csrf_token_name();
        if(isset($data[$csrf_token])){
            unset($data[$csrf_token]);
        }
        $id = $data['quarterly_output_id'];
    
        // Update the activity record where activity_id equals $id
        $this->db->where('quarterly_output_id', $id);
        $this->db->delete('quarterly_outputs', $data);
    
        // Return JSON response with a success message
        echo json_encode(['status' => 'success', 'message' => 'Deleted', 'data' => $data]);
    }
      // View Activities
      public function view_reports() {
		$data['title'] = "Activity Report";
		$data['module'] = 'tasks';
        $output_id = $this->input->get('output');
        $start_date = $this->input->get('start_date');
        $end_date = $this->input->get('end_date');
        $staff_id = $this->session->userdata('user')->staff_id;
        $data['reports'] = $this->tasks_mdl->get_reports($staff_id,$output_id, $start_date, $end_date);
       render('view_reports', $data);
    }

 
    

}
?>