<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Workplan extends MX_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('workplan_mdl', 'workplan_mdl');
		
    }

    // Add Activity
    public function index() {
        $data['title'] = "Work Plan";
        $data['module'] = 'workplan';
        $data['division_id'] = $this->session->userdata('user')->division_id;
        $data['divisions'] = $this->db->order_by('division_id', 'asc')->get('divisions')->result();
        render('view_workplan', $data);
    }

    public function get_workplan_ajax() {
        $division_id = $this->session->userdata('user')->division_id;
        $query = $this->input->get('q');
        $year = $this->input->get('year') ?: $this->input->post('year') ?: date('Y');
    
        if ($query) {
            $data = $this->workplan_mdl->search($query, $division_id, $year);
        } else {
            $data = $this->workplan_mdl->get_activities($division_id, $year);
        }
    
        echo json_encode($data);
    }
    
    public function upload_workplan() {
        if (!empty($_FILES['file']['name'])) {
            $file = $_FILES['file']['tmp_name'];
            $division_id = $this->session->userdata('user')->division_id ?: 21; // fallback to 21 (MIS)
            // Open and read CSV
            if (($handle = fopen($file, 'r')) !== FALSE) {
                $header = fgetcsv($handle); // skip header row
    
                while (($row = fgetcsv($handle)) !== FALSE) {
                    // Expected CSV column order:
                    // [0] => Intermediate Outcome, [1] => Broad Activity, [2] => Output Indicator, [3] => Cumulative Target, [4] => Activity Name
                    $data = [
                        'division_id' => $row[0],
                        'intermediate_outcome' => $row[1],
                        'broad_activity' => $row[2],
                        'output_indicator' => $row[3],
                        'cumulative_target' => $row[4],
                        'activity_name' => $row[5],
                        'year' => $row[6],
                        'has_budget' => $row[7],
                    ];
    
                    $this->workplan_mdl->insert($data);
                }
    
                fclose($handle);
            }
    
            $msg = ['msg'=>'success',
                   'type'=>'Workplan uploaded successfully.'];
            
            Modules::run('utility/setFlash', $msg);
            $log_message = "Uploaded a work plan for a division identified by ".$division_id;
		log_user_action($log_message);
        }
        redirect('workplan/index');
    }
    public function download_template() {
        // Get 5 sample rows
        $results = $this->db->limit(5)->get('workplan_tasks')->result();
    
        // Get division info from session
        $division_id = $this->session->userdata('user')->division_id;
        $division_name = $this->session->userdata('user')->division_name ?? 'Division';
    
        // Prepare final array with division_id replaced
        $fd = [];
        foreach ($results as $row) {
            $row_array = (array) $row;
            $row_array['division_id'] = $division_id; // override with session value
            $fd[] = $row_array;
        }
    
        // Clean division name for filename
        $safe_division = preg_replace('/[^a-zA-Z0-9_]/', '_', $division_name);
        $file_name = 'Workplan_Upload_Template_' . $safe_division.'.csv';
    
        // Generate CSV
        render_csv_data($fd, $file_name);
    }
    
    

    public function delete($id) {
        if ($this->session->userdata('user')->role == 10) {
            $this->workplan_mdl->delete($id);
          
        }
    }
    public function get_task_by_id($id) {
        $task = $this->workplan_mdl->get_by_id($id);
        echo json_encode($task);
    }
    
    public function update_task() {
        if ($this->input->method() === 'post') {
            $id = $this->input->post('id');
            $data = [
                'intermediate_outcome' => $this->input->post('intermediate_outcome'),
                'broad_activity'       => $this->input->post('broad_activity'),
                'output_indicator'     => $this->input->post('output_indicator'),
                'cumulative_target'    => $this->input->post('cumulative_target'),
                'activity_name'        => $this->input->post('activity_name'),
                'year'                 => $this->input->post('year'),
                'division_id'          => $this->input->post('division_id'),
                'has_budget'           => $this->input->post('has_budget') ? 1 : 0
            ];
    
            if ($this->workplan_mdl->update($id, $data)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update workplan.']);
            }
        } else {
            show_404();
        }
    }
    
    public function get_workplan_by_id($id) {
        echo json_encode($this->workplan_mdl->get_by_id($id));
    }
    
    public function create_task() {
        if ($this->input->method() === 'post') {
            $data = $this->input->post();
            $data['division_id'] = $this->session->userdata('user')->division_id ?: 21;
            $data['has_budget'] = $this->input->post('has_budget') ? 1 : 0;
    
            if ($this->workplan_mdl->insert($data)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create workplan.']);
            }
        } else {
            show_404();
        }
    }
    
    
    
}
    
?>