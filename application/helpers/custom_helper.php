<?php

/*
 * Custom Helpers
 *
 */

//render with navigation
if (!function_exists('render')) {

    function render($view, $data = [], $plain = false)
    {

        $data['view'] = $view;

        //plain renders without navigation
        $template_method = ($plain) ? 'templates/plain' : 'templates/main';

        $data['settings'] = settings();

        echo Modules::run($template_method, $data);
    }
}
//render with navigation
if (!function_exists('render_dashboard')) {

    function render_dashboard($view, $data = [], $plain = false)
    {

        $data['view'] = $view;

        //plain renders without navigation
        $template_method = 'templates/dashboards';

        $data['settings'] = settings();

        echo Modules::run($template_method, $data);
    }
}

if (!function_exists('calculate_age')) {
    function calculate_age($birthdate)
    {
        $birthdate = new DateTime($birthdate);
        $today = new DateTime();
        $age = $birthdate->diff($today)->y;
        return $age;
    }
}

//render-front-main website
if (!function_exists('render_site')) {

    function render_site($view, $data = [], $is_home = false, $plain = false)
    {

        $data['view'] = $view;
        $template_method = ($plain) ? 'templates/plain' : 'templates/frontend';

        $data['settings'] = settings();
        $data['is_home']  = $is_home;

        echo Modules::run($template_method, $data);
    }
}


//retrieve system settings like them and display
if (!function_exists('settings')) {
    function settings()
    {
        $ci = &get_instance();
        $settings = $ci->db->get('setting')->row();
        return $settings;
    }
}

//set flash data
if (!function_exists('set_flash')) {
    function set_flash($message, $isError = false)
    {
        // Get a reference to the controller object
        $ci = &get_instance();
        $msgClass =  ($isError) ? 'danger' : 'success';
        return $ci->session->set_flashdata($msgClass, $message);
    }
}

if (!function_exists('get_flash')) {
    function get_flash($key)
    {
        // Get a reference to the controller object
        $ci = &get_instance();
        return $ci->session->flashdata($key);
    }
}

//read from language file

if (!function_exists('lang')) {
    function lang($string, $plural = false, $capital = false)
    {
        $ci = &get_instance();

        $phrase = $ci->lang->line($string);

        if ($plural)
            $phrase = $phrase . "s";
        if ($capital)
            $phrase = strtoupper($phrase);
        return $phrase;
    }
}

//print old form data
if (!function_exists('old')) {
    function old($field)
    {
        $ci = &get_instance();
       // dd($ci->session->flashdata('form_data')['lname']);
        return ($ci->session->flashdata('form_data')) ? html_escape($ci->session->flashdata('form_data')[$field]) : null;
    }
}

//print old form data
if (!function_exists('truncate')) {
    function truncate($content, $limit)
    {
        return (strlen($content) > $limit) ? substr($content, 0, $limit) . "..." : $content;
    }
}

if (!function_exists('paginate')) {
    function pagination($route, $totals, $perPage = 20, $segment = 3)
    {
        $ci = &get_instance();
        $config = array();

        $config["base_url"] = base_url() . $route;
        $config["total_rows"]     = $totals;
        $config["per_page"]       = $perPage;
        $config["uri_segment"]    = $segment;
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


        $ci->pagination->initialize($config);

        return $ci->pagination->create_links();
    }
}

if (!function_exists('time_ago')) {

    function time_ago($timestamp)
    {
        $time_ago = strtotime($timestamp);
        $current_time = time();
        $time_difference = $current_time - $time_ago;
        $seconds = $time_difference;

        $minutes = round($seconds / 60);           // value 60 is seconds
        $hours = round($seconds / 3600);           //value 3600 is 60 minutes * 60 sec
        $days = round($seconds / 86400);          //86400 = 24 * 60 * 60;
        $weeks = round($seconds / 604800);          // 7*24*60*60;
        $months = round($seconds / 2629440);     //((365+365+365+365+366)/5/12)*24*60*60
        $years = round($seconds / 31553280);     //(365+365+365+365+366)/5 * 24 * 60 * 60

        if ($seconds <= 60) {
            return "Just now";
        } else if ($minutes <= 60) {
            if ($minutes == 1) {
                return "1 " . "Minute" . " " . "ago";
            } else {
                return $minutes . " " . "Minutes" . " ago";
            }
        } else if ($hours <= 24) {
            if ($hours == 1) {
                return "1 " . "hour" . " " . "ago";
            } else {
                return $hours . " " . "hours" . " " . "ago";
            }
        } else if ($days <= 30) {
            if ($days == 1) {
                return "1 " . "day" . " " . "ago";
            } else {
                return $days . " " . "days" . " " . "ago";
            }
        } else if ($months <= 12) {
            if ($months == 1) {
                return "1 " . "month" . " " . "ago";
            } else {
                return $months . " " . "months" . " " . "ago";
            }
        } else {
            if ($years == 1) {
                return "1 " . "year" . " " . "ago";
            } else {
                return $years . " " . "years" . " " . "ago";
            }
        }
    }
}


