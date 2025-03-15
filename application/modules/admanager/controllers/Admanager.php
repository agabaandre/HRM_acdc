<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admanager extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // $this->load->library('adldap');
        $this->module='admanager';
    }

    public function list_accounts() {
        $filter = '(&(objectClass=user)(objectCategory=person)(mail=*))';
        $attributes = array('cn', 'mail', 'userAccountControl');
        
        $results = $this->adldap->search($filter, $attributes);
        
        $accounts = array();
        for ($i = 0; $i < $results['count']; $i++) {
            $accounts[] = array(
                'name' => $results[$i]['cn'][0],
                'email' => $results[$i]['mail'][0],
                'enabled' => !($results[$i]['useraccountcontrol'][0] & 2)
            );
        }
        
        $this->load->view('account_list', array('accounts' => $accounts));
    }

    public function disable_account($email) {
        $filter = "(&(objectClass=user)(objectCategory=person)(mail=$email))";
        $results = $this->adldap->search($filter);
        
        if ($results['count'] > 0) {
            $dn = $results[0]['dn'];
            $uac = $results[0]['useraccountcontrol'][0];
            
            // Set the ACCOUNTDISABLE flag
            $new_uac = $uac | 2;
            
            $entry = array(
                'userAccountControl' => $new_uac
            );
            
            if ($this->adldap->modify($dn, $entry)) {
                echo "Account disabled successfully";
            } else {
                echo "Failed to disable account";
            }
        } else {
            echo "Account not found";
        }
    }
    public function expired_accounts($status, $csv=FALSE)
	{

		$data['module'] = $this->module;
		$data['title'] ="Accounts to Disable";
		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$filters = $this->input->post();
		$filters['csv'] =$csv;
		$filters['status_id'] =$status;	
		
        $count = count($this->staff_mdl->get_status($filters));
		$data['records'] = $count;
		//dd($count);
		$data['staffs'] = $this->staff_mdl->get_status($filters,$per_page = 20, $page);
		//dd($data);
		$staffs= $data['staffs'];
		$file_name = $data['title'].'_Africa CDC Staff_'.date('d-m-Y-H-i').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		//dd($data);
		$data['links'] = pagination("staff/expired_accounts/".$status, $count, $per_page = 20);
		render('manage_domains', $data);
	}

    
    public function report($status, $csv=FALSE)
	{

		$data['module'] = $this->module;
		$data['title'] ="Disabled Accounts";
		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$filters = $this->input->post();
		$filters['csv'] =$csv;
		$filters['status_id'] =$status;	
		
        $count = count($this->staff_mdl->get_status($filters));
		$data['records'] = $count;
		//dd($count);
		$data['staffs'] = $this->staff_mdl->get_status($filters,$per_page = 20, $page);
		//dd($data);
		$staffs= $data['staffs'];
		$file_name = $data['title'].'_Africa CDC Staff_'.date('d-m-Y-H-i').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		//dd($data);
		$data['links'] = pagination("staff/expired_accounts/".$status, $count, $per_page = 20);
		render('manage_domains', $data);
	}
    function remove_ids($staffs = []) {
		$keysToRemove = [
			'staff_contract_id',
			'job_id',
			'job_acting_id',
			'grade_id',
			'contracting_institution_id',
			'funder_id',
			'first_supervisor',
			'second_supervisor',
			'contract_type_id',
			'duty_station_id',
			'division_id',
			'unit_id',
			'flag',
			'created_at',
			'updated_at',
			'status_id',
			'division_head',
			'focal_person',
			'admin_assistant',
			'finance_officer',
			'region_id',
			'email_status'

		];
		
		// If it's an array of arrays:
		foreach ($staffs as $index => $staff) {
			foreach ($keysToRemove as $key) {
				unset($staffs[$index][$key]);
			}
		}
		
		return $staffs;
	}
	
	
    public function mark_disabled($staff_id){
        $user = $this->session->userdata('user')->staff_id; 
        //dd($user);
        $data['email_disabled_by']= $user;
        $data['email_status'] = 0;
        $data['email_disabled_at'] = date('Y-m-d H:i:s');

        $this->db->where('staff_id',$staff_id);
        $this->db->update('staff',$data);

        redirect('admanager/expired_accounts/3');

    }
    public function mark_enabled($staff_id){
        $user = $this->session->userdata('user')->staff_id; 
        //dd($user);
        $data['email_disabled_by']= $user;
        $data['email_status'] = 1;
        $data['email_disabled_at'] = date('Y-m-d H:i:s');

        $this->db->where('staff_id',$staff_id);
        $this->db->update('staff',$data);

        redirect('admanager/expired_accounts/3');

    }

}
