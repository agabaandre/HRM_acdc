<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "settings";
		$this->load->model("settings_mdl", 'settings_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Settings";
		render('settings', $data);
	}

	// Add Content to the Database
	public function add_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		// $redirect = $this->input->post('redirect');

		$this->settings_mdl->add_content($table);
		redirect('settings/'.$table);
		
	}

	// Update Content to the Database
	public function update_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		$column_name = $this->input->post('column_name');
		$caller_value = $this->input->post('caller_value');
		// $redirect = $this->input->post('redirect');

		$res = $this->settings_mdl->update_content($table, $column_name, $caller_value);
		$message = "Updated";

		if ($res) {
			$msg = array(
				'msg' => 'Successfully ' . $message,
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('settings/' . $table);
		} else {
			$msg = array(
				'msg' => 'Failed',
				'type' => 'error'
			);
		}

		redirect('settings/' . $table);
		
	}

	// Delete Content
	public function delete_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		$column_name = $this->input->post('column_name');
		$caller_value = $this->input->post('caller_value');
		// $redirect = $this->input->post('redirect');

		$res = $this->settings_mdl->delete_content($table, $column_name, $caller_value);
		$message = "Deleted";

		if ($res) {
			$msg = array(
				'msg' => 'Successfully ' . $message,
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('settings/' . $table);
		} else {
			$msg = array(
				'msg' => 'Failed',
				'type' => 'error'
			);
		}

		redirect('settings/'.$table);
		
	}

	
	public function duty_stations()
	{
		$this->load->model('settings_mdl');
		$data['countries'] = $this->settings_mdl->get_content('nationalities');
		$data['duties'] = $this->settings_mdl->get_content('duty_stations');

		$data['module'] = $this->module;
		$data['title'] = "Duty Stations";
		render('duty_stations', $data);
	}
	public function nationalities()
	{
		
		$data['nationalities'] = $this->settings_mdl->get_content('nationalities');

		$data['module'] = $this->module;
		$data['title'] = "Nationalities";
		render('nationalities', $data);
	}

	public function contracting_institutions()
	{

		$this->load->model('settings_mdl');
		$data['institutions'] = $this->settings_mdl->get_content('contracting_institutions');

		$data['module'] = $this->module;
		$data['title'] = "Contracting Institutions";
		render('contracting_institutions', $data);
	}

	public function units()
	{

		$this->load->model('settings_mdl');
		$data['units'] = $this->settings_mdl->get_content('units');

		$data['module'] = $this->module;
		$data['title'] = "Units";
		render('units', $data);
	}

	public function contract_types()
	{
		$this->load->model('settings_mdl');
		$data['contract_types'] = $this->settings_mdl->get_content('contract_types');

		$data['module'] = $this->module;
		$data['title'] = "Contract Types";
		render('contract_types', $data);
	}

	public function divisions()
	{
		$this->load->model('settings_mdl');
		
		$data['module'] = $this->module;
		$data['title'] = "Divisions";
		render('divisions', $data);
	}

	public function divisions_datatables()
	{
		$this->load->model('settings_mdl');
		
		// DataTables parameters
		$draw = intval($this->input->post("draw"));
		$start = intval($this->input->post("start"));
		$length = intval($this->input->post("length"));
		$search_value = $this->input->post("search")["value"];
		$order_column = intval($this->input->post("order")[0]["column"]);
		$order_dir = $this->input->post("order")[0]["dir"];
		
		// Column mapping for ordering
		$columns = array(
			0 => 'd.division_id',
			1 => 'd.division_name',
			2 => 'd.division_short_name',
			3 => 'd.category',
			4 => 'dh.fname',
			5 => 'fp.fname',
			6 => 'fo.fname',
			7 => 'fa.fname'
		);
		
		$order_by = isset($columns[$order_column]) ? $columns[$order_column] : 'd.division_name';
		
		// Get data
		$data = $this->settings_mdl->get_divisions_datatables($start, $length, $search_value, $order_by, $order_dir);
		$total_records = $this->settings_mdl->get_divisions_count();
		$filtered_records = $this->settings_mdl->get_divisions_count($search_value);
		
		// Prepare response
		$response = array(
			"draw" => $draw,
			"recordsTotal" => $total_records,
			"recordsFiltered" => $filtered_records,
			"data" => $data
		);
		
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($response));
	}
	public function directorates()
	{
		$this->load->model('settings_mdl');
	
		$data['directorates'] = $this->settings_mdl->get_content('directorates');

	
		// Additional metadata
		$data['module'] = $this->module;
		$data['title'] = "Directorates";
	
		render('directorates', $data);
	}
	
	public function grades()
	{
		$this->load->model('settings_mdl');
		$data['grades'] = $this->settings_mdl->get_content('grades');

		$data['module'] = $this->module;
		$data['title'] = "Grades";
		render('grades', $data);
	}

	public function jobs()
	{
		$this->load->model('settings_mdl');
		$data['jobs'] = $this->settings_mdl->get_content('jobs');

		$data['module'] = $this->module;
		$data['title'] = "Jobs";
		render('jobs', $data);
	}

	public function jobs_acting()
	{
		$this->load->model('settings_mdl');
		$data['jobs_acting'] = $this->settings_mdl->get_content('jobs_acting');

		$data['module'] = $this->module;
		$data['title'] = "Jobs Acting";
		render('jobs_acting', $data);
	}

	public function au_values()
	{
		$this->load->model('settings_mdl');
		$data['au_values'] = $this->settings_mdl->get_content('au_values');

		$data['module'] = $this->module;
		$data['title'] = "AU Values";
		render('au_values', $data);
	}

	public function funders()
	{
		$this->load->model('settings_mdl');
		$data['funders'] = $this->settings_mdl->get_content('funders');

		$data['module'] = $this->module;
		$data['title'] = "Funders";
		render('funders', $data);
	}

	public function contract_status()
	{
		$data['module'] = $this->module;
		$data['title'] = "Contract Status";
		render('contract_status', $data);
	}

	public function leave_types()
	{
		$this->load->model('settings_mdl');
		$data['leaves'] = $this->settings_mdl->get_content('leave_types');

		$data['module'] = $this->module;
		$data['title'] = "Leave Types";
		render('leave_types', $data);
	}

	public function member_states()
	{
		$data['module'] = $this->module;
		$data['title'] = "Member States";
		render('member_states', $data);
	}

	public function training_skills()
	{
		$this->load->model('settings_mdl');
		$data['skills'] = $this->settings_mdl->get_content('training_skills');

		$data['module'] = $this->module;
		$data['title'] = "Training Skills";
		render('training_skills', $data);
	}

	public function regions()
	{
		$this->load->model('settings_mdl');
		$data['regions'] = $this->settings_mdl->get_content('regions');

		$data['module'] = $this->module;
		$data['title'] = "Regions";
		render('regions', $data);
	}