if (!function_exists('is_past')) {

    function is_past($date)
    {
        $date_now = new DateTime();
        $date2    = new DateTime($date);
        return ($date_now > $date2);
    }
}

if (!function_exists('text_date')) {

    function text_date($date)
    {
        return date("M jS, Y", strtotime($date));;
    }
}

if (!function_exists('setting')) {

    function setting()
    {
        $ci = &get_instance();
        return $ci->db->get('setting')->row();
    }
}
if (!function_exists('getcountry')) {

    function getcountry($id)
    {
        $ci = &get_instance();
        $ci->db->where('nationality_id', $id);
        return $ci->db->get('nationalities')->row()->nationality;
    }
}


if (!function_exists('user_session')) {
    function user_session()
    {
        $ci = &get_instance();
        return ($ci->session->userdata()) ? (object) $ci->session->userdata() : (object) ['is_logged_in' => false, 'is_admin' => false];
    }
}


// 
if (!function_exists('asterik')) {
    function asterik()
    {
       return '<b style="color:red;">*</b>';
    }
}


if (!function_exists('poeple_titles')) {
    function poeple_titles()
    {
        $titles = ['Mr.', 'Mrs.', 'Ms.', 'Hon.', 'Dr.', 'Pr.', 'He.', 'Hh.'];
        return $titles;
    }
}





//date diff
if (!function_exists('date_difference')) {
    function date_difference($date1, $date2, $format = '%a')
    {
        $datetime_1 = date_create($date1);
        $datetime_2 = date_create($date2);
        $diff = date_diff($datetime_1, $datetime_2);
        return $diff->format($format);
    }
}



//generate unique id
if (!function_exists('generate_unique_id')) {
    function generate_unique_id()
    {
        $id = uniqid("", TRUE);
        $id = str_replace(".", "-", $id);
        return $id . "-" . rand(10000000, 99999999);
    }
}


//generate unique id
if (!function_exists('flash_form')) {
    function flash_form($data = null, $key = 'form_data')
    {
        $ci = &get_instance();

        if ($data == null)
            $data = $ci->input->post();

        $ci->session->set_flashdata($key, $data);
    }
}
if (!function_exists('current_contract')) {
    function current_contract($staff_id)
    {
    $ci = &get_instance();
    return $ci->db->query("SELECT max(staff_contract_id) as current_contract from staff_contracts where staff_id ='$staff_id'")->row()->current_contract;
    }
}
if (!function_exists('current_head_of_departmemnt')) {
    function current_head_of_departmemnt($division)
    {
        $ci = &get_instance();
        @$division_id = $ci->db->query("SELECT division_head from divisions where division_id='$division'")->row()->division_head;
        return $division_id;

    }
}
if (!function_exists('leave_balance')) {
    function leave_balance($staff_id,$leave_id)
    {
        $ci = &get_instance();
        $leave_count = $ci->db->query("SELECT leave_days from leave_types where leave_id='$leave_id'")->row()->leave_days;

        $sum  = $ci->db->query("SELECT SUM(requested_days) as sum_approved from staff_leave where  approval_status='Approved' and approval_status1='Approved' and approval_status2='Approved' and approval_status3='Approved' and leave_id='$leave_id' and staff_id='$staff_id'")->row()->sum_approved;

        $balance= $leave_count-$sum;
        return $balance;
    }
}
if (!function_exists('get_supervisor')) {
    function get_supervisor($contract_id)
    {
        $ci = &get_instance();
        $ci->db->where('staff_contract_id', $contract_id);
        $result = $ci->db->get('staff_contracts')->row();
        return $result;
    }
}

if (!function_exists('job_output')) {
    function job_output($output_id)
    {
        $ci = &get_instance();
        $ci->db->where('output_id', $output_id);
        $query = $ci->db->get('quarterly_outputs');

        if ($query->num_rows() > 0) {
            $result = $query->row()->name;
            if (!empty($result)) {
                return $result;
            }
        }

        return 'NA';
    }

}
if (!function_exists('clear_form')) {
    function clear_form($key = 'form_data')
    {
        $ci = &get_instance();

        $ci->session->set_flashdata($key, null);
    }
}

if (!function_exists('clear_form')) {
    function date_difference($start_date, $end_date)
    {

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);

        $interval = $start->diff($end);

        $days_difference = $interval->days;

    }
}
function check_logged_in()
{

    if (!user_session()->is_logged_in)
        redirect(base_url('client/login'));
}

