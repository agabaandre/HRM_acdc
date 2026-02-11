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

if (!function_exists('years_of_tenure')) {
    /**
     * Years of tenure from initiation_date to today.
     * @param string|null $initiation_date Date string (Y-m-d or similar)
     * @return int|string Years (integer) or 'N/A' if no date
     */
    function years_of_tenure($initiation_date)
    {
        if (empty($initiation_date)) {
            return 'N/A';
        }
        try {
            $start = new DateTime($initiation_date);
            $today = new DateTime();
            $diff = $start->diff($today);
            return $diff->y;
        } catch (Exception $e) {
            return 'N/A';
        }
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
        $fdatas = remove_ids($datas);
        if (empty($fdatas)) {
            return;
        }

        // Clean output buffer to prevent stray whitespace or empty line
        if (ob_get_length()) {
            ob_end_clean();
        }

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $fh = fopen('php://output', 'w');

        $is_column = true;

        foreach ($datas as $data) {
            if ($is_column) {
                // First row: output custom headers
                fputcsv($fh, array_map(function ($key) {
                    $replacements = [
                        'fname' => 'First Name',
                        'lname' => 'Last Name',
                        'oname' => 'Other Name',
                        'tel_1' => 'Contact1',
                        'tel_2' => 'Contact2',
                        'start_date' => 'Current Contract Start Date',
                        'end_date' => 'Current Contract End Date'
                    ];
                    $key = $replacements[$key] ?? str_replace('_', ' ', $key);
                    return ucwords($key);
                }, array_keys($data)));

                $is_column = false;
            }

            // Write data row
            fputcsv($fh, array_values($data));
        }

        fclose($fh);
        exit;
    }
}


