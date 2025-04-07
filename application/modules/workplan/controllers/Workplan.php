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
                        'division_id' => $division_id,
                        'intermediate_outcome' => $row[0],
                        'broad_activity' => $row[1],
                        'output_indicator' => $row[2],
                        'cumulative_target' => $row[3],
                        'activity_name' => $row[4],
                        'year' => date('Y')
                    ];
    
                    $this->workplan_mdl->insert($data);
                }
    
                fclose($handle);
            }
    
            $msg = ['msg'=>'success',
                   'type'=>'Workplan uploaded successfully.'];
            Modules::run('utility/setFlash', $msg);
        }
        redirect('workplan/index');
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
        $id = $this->input->post('id');
        $data = [
            'intermediate_outcome' => $this->input->post('intermediate_outcome'),
            'broad_activity' => $this->input->post('broad_activity'),
            'output_indicator' => $this->input->post('output_indicator'),
            'cumulative_target' => $this->input->post('cumulative_target'),
            'activity_name' => $this->input->post('activity_name'),
        ];
        $this->workplan_mdl->update($id, $data);
    }
    public function get_workplan_by_id($id) {
        echo json_encode($this->workplan_mdl->get_by_id($id));
    }
    
    public function create_task() {
        $data = $this->input->post();
        $data['division_id'] = $this->session->userdata('user')->division_id ?: 21;
        $this->workplan_mdl->insert($data);
    }
    
    
}
    
?>