function check_admin_access()
{

    if (!user_session()->is_admin)
        redirect(base_url("admin"));
}
if (!function_exists('render_csv_data')) {
    function render_csv_data($datas, $filename)
    {
        //datas should be assoc array
        $csv_file = $filename;
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"$csv_file\"");
        $fh = fopen('php://output', 'w');

        $is_coloumn = true;
        if (!empty($datas)) {
            foreach ($datas as $data) {
                if ($is_coloumn) {
                    fputcsv($fh, array_map(function($key) {
                        return ucwords(str_replace('_', ' ', $key));
                    }, array_keys($data)));
                    $is_coloumn = false;
                }
                
                fputcsv($fh, array_values($data));
            }
            fclose($fh);
        }
        exit;
    }
}


if (!function_exists('share_buttons')) {
    function share_buttons($link, $subject = "Check this  Africa CDC  resource")
    {

        $data['link']   = $link;
        $data['subject'] = $subject;

        $ci = &get_instance();

        $ci->load->view('templates/share_buttons', $data);
    }
}


if (!function_exists('user_session')) {
    function user_session($return_array = false)
    {
        $ci = &get_instance();
        if ($return_array) :
            return ($ci->session->userdata('region')) ? $ci->session->userdata() : ['is_logged_in' => false, 'is_admin' => false];
        else :
            return ($ci->session->userdata('region')) ? (object) $ci->session->userdata() : (object) ['is_logged_in' => false, 'is_admin' => false];
        endif;
    }
}

if (!function_exists('is_guest')) {
    function is_guest()
    {
        return (@user_session()->user) ? false : true;
    }
}