function remove_ids($staffs = []) {
    $keysToRemove = [
        'staff_contract_id',
        'email_disabled_at',
        'email_disabled_by',
        'job_id',
        'job_acting_id',
        'job_acting',
        'grade_id',
        'contracting_institution_id',
        'funder_id',
        'nationality_id',
        'staff_id',
        'first_supervisor',
        'second_supervisor',
        'contract_type_id',
        'duty_station_id',
        'division_id',
        'unit_id',
        'photo',
        'flag',
        'created_at',
        'updated_at',
        'status_id',
        'division_head',
        'focal_person',
        'admin_assistant',
        'finance_officer',
        'region_id',
        'email_status',
       'entry_id',
       'id',
       'supervisor_id',
       'supervisor2_id',
       'staff_id'


    ];
    
    // If it's an array of arrays:
    foreach ($staffs as $index => $staff) {
        foreach ($keysToRemove as $key) {
            unset($staffs[$index][$key]);
        }
    }
    
    return $staffs;
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


if (!function_exists('current_period')) {

    function current_period()
    {
        $currentYear = date('Y');
        $periods = 'January ' . $currentYear .' to '. 'December ' . $currentYear;

        return $periods;
    }
}

if (!function_exists('previous_period')) {

    /**
     * Returns the performance period for the previous year (same format as current_period).
     * Used by jobs/reminders so notifications target the previous period, not the current one.
     */
    function previous_period()
    {
        $previousYear = (int) date('Y') - 1;
        return 'January ' . $previousYear . ' to ' . 'December ' . $previousYear;
    }
}

if (!function_exists('endterm_reminder_period')) {

    /**
     * Period to use for endterm reminders and approval notifications:
     * - Before October (Jan–Sep): previous year's period (e.g. 2024 when current year is 2025).
     * - October onwards (Oct–Dec): current year's period (e.g. 2025).
     */
    function endterm_reminder_period()
    {
        $month = (int) date('n');
        return $month >= 10 ? current_period() : previous_period();
    }
}

if (!function_exists('get_staff_name')) {

    function staff_name($id)
    {
        $ci = &get_instance();
        $query = $ci->db->query("SELECT title, lname, fname from staff where staff_id='$id'")->row();
        return @$query->title.' '.@$query->fname. ' '. @$query->lname;
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
    function generate_user_avatar($surname, $other_name, $image_path, $photo = false)
    {
        // Get the initials (first letter of surname & other name)
        $surname_initial = !empty($surname) ? strtoupper(substr($surname, 0, 1)) : '';
        $other_name_initial = !empty($other_name) ? strtoupper(substr($other_name, 0, 1)) : '';
        $initials = $surname_initial . $other_name_initial;
        
        // Generate color for avatar (matching Laravel app logic)
        $colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
        $firstChar = !empty($other_name) ? strtoupper($other_name[0]) : 'A';
        $colorIndex = (ord($firstChar) - 65) % count($colors);
        if ($colorIndex < 0) $colorIndex = 0;
        $bg_color = $colors[$colorIndex];

        // Check if photo exists and is valid
        $photo_exists = false;
        $clean_image_path = '';
        
        if (!empty($photo) && $photo !== null && trim($photo) !== '') {
            // Clean the image path
            $relative_path = str_replace(base_url(), '', $image_path);
            $relative_path = ltrim($relative_path, '/');
            $absolute_path = FCPATH . $relative_path;
            
            // Check if file actually exists on server
            if (file_exists($absolute_path) && is_valid_image($absolute_path)) {
                $photo_exists = true;
                $clean_image_path = rtrim(base_url(), '/') . '/uploads/staff/' . ltrim($photo, '/');
            }
        }

        // Only show image if it exists, otherwise show avatar only
        if ($photo_exists) {
            // Show photo only - no avatar fallback
            return '<img src="' . htmlspecialchars($clean_image_path) . '" 
                        class="user-img rounded-circle" 
                        style="width: 40px; height: 40px; object-fit: cover; cursor: pointer; border: 1px solid #fff;" 
                        alt="user avatar"
                        onclick="if(typeof openImageModal === \'function\') { openImageModal(\'' . htmlspecialchars($clean_image_path) . '\'); }">';
        } else {
            // Show initials avatar only (no photo exists)
            return '<div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                        style="width: 40px; height: 40px; background-color: ' . $bg_color . '; font-weight: 600; font-size: 14px; border: 1px solid #fff;">
                        ' . $initials . '
                    </div>';
        }
    }

    // Function to generate a random color based on name hash
    function generate_random_color($name)
{
    $hash = md5($name);
    return '#' . substr($hash, 0, 6);
}

}


function is_valid_image($absolute_path)
{
    if (!file_exists($absolute_path)) return false;

    // Check by file type
    $image_type = @exif_imagetype($absolute_path);

    // Standard image types
    $valid_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (in_array($image_type, $valid_types)) return true;

    // Check SVG manually
    $ext = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        $contents = file_get_contents($absolute_path);
        return stripos($contents, '<svg') !== false;
    }

    return false;
}


if (!function_exists('log_user_action')) {
    function log_user_action($action)
{
    $CI =& get_instance(); // Get CodeIgniter instance

    $user_id = $CI->session->userdata('user')->user_id ?? null;

    // Suppress deprecated filter_var warning for null IPs
    $ip_address = @($CI->input->ip_address()) ?: '0.0.0.0';
    $user_agent = $CI->input->user_agent() ?? 'Unknown';

    $data = [
        'user_id'    => $user_id,
        'action'     => $action,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
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
        return 'style="color: ' . $textColor . '; background: ' . $bgColor . '; min-height:120px;"';

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

    if (!function_exists('show_ppa_approval_action')) {
        function show_ppa_approval_action($ppa, $approval_trail=[], $current_user='')
        {
            $staff_id = $current_user->staff_id ?? null;
            $isSupervisor1 = isset($ppa->supervisor_id) && $ppa->supervisor_id == $staff_id;
            $isSupervisor2 = isset($ppa->supervisor2_id) && $ppa->supervisor2_id == $staff_id;
    
            $last_action = (is_array($approval_trail) && count($approval_trail) > 0) ? (end($approval_trail)->action ?? null) : null;

           // dd($last_action);
    
            $supervisor1Approved = false;
            $supervisor2Approved = false;
    
            if (!empty($approval_trail)) {
                foreach ($approval_trail as $log) {
                    if (
                        isset($log->action, $log->staff_id)
                    ) {
                        if ($log->action === 'Approved' && $log->staff_id == $ppa->supervisor_id) {
                            $supervisor1Approved = true;
                        }
                        if ($log->action === 'Approved' && $log->staff_id == $ppa->supervisor2_id) {
                            $supervisor2Approved = true;
                        }
                    }
                }
            }
    
            //dd($supervisor2Approved);
            // Main logic
            //dd($isSupervisor2);
            if ($isSupervisor1 && $ppa->draft_status == 0 && ($last_action == 'Submitted'||$last_action == 'Updated')) {
                return 'show';
            } elseif ($isSupervisor2 && $supervisor1Approved && $ppa->draft_status == 0 && $last_action == 'Approved' && !$supervisor2Approved) {
                return 'show';
            } elseif (
                ($supervisor1Approved && is_null($ppa->supervisor2_id)) || 
                ($supervisor1Approved && $supervisor2Approved)
            ) {
                return '<a href="' . base_url('performance/print_ppa/' . $ppa->entry_id) .'/'.$ppa->staff_id.'/'.$ppa->staff_contract_id.'" 
                            class="btn btn-dark btn-sm me-2" target="_blank">
                            <i class="fa fa-print"></i> Print PPA without Approval Trail
                        </a>' .'<a href="' . base_url('performance/print_ppa/' . $ppa->entry_id) .'/'.$ppa->staff_id.'/'.$ppa->staff_contract_id.'/1'. '" 
                        class="btn btn-dark btn-sm" target="_blank">
                        <i class="fa fa-print"></i> Print PPA With Approval Trail
                    </a>';
            } else {
                return false; // No action
            }
        }
    }
    

    if (!function_exists('pdf_print_data')) {
        function pdf_print_data($data, $file_name, $orient, $view)
        {
            ini_set('pcre.backtrack_limit', '10000000');

            // Get CodeIgniter instance
            $CI = &get_instance();
    
            // Load appropriate PDF library based on orientation
            if ($orient === 'L') {
                $CI->load->library('ML_pdf');
                $pdf = $CI->ml_pdf->pdf;
            } else {
                $CI->load->library('M_pdf');
                $pdf = $CI->m_pdf->pdf;
            }
    
            // Set watermark image (if available)
            $watermark = FCPATH . "assets/images/au_emblem.png";
            if (file_exists($watermark)) {
                $pdf->SetWatermarkImage($watermark);
                $pdf->showWatermarkImage = true;
            }
    
            // Set PDF margins
            $pdf->SetMargins(10, 10, 10);         // left, top, right margins
            $pdf->SetAutoPageBreak(true, 30);     // allow auto page break with 30mm bottom margin for footer
    
            // Set timezone and load view content
            date_default_timezone_set("Africa/Nairobi");
            $html = $CI->load->view($view, $data, true);
            $PDFContent = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    
            // Set footer content
            $PDFContent = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
         
            $PDFContent = preg_replace('/\s+/', ' ', $PDFContent); // Clean spaces
            $footer = '
            <table width="100%" style="font-size: 9pt; color: #911C39; border:none;">
                <tr>
                    <td align="left" style="border: none;">
                        Africa CDC, P.O. Box 3243, Addis Ababa, Ethiopia, Ring Road, 16/17<br>
                        Tel: +251 (0) 11 551 77 00, Fax: +251 (0) 11 551 78 44<br>
                        Website: <a href="https://africacdc.org" style="color: #911C39;">africacdc.org</a>
                    </td>
                    <td align="left" style="border: none;">
                        Source: Africa CDC - Staff Portal<br>
                        Generated on: ' . date('d F, Y h:i A') . '<br>
                        ' . base_url() . '
                    </td>
                </tr>
            </table>';
            $pdf->SetHTMLFooter($footer);
    
            // Output PDF
            $pdf->WriteHTML($PDFContent);
            $pdf->Output($file_name, 'I'); // 'I' for inline view in browser
        }
    }
    
if (!function_exists('get_last_ppa_approval_action')) {
    function get_last_ppa_approval_action($entry_id, $staff_id)
    {
        $CI =& get_instance();
        return $CI->db
            ->where('entry_id', $entry_id)
            ->where('staff_id', $staff_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('ppa_approval_trail')
            ->row(); // returns latest action by that staff for that PPA
    }
}

if (!function_exists('get_last_ppa_approval_action_midterm')) {
    function get_last_ppa_approval_action_midterm($entry_id, $staff_id)
    {
        $CI =& get_instance();
        return $CI->db
            ->where('entry_id', $entry_id)
            ->where('staff_id', $staff_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('ppa_approval_trail_midterm')
            ->row(); // returns latest action by that staff for that PPA
    }
}
if (!function_exists('pa_settings')) {
    function ppa_settings()
    {
        $CI =& get_instance();
        return $CI->db->get('ppa_configs')->row();
            
}

// function generate_weeks_of_year($year = null) {
//     $year = $year ?: date('Y');
//     $weeks = [];
//     $weekNum = 1;

//     for ($month = 1; $month <= 12; $month++) {
//         // Start from the 1st of the current month
//         $monthStart = new DateTime("$year-$month-01");

//         for ($week = 1; $week <= 4; $week++) {
//             $weekStart = clone $monthStart;
//             $weekStart->modify('+'.(($week - 1) * 5).' days');

//             $weekEnd = clone $weekStart;
//             $weekEnd->modify('+4 days'); // 5-day work week

//             // Prevent overflow to next month
//             if ($weekStart->format('m') != $month) break;
//             if ($weekEnd->format('m') != $month) {
//                 $weekEnd = new DateTime($year . '-' . $month . '-' . $weekStart->format('t'));
//             }

//             $weeks[] = [
//                 'label' => "Week-{$week} " . $weekStart->format('M Y'),
//                 'start' => $weekStart->format('Y-m-d'),
//                 'end'   => $weekEnd->format('Y-m-d'),
//                 'week'  => $weekNum++
//             ];
//         }
//     }

//     return $weeks;
// }
function generate_weeks_of_year($year = null) {
    $year = $year ?: date('Y');
    $weeks = [];
    $weekNum = 1;

    for ($month = 1; $month <= 12; $month++) {
        // Get first Monday of the month
        $firstDay = new DateTime("$year-$month-01");
        if ($firstDay->format('N') != 1) {
            $firstDay->modify('next Monday');
        }

        $weekStart = clone $firstDay;

        for ($i = 1; $i <= 4; $i++) {
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+4 days'); // Monday to Friday

            // Prevent overflow to next month
            if ($weekStart->format('m') != $month || $weekEnd->format('m') != $month) break;

            $weeks[] = [
                'label' => "Week-{$i} " . $weekStart->format('M Y') . " ({$weekStart->format('Y-m-d')} - {$weekEnd->format('Y-m-d')})",
                'start' => $weekStart->format('Y-m-d'),
                'end'   => $weekEnd->format('Y-m-d'),
                'week'  => $weekNum++
            ];

            $weekStart->modify('+7 days'); // Move to next Monday
        }
    }

    return $weeks;
}

}
function status_badge($status) {
    switch ((int)$status) {
        case 1: return '<span class="badge bg-warning">Pending</span>';
        case 2: return '<span class="badge bg-success">Done</span>';
        case 3: return '<span class="badge bg-info">Next Week</span>';
        case 4: return '<span class="badge bg-danger">Cancelled</span>';
        default: return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function get_status_text($status) {
    switch ((int)$status) {
        case 1: return 'Pending';
        case 2: return 'Done';
        case 3: return 'Next Week';
        case 4: return 'Cancelled';
        default: return 'Unknown';
    }
}
if (!function_exists('get_week_range')) {
    function get_week_range($week_start_date) {
        $start = date('F j', strtotime($week_start_date));
        $end = date('F j, Y', strtotime($week_start_date . ' +4 days'));
        return "$start to $end";
    }
}

if (!function_exists('get_week_label')) {
    function get_week_label($week_start_date) {
        $week_number = ceil(date('j', strtotime($week_start_date)) / 7);
        $month = date('M', strtotime($week_start_date));
        $year = date('Y', strtotime($week_start_date));
        return "Week-$week_number $month $year";
    }
}

if (!function_exists('days_to_ppa_deadline')) {
    function days_to_ppa_deadline()
    {
        $CI = &get_instance();
        $deadline_row = $CI->db->select('ppa_deadline')->get('ppa_configs')->row();

        if (!$deadline_row || empty($deadline_row->ppa_deadline)) {
            return null; // or return a default value if preferred
        }

        $deadline = new DateTime($deadline_row->ppa_deadline);
        $today = new DateTime();

        // Returns signed difference in days: positive if future, negative if past
        return (int) $today->diff($deadline)->format('%r%a');
    }
}

if (!function_exists('days_to_midterm_deadline')) {
    function days_to_midterm_deadline()
    {
        $CI = &get_instance();
        $deadline_row = $CI->db->select('mid_term_deadline')->get('ppa_configs')->row();

        if (!$deadline_row || empty($deadline_row->mid_term_deadline)) {
            return null; // or return a default value if preferred
        }

        $deadline = new DateTime($deadline_row->mid_term_deadline);
        $today = new DateTime();

        // Returns signed difference in days: positive if future, negative if past
        return (int) $today->diff($deadline)->format('%r%a');
    }
}

if (!function_exists('days_to_endterm_deadline')) {
    function days_to_endterm_deadline()
    {
        $CI = &get_instance();
        $deadline_row = $CI->db->select('end_term_deadline')->get('ppa_configs')->row();

        if (!$deadline_row || empty($deadline_row->end_term_deadline)) {
            return null; // or return a default value if preferred
        }

        $deadline = new DateTime($deadline_row->end_term_deadline);
        $today = new DateTime();

        // Returns signed difference in days: positive if future, negative if past
        return (int) $today->diff($deadline)->format('%r%a');
    }
}

function is_unit_lead($staff_id){
    $CI =& get_instance();
        $query = $CI->db
            ->where('staff_id', $staff_id)
            ->limit(1)
            ->get('units')
            ->num_rows(); 
    if($query>0){
        return TRUE;
    }
    else{
        return FALSE;
    }
 //dd($query);

}

if (!function_exists('calculate_endterm_overall_rating')) {
    /**
     * Calculate overall endterm rating based on objectives
     * Formula: Sum of (Appraiser's Rating * Weight) / 5
     * 
     * @param array|object $objectives Endterm objectives with appraiser_rating and weight
     * @return array Contains 'score', 'category', 'label', 'annotation'
     */
    function calculate_endterm_overall_rating($objectives) {
        if (empty($objectives)) {
            return [
                'score' => 0,
                'category' => 'not_rated',
                'label' => 'Not Rated – New in Position',
                'annotation' => 'Not Rated – New in Position'
            ];
        }

        // Ensure objectives is an array
        if (is_string($objectives)) {
            $objectives = json_decode($objectives, true);
        } elseif (is_object($objectives)) {
            $objectives = json_decode(json_encode($objectives), true);
        }

        if (!is_array($objectives)) {
            $objectives = [];
        }

        $total_score = 0;

        foreach ($objectives as $obj) {
            $rating = isset($obj['appraiser_rating']) ? (float)$obj['appraiser_rating'] : 0;
            $weight = isset($obj['weight']) ? (float)$obj['weight'] : 0;

            if ($rating > 0 && $weight > 0) {
                // Multiply Appraiser's Rating * Weight
                $total_score += ($rating * $weight);
            }
        }

        // Calculate overall score: divide the overall total by 5
        $overall_score = $total_score > 0 ? ($total_score / 5) : 0;
        $overall_score = round($overall_score, 2);

        // Categorize the score
        if ($overall_score >= 80) {
            $category = 'outstanding';
            $label = 'Outstanding Performance';
            $annotation = 'Outstanding Performance - Overall performance is superior and significantly exceeds expectations';
        } elseif ($overall_score >= 51) {
            $category = 'satisfactory';
            $label = 'Satisfactory Performance';
            $annotation = 'Satisfactory Performance - Overall performance is consistent with expectations';
        } elseif ($overall_score > 0) {
            $category = 'poor';
            $label = 'Poor Performance';
            $annotation = 'Poor Performance - Overall Performance fails to meet the expectations';
        } else {
            $category = 'not_rated';
            $label = 'Not Rated – New in Position';
            $annotation = 'Not Rated – New in Position';
        }

        return [
            'score' => $overall_score,
            'category' => $category,
            'label' => $label,
            'annotation' => $annotation
        ];
    }
}

}
