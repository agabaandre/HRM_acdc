<?php

use utils\HttpUtils;
defined('BASEPATH') or exit('No direct script access allowed');
use TheNetworg\OAuth2\Client\Provider\Azure;

class Auth extends MX_Controller
{
  private $provider;
  private $http;
  private $client_id;
  private $client_secret;
  private $tenant_id;
  private $redirect_uri;
  public function __construct()
  {
    parent::__construct();
    $this->load->model('auth_mdl');
    $this->module = "auth";
    $this->client_id = $_ENV['CLIENT_ID'];
    $this->client_secret = $_ENV['CLIENT_SEC_VALUE'];
    $this->tenant_id = $_ENV['TENANT_ID'];
    $this->redirect_uri = base_url('auth/callback');
   
  }
  public function index()
  {

    $this->load->view("login/login");
  }

  public function login() {
    $authorize_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/authorize";
    $params = [
        'client_id'     => $this->client_id,
        'response_type' => 'code',
        'redirect_uri'  => $this->redirect_uri,
        'response_mode' => 'query',
        'scope'         => 'openid profile email offline_access User.Read'
    ];
    redirect($authorize_url . '?' . http_build_query($params));
}

public function callback() {
    if (!$this->input->get('code')) {
        if ($this->input->get('error')) {
            exit('Error: ' . htmlspecialchars($this->input->get('error')));
        } else {
            exit('Invalid request');
        }
    }

    $token = $this->get_access_token($this->input->get('code'));
   // dd($token);
    if (!$token) {
        exit('Error retrieving access token.');
    }

    $user = $this->get_user_data($token);
    //dd($user);
    if ($user) {
        $email = $user['mail'] ?? $user['userPrincipalName']; // Use mail or userPrincipalName if mail is missing
        $name = $user['displayName'];

        // Check if email exists in the database
        $postdata = ['email' => $email];
        $data['users'] = $this->auth_mdl->login($postdata);

        //dd($data['users']);

        if (!empty($data['users'])) {
            // Proceed with login
            $this->handle_login($data['users'], $email);
        } else {
            // Reject login
            $this->session->set_flashdata('error', 'Staff profile missing. Contact HR.');
            redirect('auth');
        }
    } else {
        exit('Failed to fetch user details.');
    }
}

private function get_access_token($auth_code) {
    $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";

    $post_data = [
        'client_id'     => $this->client_id,
        'client_secret' => $this->client_secret,
        'code'          => $auth_code,
        'redirect_uri'  => $this->redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $headers = ['Content-Type: application/x-www-form-urlencoded'];

    $response = curl_send_post($token_url, $post_data, $headers);
    return $response->access_token ?? null;
}

private function get_user_data($access_token) {
    $url = "https://graph.microsoft.com/v1.0/me";
    $headers = ['Authorization: Bearer ' . $access_token, 'Accept: application/json'];

    $response = curl_send_get($url, $headers);
    return $response ?? null;
}

private function handle_login($user_data, $email) {
  //dd($user_data->auth_staff_id);
  dd($user_data);
    $data['contract'] = $this->staff_mdl->get_latest_contracts($user_data->auth_staff_id);
    $users_array = (array) $user_data;
    $contract_array = (array) $data['contract'];
    $users = array_merge($users_array, $contract_array);
    
    $role = $user_data->role;

    unset($users['password']);
    $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
    $users['is_admin'] = false;
    $_SESSION['user'] = (object) $users;

    if (!empty($user_data)&& $role!=17 ) {
      unset($users['password']);
         $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
          $users['is_admin'] = false;
          $_SESSION['user'] = (object)$users;
            
          $log_message = "User Logged in Successfully using MS SSO";
          log_user_action($log_message);
          redirect('dashboard');

       
      
  }
  else if (!empty($user_data)&& $role==17 ) {
    unset($users['password']);
       $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
        $users['is_admin'] = false;
        $_SESSION['user'] = (object)$users;
        $log_message = "User Logged in Successfully using MS SSO";
        log_user_action($log_message);
        redirect('auth/profile');
    
}
  else {
  
      redirect('auth');
  }
}

// public function logout() {
//     $this->session->sess_destroy();
//     redirect('https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/logout?post_logout_redirect_uri=' . base_url());
// }

public function cred_login()
{
    $postdata = $this->input->post();
    $post_password = trim($this->input->post('password'));

    // Fetch user data
    $data['users'] = $this->auth_mdl->login($postdata);

    // Check if user exists
    if (empty($data['users'])) {
        $this->session->set_flashdata('error', 'Invalid email or user does not exist.');
        redirect('auth'); // Redirect back to login page
        return;
    }

    $data['contract'] = $this->staff_mdl->get_latest_contracts($data['users']->staff_id);
    //dd( $data['contract']);

    $users_array = (array)$data['users'];
    $contract_array = (array)$data['contract'];
    $users = array_merge($users_array, $contract_array);
    
    // Use the stored hash from the database
    $dbpassword = $data['users']->password;
    $role = $data['users']->role;
    $auth = $this->validate_password($post_password, $dbpassword);

    if ($auth && !empty($data['users']) && $role != 17) {
        unset($users['password']);
        $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
        $users['is_admin'] = false;
        $_SESSION['user'] = (object)$users;
        $log_message = "User Logged in Successfully using Email and Password";
        log_user_action($log_message);
        redirect('dashboard');
    } elseif ($auth && !empty($data['users']) && $role == 17) {
        unset($users['password']);
        $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
        $users['is_admin'] = false;
        $_SESSION['user'] = (object)$users;
        $log_message = "User Logged in Successfully using Email and Password";
        log_user_action($log_message);
        redirect('auth/profile');
    } else {
        $this->session->set_flashdata('error', 'Incorrect password. Please try again.');
        redirect('auth'); // Redirect back to login page
    }
}

public function impersonate($user_id)
{
    // Check if current user is admin
    $current_user = $this->session->userdata('user');
    if (!$current_user || $current_user->role != 10) {
        show_error('You are not authorized to impersonate users.', 403);
    }

    // Store current session as "original user"
    $this->session->set_userdata('original_user', $current_user);

    // Fetch the user to impersonate
    $user = $this->auth_mdl->find_user($user_id);

    //dd($user);
    if (empty($user)) {
        $this->session->set_flashdata('error', 'User not found.');
        redirect('dashboard');
    }

    // Merge contract details
    $contract = $this->staff_mdl->get_latest_contracts($user->auth_staff_id);
    $user_array = (array)$user;
    $contract_array = (array)$contract;
    $merged = array_merge($user_array, $contract_array);

    unset($merged['password']);
    $merged['permissions'] = $this->auth_mdl->user_permissions($merged['role']);
    $merged['is_admin'] = false;

    $this->session->set_userdata('user', (object)$merged);
    $this->session->mark_as_temp('user', 300); // optional: set lifespan for session if needed
    session_write_close(); // force session save
    redirect('dashboard');
    $msg = [
      'msg' => 'You are now impersonating ' . $user->surname . '.',
      'type' => 'success'
     ];
    Modules::run('utility/setFlash', $msg);
    $log_message = "User Impersonated ".$user. "," .$user->auth_staff_id. "Successfully using the Impersonate Feature";
        log_user_action($log_message);
  
    redirect('dashboard');
}


public function revert()
{
    $original = $this->session->userdata('original_user');

    if ($original) {
        $this->session->set_userdata('user', $original);
        $this->session->unset_userdata('original_user');

        $msg = [
            'msg' => 'You have returned to your admin session.',
            'type' => 'success'
        ];
    } else {
        $msg = [
            'msg' => 'You are not impersonating any user.',
            'type' => 'warning'
        ];
    }

    Modules::run('utility/setFlash', $msg);
    $log_message = "Reverted back to personal account";
    log_user_action($log_message);
    redirect('dashboard');
}


  public function validate_password($post_password,$dbpassword){
    $auth = ($this->argonhash->check($post_password, $dbpassword));
     if ($auth) {
       return TRUE;
     }
     else{
       return FALSE;
     }
     
   }

  public function profile()
  {
    $data['module'] = "auth";
    $data['view'] = "profile";
    $data['title'] = "My Profile";

    render("users/profile", $data);

  }
  public function logout()
  {
    // Clear session
   // Unset all session variables
   $log_message = "Logged Out";
   //log_user_action($log_message);
   $this->session->unset_userdata('user');
   $this->session->sess_destroy();

   // Clear session variables manually
   $_SESSION = array();
   
   // Remove the session cookie (if set)
   if (ini_get("session.use_cookies")) {
       $params = session_get_cookie_params();
       setcookie(session_name(), '', time() - 42000,
           $params["path"], $params["domain"],
           $params["secure"], $params["httponly"]
       );
   }
   
   // Prevent browser caching (important!)
   header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
   header("Pragma: no-cache");
   header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
  redirect("auth");
  }

  public function getUserByid($id)
  {
    $userrow = $this->auth_mdl->getUser($id);
    //print_r($userrow);
    return $userrow;
  }

  public function users()
  {
    $searchkey = $this->input->get('search_key');
    if (empty($searchkey)) {
      $searchkey = "";
    }
    $this->load->library('pagination');
    $config = array();
    $config['base_url'] = base_url() . "auth/users";
    $config['total_rows'] = $this->auth_mdl->count_Users($searchkey);
    $config['per_page'] = 20; //records per page
    $config['uri_segment'] = 3; //segment in url  
    //pagination links styling
    $config['full_tag_open'] = '<ul class="pagination">';
    $config['full_tag_close'] = '</ul>';
    $config['attributes'] = ['class' => 'page-link'];
    $config['first_link'] = false;
    $config['last_link'] = false;
    $config['first_tag_open'] = '<li class="page-item">';
    $config['first_tag_close'] = '</li>';
    $config['prev_link'] = '&laquo';
    $config['prev_tag_open'] = '<li class="page-item">';
    $config['prev_tag_close'] = '</li>';
    $config['next_link'] = '&raquo';
    $config['next_tag_open'] = '<li class="page-item">';
    $config['next_tag_close'] = '</li>';
    $config['last_tag_open'] = '<li class="page-item">';
    $config['last_tag_close'] = '</li>';
    $config['cur_tag_open'] = '<li class="page-item active"><a href="#" class="page-link">';
    $config['cur_tag_close'] = '<span class="sr-only">(current)</span></a></li>';
    $config['num_tag_open'] = '<li class="page-item">';
    $config['num_tag_close'] = '</li>';
    $config['use_page_numbers'] = false;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0; //default starting point for limits 
    $data['links'] = $this->pagination->create_links();
    $data['users'] = $this->auth_mdl->getAll($config['per_page'], $page, $searchkey);
   // dd($this->db->last_query());
    $data['divisions'] = $this->db->get('divisions')->result();
    $data['module'] = "auth";
    $data['title'] = "User Management";
    $data['uptitle'] = "User Management";
    render("users/add_users", $data);
  }

  public function fetch_users_ajax()
  {
      $searchkey = $this->input->get('search_key');
      $users = $this->auth_mdl->getAll(0, 1000, $searchkey); // fetch all for table
  
      $usergroups = Modules::run("permissions/getUserGroups");
  
      echo json_encode([
          'users' => $users,
          'usergroups' => $usergroups
      ]);
  }
  


  public function logs()
  {
    $searchkey = $this->input->get();
    if (empty($searchkey)) {
      $searchkey = "";
    }
    $this->load->library('pagination');
    $config = array();
    $config['base_url'] = base_url() . "auth/logs";
    $config['total_rows'] = $this->auth_mdl->count_logs($searchkey);
    $config['per_page'] = 50; //records per page
    $config['uri_segment'] = 3; //segment in url  
    //pagination links styling
    $config['full_tag_open'] = '<ul class="pagination">';
    $config['full_tag_close'] = '</ul>';
    $config['attributes'] = ['class' => 'page-link'];
    $config['first_link'] = false;
    $config['last_link'] = false;
    $config['first_tag_open'] = '<li class="page-item">';
    $config['first_tag_close'] = '</li>';
    $config['prev_link'] = '&laquo';
    $config['prev_tag_open'] = '<li class="page-item">';
    $config['prev_tag_close'] = '</li>';
    $config['next_link'] = '&raquo';
    $config['next_tag_open'] = '<li class="page-item">';
    $config['next_tag_close'] = '</li>';
    $config['last_tag_open'] = '<li class="page-item">';
    $config['last_tag_close'] = '</li>';
    $config['cur_tag_open'] = '<li class="page-item active"><a href="#" class="page-link">';
    $config['cur_tag_close'] = '<span class="sr-only">(current)</span></a></li>';
    $config['num_tag_open'] = '<li class="page-item">';
    $config['num_tag_close'] = '</li>';
    $config['use_page_numbers'] = false;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0; //default starting point for limits 
    $data['links'] = $this->pagination->create_links();
    $data['logs'] = $this->auth_mdl->get_logs($searchkey, $config['per_page'], $page);

   // dd($this->db->last_query());
    $data['module'] = "auth";
    $data['title'] = "User Access Logs";
    $data['uptitle'] = "User Access Logs";
    render("users/user_logs", $data);
  }
  public function addUser()
  {
    $postdata = $this->input->post();
    $res = $this->auth_mdl->addUser($postdata);
    echo $res;
  }
  public function contract_info($staff_id){
    $current_contract = current_contract($staff_id);
    $this->db->where('staff_contract_id',$current_contract);
    $this->db->join('jobs', 'staff_contracts.job_id=jobs.job_id');
    $this->db->join('staff', 'staff.staff_id=staff_contracts.staff_id');
    $this->db->join('jobs_acting', 'staff_contracts.job_acting_id=jobs_acting.job_acting_id');
    $data=  $this->db->get('staff_contracts')->row();
    return $data;
  }
  public function updateUser()
  {
    $postdata = $this->input->post();
    
      $res = $this->auth_mdl->updateUser($postdata);
    //no photo
  }

  public function acdc_users($job=FALSE){
  $final=array();
  $staffs =  $this->db->query("SELECT staff.*, staff_contracts.division_id,staff_contracts.staff_contract_id from staff join staff_contracts on staff.staff_id=staff_contracts.staff_id where work_email!='' and staff_contracts.status_id in (1,2,7) and staff.staff_id not in (SELECT DISTINCT auth_staff_id from user)")->result();
    foreach ($staffs as $staff):
      $users['name'] = $staff->lname . ' ' . $staff->fname;
      $users['status'] = 1;
      $users['auth_staff_id'] = $staff->staff_id;
      $users['password'] =$this->argonhash->make(setting()->default_password);
      $users['role'] = 17;
      $this->db->replace('user', $users);
    endforeach;
     $accts = $this->db->affected_rows();
   

    $msg = array(
      'msg' => $accts .'Staff Accounts Created .',
      'type' => 'info'
    );
    Modules::run('utility/setFlash', $msg);
    if (!$job) {
      redirect('auth/users');
    }
    


  }

  public function changePass()
  {
    $postdata = $this->input->post();
    echo $res = $this->auth_mdl->changePass($postdata);
    redirect('users/change_pass');
  }

  public function change_password(){
    $data['title'] = "Change Password";
    $data['module'] = "auth";
    render('users/change_pass', $data);
  }
  public function resetPass()
  {
    $postdata = $this->input->post();
    //print_r ($postdata);
    $res = $this->auth_mdl->resetPass($postdata);
    echo  $res;
  }
  public function blockUser()
  {
    $postdata = $this->input->post();
    //print_r ($postdata);
    $res = $this->auth_mdl->blockUser($postdata);
    echo $res;
  }
  public function unblockUser()                                                                                                                                                                                                                                                              
  {
    $postdata = $this->input->post();
    $res = $this->auth_mdl->unblockUser($postdata);
    echo $res;
  }
  public function update_profile()
  {
    $data = $this->input->post();
 
    //dd($postdata);
    $is_error = false;
    // Load the Upload library
    $this->load->library('upload');
    // For each get the file name and upload it
    // Get the cover
    $photo = $_FILES['photo'];
    $signature = $_FILES['signature'];
    //passport
    if (!empty($photo['name'])) {
      // Chnage the file name to cover with the extension and timestamp
      $photo['name'] = str_replace(' ', '_', $data['name']) . time() . pathinfo($photo['name'], PATHINFO_EXTENSION);
      $config['upload_path']   = './uploads/staff/';
      $config['allowed_types'] = 'gif|jpg|png|jpeg';
      $config['file_name']     = $photo['name'];

      $this->upload->initialize($config);
      // If the upload fails, set the error message
      if (!$this->upload->do_upload('photo')) {
        $this->session->set_flashdata('error', $this->upload->display_errors());
        $is_error = true;
      } else {
        // If the upload is successful, get the file name
        $photo = $this->upload->data('file_name');
        $data['photo'] = $photo;
        $this->session->userdata('user')->photo = $photo;
      }
    }

    //passport
    if (!empty($signature['name'])) {
      // Chnage the file name to cover with the extension and timestamp
      $signature['name'] = str_replace(' ', '_', $data['name']) . time() . pathinfo($signature['name'], PATHINFO_EXTENSION);
      $config['upload_path']   = './uploads/staff/signature/';
      $config['allowed_types'] = 'gif|jpg|png|jpeg';
      $config['file_name']     = $signature['name'];

      $this->upload->initialize($config);
      // If the upload fails, set the error message
      if (!$this->upload->do_upload('signature')) {
        $this->session->set_flashdata('error', $this->upload->display_errors());
        $is_error = true;
        //dd($this->upload->display_errors());
      } else {
        // If the upload is successful, get the file name
        $signature = $this->upload->data('file_name');
        $data['signature'] = $signature;
        $this->session->userdata('user')->signature = $signature;
      }
    }
  


    $this->session->userdata('user')->langauge = $data['langauge'];
    $res = $this->auth_mdl->updateProfile($data);

    if ($res) {
      $msg = array(
        'msg' => $res,
        'type' => 'success'
      );
      Modules::run('utility/setFlash', $msg);
      redirect('auth/profile');
    } else {
      $msg = array(
        'msg' => $res,
        'type' => 'error'
      );
      redirect('auth/profile');
    }
  }
  public function photoMark($imagepath)
  {
    $config['image_library'] = 'gd2';
    $config['source_image'] = $imagepath;
    //$config['wm_text'] = ' Uganda';
    $config['wm_type'] = 'overlay';
    $config['wm_overlay_path'] = './assets/images/daswhite.png';
    //$config['wm_font_color'] = 'ffffff';
    $config['wm_opacity'] = 40;
    $config['wm_vrt_alignment'] = 'bottom';
    $config['wm_hor_alignment'] = 'left';
    //$config['wm_padding'] = '50';
    $this->load->library('image_lib');
    $this->image_lib->initialize($config);
    $this->image_lib->watermark();
  }
  //permissions management

}