if (!function_exists('is_valid_image')) {

    function is_valid_image($name, $path = './uploads/staff/')
    {
        $image = $path . $name;
        if (file_exists($image)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
if (!function_exists('can_access')) {

    function can_access($permission)
    {
        $ci = &get_instance();
        $permissions = $ci->session->userdata('user')->permissions;
        return in_array($permission, $permissions);
    }
}

// Can Access With Array
if (!function_exists('can_access_multi')) {

    function can_access_multi($permissions)
    {
        // Get ID's of all permission names
        $permission_ids = array_map(function ($permission) {
            return get_permission_id($permission);
        }, $permissions);

        // dd($permission_ids);

        $ci = &get_instance();

        // Get ID's of all user permissions
        $user_permissions = $ci->session->userdata('user')->permissions;

        // dd($user_permissions);

        // Check if any of the permission id's match
        foreach ($permission_ids as $permission_id) {
            if (in_array($permission_id, $user_permissions)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('get_permission_id')) {

    function get_permission_id($permission)
    {
        $ci = &get_instance();
        $ci->db->select('id');
        $ci->db->where('name', $permission);
        $query = $ci->db->get('permissions');
        if ($query->num_rows() > 0) {
            return $query->row()->id;
        }
        return false;
    }
}
if (!function_exists('translate')) {

    function translate()
    {
        include('langauge.php');
    }
}
if (!function_exists('activelink')) {
    function activelink($link, $uri_segment, $child = FALSE)
    {

        $flink = $link;

        if ($uri_segment == $flink || $uri_segment == $child) {
            return "active";
        } else {
            return $flink;
        }
    }

    function uri_segment()
    {

        $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri_segments = explode('/', $uri_path);

        return $uri_segments;
    }
}

if (!function_exists('nice_number')) {
    function nice_number($n)
    {
        // first strip any formatting;
        if ($n == 'NULL' || $n == '' || $n == '0') {
            $n = 0;
            return ($n);
        } else {
            $n = (0 + str_replace(",", "", $n));
            // is this a number?
            if (!is_numeric($n))
                return false;
            // now filter it;
            if ($n > 1000000000000)
                return round(($n / 1000000000000), 2) . 'T';
            // elseif ($n > 1000000000) return round(($n/1000000000), 2).' billion doses';
            elseif ($n > 1000000)
                return round(($n / 1000000), 2) . 'M ';
            elseif ($n > 1000)
                return round(($n / 1000), 2) . 'K';
            return number_format($n);
        }
    }
}
if (!function_exists('dateDiff')) {

    function dateDiff($date1, $date2)
    {
        $date1_ts = strtotime($date1);
        $date2_ts = strtotime($date2);
        $diff = $date2_ts - $date1_ts;
        return round($diff / 86400);
    }
}
//select data from the db automatically
if (!function_exists('select_field')) {
    function select_field($name, $table)
    { ?>
        <select name="<?php echo $name . '_d'; ?>" class="form-control select2bs4" required="">

            <option value="" disabled>Select Value</option>
            <?php
            $CI = &get_instance();
            $query = $CI->db->get($table)->result();
            foreach ($query as $data) : ?>
                <option value="" disabled>N/A</option>
                <option value="<?php echo @$data->$name . '_id' ?>" d><?php echo @$data->$name; ?></option>
            <?php endforeach;



            ?>
        </select>
    <?php
    }
}
//select data from the db automatically
if (!function_exists('select_field')) {
    function selected_fields($dbval, $frontval) 
    {
        if ($dbval== $frontval) {
            return "selected";
        }
    }
}

if (!function_exists('acdc_division')) {

    function acdc_division($division)
    {
        $ci = &get_instance();
    return $ci->db->query("SELECT division_name from divisions where division_id='$division'")->row()->division_name;
    
    }
}

if (!function_exists('periods')) {

    function periods()
    {
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        $months = array(
            'January', 'February', 'March', 'April', 'May', 'June', 'July',
            'August', 'September', 'October', 'November', 'December'
        );
        $periods = '<option value="January ' . $currentYear . ' - December ' . $currentYear . '">January ' . $currentYear . ' - December ' . $currentYear . '</option>';
        $periods .= '<option value="January ' . $nextYear . ' - December ' . $nextYear . '">January ' . $nextYear . ' - December ' . $nextYear . '</option>';

        return $periods;
    }
}
if (!function_exists('get_staff_name')) {

    function staff_name($id)
    {
        $ci = &get_instance();
        $query = $ci->db->query("SELECT lname, fname from staff where staff_id='$id'")->row();
        return $query->fname. ' '. $query->lname;
    }
}
if (!function_exists('staff_details')) {

    function staff_details($id)
    {
        $ci = &get_instance();
        $query = $ci->db->query("SELECT * from staff where staff_id='$id'")->row();
        return $query;
    }
}


if (!function_exists('generate_user_avatar')) {
    function generate_user_avatar($surname, $other_name, $image_path,$photo=false)
    {
   
         
        if (!empty($photo)) {
            return '<img src="' . $image_path . '" class="user-img" alt="user avatar" 
                 style="cursor:pointer; width:50px; height:50px; border-radius:50%;" 
                 onclick="openImageModal(\'' . $image_path . '\')">';
        }
        
        // Get the initials (first letter of surname & other name)
        $surname_initial = !empty($surname) ? strtoupper(substr($surname, 0, 1)) : '';
        $other_name_initial = !empty($other_name) ? strtoupper(substr($other_name, 0, 1)) : '';
    
        // Generate the initials-based avatar
        $initials = $surname_initial . $other_name_initial;
        $bg_color = generate_random_color($surname . $other_name); // Unique background color
    
        return '<div class="avatar-placeholder" style="background-color:' . $bg_color . '; color: #fff; 
                width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                    ' . $initials . '
                </div>';
    }
    
// Function to generate a random color based on name hash
function generate_random_color($name)
{
    $hash = md5($name);
    return '#' . substr($hash, 0, 6);
}

}

if (!function_exists('log_user_action')) {
    function log_user_action($action)
    {
        $CI =& get_instance(); // Get CodeIgniter instance

        // Get the logged-in user's ID (change session key if needed)
        $user_id = $CI->session->userdata('user')->user_id ?? null;


        // Capture user IP and user agent
        $ip_address = $CI->input->ip_address();
        $user_agent = $CI->input->user_agent();

        // Insert log into database
        $data = [
            'user_id' => $user_id,
            'action' => $action,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ];
        $CI->db->insert('user_logs', $data);
    }
    function getRandomAUColor() {
        // Define AU color palette with background colors
        $colors = [
            "#194F90", // AU Blue
            "#348F41", // AU Green
            "#1A5632", // AU Corporate Green
            "#9F2241", // AU Red
            "#B4A269", // AU Gold
            "#58595B", // AU Grey Text
            "#AE1857", // Plum
            "#5B7E96", // Blue Grey
            "#FFB71B", // Amber
            "#1DCAD3", // Cyan
            "#FF5C35", // Deep Orange
            "#009383", // Teal
            "#8F4899", // Purple
            "#DAE343", // Lime
            "#385CAD", // Mauve
            "#E81F76"  // Pink
        ];
    
        // Select a random background color
        $bgColor = $colors[array_rand($colors)];
    
        // Convert HEX to RGB to determine brightness
        list($r, $g, $b) = sscanf($bgColor, "#%02x%02x%02x");
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000; // Standard luminance formula
    
        // Choose text color based on brightness
        $textColor = ($brightness < 128) ? "#FFFFFF" : "#000000"; // Dark background -> white text, Light background -> black text
    
        // Return inline CSS styles
        return 'style="color: ' . $textColor . '; background: ' . $bgColor . ';"';
    }

 
function curl_send_post($url, $body, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result);
}

    function curl_send_get($url, $headers) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
        $result = curl_exec($ch);
        curl_close($ch);
    
        return json_decode($result, true);
    }

 

    


  
}