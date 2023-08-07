<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Auth extends MX_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('auth_mdl');
    $this->module = "auth";
  }
  public function index()
  {

    $this->load->view("login/login");
  }

  public function login()
  {
    $postdata = $this->input->post();
    $password = $this->input->post('password');
    $hash = $this->argonhash->make($password);
    $data = $this->auth_mdl->login($postdata);
    
    $adata = (array)$data;
   

    $auth = ($this->argonhash->check($password, $adata['password']));
    unset($adata['password']);
   

    if ($auth && $adata['role']==17) {
      $adata['permissions'] = $this->auth_mdl->user_permissions($adata['role']);
      $adata['is_admin']    = true;
      $_SESSION['user'] = (object)$adata;
      redirect('auth/profile');
    } else if ($auth && $adata['role']!= 17) {
      $adata['permissions'] = $this->auth_mdl->user_permissions($adata['role']);
      $adata['is_admin']    = true;
      $_SESSION['user'] = (object)$adata;
      redirect('dashboard/index');
     }
    else {
      redirect('auth/index');
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
    session_unset();
    session_destroy();
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
    $searchkey = $this->input->post('search_key');
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
    $data['divisions'] = $this->db->get('divisions')->result();
    $data['module'] = "auth";
    $data['title'] = "User Management";
    $data['uptitle'] = "User Management";
    render("users/add_users", $data);
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
    $userfile = $postdata['username'];
    //CHECK whether user upload a photo
    if (!empty($_FILES['photo']['tmp_name'])) {
      $config['upload_path']   = './assets/images/sm/';
      $config['allowed_types'] = 'gif|jpg|png';
      $config['max_size']      = 3070;
      $config['file_name']      = $userfile;
      $this->load->library('upload', $config);
      if (!$this->upload->do_upload('photo')) {
        $error = $this->upload->display_errors();
        echo strip_tags($error);
      } else {
        $data = $this->upload->data();
        $photofile = $data['file_name'];
        $path = $config['upload_path'] . $photofile;
        //water mark the photo
        $this->photoMark($path);
        $postdata['photo'] = $photofile;
        $res = $this->auth_mdl->updateUser($postdata);
      }
    } //user uploaded with a photo
    else {
      $res = $this->auth_mdl->updateUser($postdata);
    } //no photo
  }

  public function acdc_users($job=FALSE){
  $final=array();
  $staffs =  $this->db->query("SELECT staff.*, staff_contracts.division_id,staff_contracts.staff_contract_id from staff join staff_contracts on staff.staff_id=staff_contracts.staff_id where work_email!='' and staff_contracts.status_id in (1,2) and staff.staff_id not in (SELECT DISTINCT staff_id from user)")->result();
    foreach ($staffs as $staff):
      $users['username'] = explode('@', $staff->work_email)[0];
      $users['email'] = $staff->work_email;
      $users['name'] = $staff->lname . ' ' . $staff->fname;
      $users['division_id'] = $staff->division_id;
      $users['status'] = 1;
      $users['staff_id'] = $staff->staff_id;
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
    $postdata = $this->input->post();
    $data['username'] = $postdata['username'];
    $data['user_id'] = $postdata['user_id'];
    $data['name'] = $postdata['name'];
    $data['langauge'] = $postdata['langauge'];
    $data['staff_id'] = $postdata['staff_id'];
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
