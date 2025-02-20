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
    public function expired_accounts($status){
		$data['module'] = $this->module;
	
		if ($status == 3) {
			$data['title'] = "Expired Accounts";
		} 
		
		$data['staff'] = $this->staff_mdl->get_status($status);
	
	
		render('manage_domains', $data);

	}
}