public function sysvariables()
{
	$data['title'] = "Settings - Constants & Variables";
	$data['uptitle'] = "Constants & Variables";
	$data['module'] = 'settings';
	$data['view'] = "sys_variables";
	$postdata = $this->input->post();
	$data['setting'] = $this->settings_mdl->getSettings();

	if ($this->input->method() === 'post' && $this->input->post('language')) {
		unset($postdata['africacdc_csrf_cookie']);
		//dd($postdata);
		$res = $this->settings_mdl->update_variables($postdata);

		//dd($data);

		if ($this->input->is_ajax_request()) {
			// Respond to AJAX
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => $res ? 'success' : 'error',
					'message' => $res ? 'Successfully Saved' : 'Failed to save'
				]));
		} else {
			// Non-AJAX fallback
			$msg = array(
				'msg' => $res ? 'Successfully Saved' : 'Failed',
				'type' => $res ? 'success' : 'error'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('settings/sysvariables');
		}
	} else {
		echo Modules::run('templates/main', $data);
	}
}


public function ppa_variables()
{
    $data['title'] = "PPA Configuration";
    $data['uptitle'] = "PPA Configuration";
    $data['module'] = 'settings';
    $data['view'] = "ppa_variables";

    $postdata = $this->input->post();

   
    unset($postdata['africacdc_csrf_cookie'], $postdata['africacdc_csrf_token']);

    $data['setting'] = $this->settings_mdl->get_ppa();

    if ($this->input->post()) {
        $res = $this->settings_mdl->update_ppa_variables($postdata);
        if ($res) {
            $msg = ['msg' => 'Successfully Saved', 'type' => 'success'];
        } else {
            $msg = ['msg' => 'Failed', 'type' => 'error'];
        }
        Modules::run('utility/setFlash', $msg);
        redirect('settings/ppa_variables');
    } else {
        echo Modules::run('templates/main', $data);
    }
}

/**
 * Generate short names for existing divisions
 */
public function generate_division_short_names() {
    $data['title'] = 'Generate Division Short Names';
    $data['module'] = 'settings';
    $data['view_file'] = 'generate_short_names';
    
    if ($this->input->post()) {
        // Load the model
        $this->load->model('settings_mdl');
        
        // Generate short names
        $result = $this->settings_mdl->updateDivisionsWithShortNames();
        
        // Set flash message
        $message = "Processed {$result['total_processed']} divisions. ";
        $message .= "Updated: {$result['updated']}, Errors: {$result['errors']}";
        
        if ($result['errors'] > 0) {
            $message .= "<br><strong>Errors:</strong><br>";
            foreach ($result['results'] as $item) {
                if ($item['status'] == 'error') {
                    $message .= "Division {$item['id']} ({$item['name']}): {$item['error']}<br>";
                }
            }
        }
        
        Modules::run('utility/setFlash', $message, $result['errors'] > 0 ? 'error' : 'success');
        redirect('settings/generate_division_short_names');
    } else {
        // Load divisions without short names
        $this->load->model('settings_mdl');
        $data['divisions_without_short_names'] = $this->settings_mdl->getDivisionsWithoutShortNames();
        echo Modules::run('templates/main', $data);
    }
}

/**
 * Preview short names before generating
 */
public function preview_short_names() {
    $this->load->model('settings_mdl');
    $divisions = $this->settings_mdl->getDivisionsWithoutShortNames();
    
    $preview = array();
    foreach ($divisions as $division) {
        $shortName = $this->settings_mdl->generateShortCodeFromDivision($division->division_name);
        
        // Ensure short name is not empty
        if (empty($shortName)) {
            $shortName = 'DIV' . $division->division_id;
        }
        
        $preview[] = array(
            'id' => $division->division_id,
            'name' => $division->division_name,
            'proposed_short_name' => $shortName
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($preview);
}

	
		
	
}
