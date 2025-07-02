<?php
defined('BASEPATH') or exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;
class Jobs extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
       // $this->load->model("midterm_mdl", 'midterm_mdl');
    }
 
   //render user accounts automatically
   public function manage_accounts()
   {
       $accts = 0;
   
       // Step 1: Subquery to get latest contract per staff
       $subquery = "
           SELECT MAX(staff_contract_id) AS latest_contract_id
           FROM staff_contracts
           GROUP BY staff_id
       ";
   
       // Step 2: Main query to get staff who don't yet have accounts
       $sql = "
           SELECT s.*, sc.division_id, sc.staff_contract_id
           FROM staff s
           JOIN staff_contracts sc ON s.staff_id = sc.staff_id
           WHERE sc.staff_contract_id IN ($subquery)
             AND s.work_email != ''
             AND sc.status_id IN (1, 2, 7)
             AND s.staff_id NOT IN (
                 SELECT DISTINCT auth_staff_id FROM user
             )
       ";
   
       $staffs = $this->db->query($sql)->result();
   
       // Step 3: Create accounts
       foreach ($staffs as $staff) {
           $users = [
               'name' => $staff->lname . ' ' . $staff->fname,
               'status' => 1,
               'auth_staff_id' => $staff->staff_id,
               'password' => $this->argonhash->make(setting()->default_password),
               'role' => 17
           ];
   
           $this->db->replace('user', $users);
           $accts += $this->db->affected_rows();
       }
   
       // Step 4: Optionally disable old accounts
       $this->disable_accounts();
       //renewed accounts
       $this->enable_accounts();
   
       // Step 5: Return result
       echo json_encode([
           'msg' => "{$accts} Staff Accounts Created.",
           'type' => 'info'
       ]);
   }
   
   public function disable_accounts()
   {
       $disabled_count = 0;
   
       // Subquery to get latest contract per staff
       $subquery = "
           SELECT MAX(staff_contract_id) AS latest_contract_id
           FROM staff_contracts
           GROUP BY staff_id
       ";
   
       // Get staff whose latest contracts are not active (not in 1,2,7)
       $sql = "
           SELECT s.*, sc.division_id, sc.staff_contract_id
           FROM staff s
           JOIN staff_contracts sc ON s.staff_id = sc.staff_id
           WHERE sc.staff_contract_id IN ($subquery)
             AND s.work_email != ''
             AND sc.status_id NOT IN (1, 2, 7)
       ";
   
       $staffs = $this->db->query($sql)->result();
   
       // Disable matching user accounts
       foreach ($staffs as $staff) {
           $this->db->where('auth_staff_id', $staff->staff_id);
           $this->db->update('user', ['status' => 0]); // 0 = disabled
           $disabled_count += $this->db->affected_rows();
       }
   
       // Output JSON message
       echo json_encode([
           'msg' => "{$disabled_count} Staff Accounts Disabled.",
           'type' => 'info'
       ]);
   }
   
   public function enable_accounts()
   {
       $disabled_count = 0;
   
       // Subquery to get latest contract per staff
       $subquery = "
           SELECT MAX(staff_contract_id) AS latest_contract_id
           FROM staff_contracts
           GROUP BY staff_id
       ";
   
       // Get staff whose latest contracts are not active (not in 1,2,7)
       $sql = "
           SELECT s.*, sc.division_id, sc.staff_contract_id
           FROM staff s
           JOIN staff_contracts sc ON s.staff_id = sc.staff_id
           WHERE sc.staff_contract_id IN ($subquery)
             AND s.work_email != ''
             AND sc.status_id IN (1, 2, 7)
       ";
   
       $staffs = $this->db->query($sql)->result();
   
       // Disable matching user accounts
       foreach ($staffs as $staff) {
           $this->db->where('auth_staff_id', $staff->staff_id);
           $this->db->update('user', ['status' => 1]); // 0 = enabled accounts
           $disabled_count += $this->db->affected_rows();
       }
   
       // Output JSON message
       echo json_encode([
           'msg' => "{$disabled_count} Staff Accounts Disabled.",
           'type' => 'info'
       ]);
   }
//get the date difference for contract status
// Improved dateDiff function using DateTime and DateInterval
function dateDiff($date1, $date2) {
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    // %r gives the sign and %a gives the total number of days
    return (int)$d1->diff($d2)->format('%r%a');
}

// Contract status job.
public function mark_due_contracts() {
    // Calculate current date once
    $today = new DateTime();
    
    // Use IN for cleaner SQL
    $sql = "SELECT staff_contract_id, end_date, staff_id FROM staff_contracts WHERE status_id IN (1,2)";
    $result = $this->db->query($sql)->result_array();

    foreach ($result as $row) {
        $staff_contract_id = $row['staff_contract_id'];
        $staff_id = $row['staff_id'];
        $end_date = $row['end_date'];
        
        // Using DateTime::diff directly for efficiency
        $dateDiff = (int)$today->diff(new DateTime($end_date))->format('%r%a');

      // dd($dateDiff);
        
        $data['name'] = staff_name($staff_id);
        $data['date2'] = $end_date;
        $data['remaining_days'] = $dateDiff;

        // Update the flag for the staff member (ensure LIMIT is correct for your use-case)
        $this->db->query("UPDATE staff SET flag = 1 WHERE staff_id = $staff_id");

        if ($dateDiff > 0 && $dateDiff <= 90) {
            //due contracts
            $data['subject'] = "Contract Due for Renewal Notice";
            $supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
            $first_supervisor_mail = staff_details($supervisor_id)->work_email;
            //$copied_mails = settings()->contracts_status_copied_emails;
            $data['email_to'] = staff_details($staff_id)->work_email . ';' . $first_supervisor_mail.';'.settings()->email;
            $data['body'] = $this->load->view('due_contract', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            $entry_id = $staff_id.'-DU-'.date('Y-m-d');
            golobal_log_email('system', $data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date2'], $dispatch,md5($entry_id));
            $this->db->query("UPDATE staff_contracts SET status_id = 2 WHERE staff_contract_id = $staff_contract_id");
        } elseif ($dateDiff <= 0) {
            //expired
            $data['subject'] = "Expired Contract Notice";
            $supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
            $first_supervisor_mail = staff_details($supervisor_id)->work_email;
            $copied_mails = settings()->contracts_status_copied_emails;
            $data['email_to'] = staff_details($staff_id)->work_email . ';' . $first_supervisor_mail.';'.settings()->email.';'.$copied_mails;
            $data['body'] = $this->load->view('expired_contract', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            $entry_id = $staff_id.'-EX-'.date('Y-m-d');
            golobal_log_email('system', $data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date2'], $dispatch,md5($entry_id));
            $this->db->query("UPDATE staff_contracts SET status_id = 3 WHERE staff_contract_id = $staff_contract_id");
        } elseif ($dateDiff >90) {
            $this->db->query("UPDATE staff_contracts SET status_id = 1 WHERE staff_contract_id = $staff_contract_id");
        }
    }
}

         
public function staff_birthday() {
    $todays = $this->staff_mdl->getBirthdays(0);

    foreach ($todays as $row) {
        // Try to create a DateTime object from the staff member's date_of_birth.
        try {
            $dob = new DateTime($row->date_of_birth);
        } catch (Exception $e) {
            // Skip if the date_of_birth is not valid.
            continue;
        }

        // Get today's date as a DateTime object.
        $today = new DateTime();
        // Calculate the age.
        $age = $today->diff($dob)->y;

        // Check if the staff member is 18 years or older.
        if ($age >= 18) {
            $data['subject'] = "AFRICA CDC Birthday Greetings";
            $data['email_to'] = $row->work_email.';'.settings()->email;
            $data['name'] = staff_name($row->staff_id);
            $staff_id = $row->staff_id;
            $data['date_2'] = $today->format('Y-m-d');
            // Load the view and return its output as a string.
            $data['body'] = $this->load->view('staff_bd', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            $entry_id = $staff_id.'-BD-'.date('Y-m-d');
            golobal_log_email('system',$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
        }
    }
}

//cron register runs once a day
public function cron_register(){

    //run everyday at 23:00
    $this->staff_birthday();
    //run everyday at 23:10
    $this->manage_accounts();
    //run everyday at 23:30
    $this->mark_due_contracts();
    
    
}

//   * * * * * cd /var/www/staff_tracker && php index.php person send_mails. runs every minute.
    public function send_mails()
    {
        // Remove test/dummy emails
        $this->db->query("DELETE FROM `email_notifications` WHERE `email_to` LIKE '%xxx%'");

        $today = date('Y-m-d');
        // Only select emails scheduled for today, not already sent, and not test emails
        $messages = $this->db->query("SELECT * FROM email_notifications WHERE next_dispatch LIKE '$today%' AND status != '1' AND email_to NOT LIKE 'xx%'")->result();

        $counter = 0;

        if (!empty($messages)) {
            foreach ($messages as $message) {
                // Try to lock this notification for sending
                $this->db->query("UPDATE email_notifications SET status = '2' WHERE id = $message->id AND status != '1' AND status != '2'");
                if ($this->db->affected_rows() == 0) {
                    // Another process already locked or sent this notification
                    continue;
                }

                $body = $message->body;
                $to = $message->email_to;
                $subject = $message->subject;
                $id = $message->id;
                $next_run = $this->getNextRunDate($message->end_date);
                $next_run = $next_run->format('Y-m-d');

                $sending = push_email($to, $subject, $body, $id, $next_run);

                if ($sending) {
                    echo "Message sent to " . $to . "\n";
                    $today_check = date("Y-m-d");
                    $status = ($today_check == $next_run) ? 1 : 0;
                    $this->db->query("UPDATE `email_notifications` SET `status` = '$status', next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");
                    // Clean up old sent notifications
                    $this->db->query("DELETE FROM email_notifications WHERE next_dispatch < DATE_SUB(NOW(), INTERVAL 1 WEEK) AND status = '1'");
                } else {
                    echo "Failed to send message to " . $to . "\n";
                    $this->db->query("UPDATE `email_notifications` SET `status` = '0', next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");
                }

                if ($sending) {
                    $counter++;
                    // Delay 10 seconds after each email
                    sleep(10);

                    // Delay 30 seconds after every 20 emails
                    if ($counter % 20 == 0) {
                        log_message('info', "Reached $counter emails. Pausing for 30 seconds.");
                        sleep(30);
                    }
                }
            }
        } else {
            echo "No messages to send.\n";
        }
    }
//   * * * * * cd /var/www/staff_tracker && php index.php person send_mails. runs every minute.
    public function send_instant_mails()
    {
        $this->db->query("DELETE FROM `email_notifications` WHERE `email_to` LIKE '%xxx%'");
        $today = date('Y-m-d');
        $messages = $this->db->query("SELECT * FROM email_notifications WHERE next_dispatch like '$today%' and status = '0' and (subject like'PPA%' OR subject like '%Midterm%') and email_to NOT LIKE 'xx%'")->result();

        $counter = 0;
        if (count($messages) > 0) {
            foreach ($messages as $message) {
                // Try to lock this notification for sending
                $this->db->query("UPDATE email_notifications SET status = '2' WHERE id = $message->id AND status = '0'");
                if ($this->db->affected_rows() == 0) {
                    // Another process already locked or sent this notification
                    continue;
                }

                $body = $message->body;
                $to = $message->email_to;
                $subject = $message->subject;
                $id = $message->id;
                $next_run = $this->getNextRunDate($message->end_date);
                $next_run = $next_run->format('Y-m-d');

                $sending = push_email($to, $subject, $body, $id, $next_run);
                if ($sending) {
                    echo "Message sent to " . $to . "\n";
                    $today = date("Y-m-d");

                    $status = ($today == $next_run) ? 1 : 0;
                    $this->db->query("UPDATE `email_notifications` SET `status` = '$status',next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");
                    $this->db->query("DELETE FROM email_notifications WHERE next_dispatch < DATE_SUB(NOW(), INTERVAL 1 WEEK) AND status = '1'");
                } else {
                    echo "Failed to send message to " . $to . "\n";
                    $this->db->query("UPDATE `email_notifications` SET `status` = '0',next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");
                }

                if ($sending) {
                    $counter++;
                    // Delay 1 minute after each email
                    sleep(10);
                    // Delay 5 minutes after every 20 emails
                    if ($counter % 20 == 0) {
                        log_message('info', "Reached $counter emails. Pausing for 5 minutes.");
                        sleep(30); // 5 minutes = 300 seconds
                    }
                }
            }
        } else {
            echo "No messages to send.\n";
        }
    }
    public function getNextRunDate($end) { 
        $current = new DateTime();
        // Convert $end to a DateTime object if it's a string.
        $contractEnd = is_string($end) ? new DateTime($end) : $end;
    
        // If the contract end date is today, return it as the final run date.
        if ($contractEnd->format('Y-m-d') === $current->format('Y-m-d')) {
            return $contractEnd;
        }
      
        // Calculate the difference in days between now and the contract end date.
        $diffDays = (int)$current->diff($contractEnd)->format('%r%a');
        
        // Define the thresholds (in days before the contract end).
        $thresholds = [90, 30, 21, 14, 7, 6, 5, 4, 3, 2, 1];
        
        // Find the next upcoming threshold date.
        foreach ($thresholds as $threshold) {
            if ($diffDays > $threshold) {
                // Calculate when the contract will have $threshold days remaining.
                $nextRun = clone $contractEnd;
                $nextRun->modify("-{$threshold} days");
                if ($nextRun > $current) {
                    return $nextRun;
                }
            }
        }
        
        // Fallback: if no threshold is found, schedule for the contract end date.
        return $contractEnd;
    }

   public function get_ms_token()
    {
     $url = "https://login.microsoftonline.com/{$_ENV['TENANT_ID']}/oauth2/v2.0/token";
 
     $body = [
         'grant_type'    => 'client_credentials',
         'client_id'     => $_ENV['CLIENT_ID'],
         'client_secret' => $_ENV['CLIENT_SEC_VALUE'],
         'scope'         => 'https://outlook.office365.com/.default'
     ];
 
     $headers = ['Content-Type: application/x-www-form-urlencoded'];
 
     $response = curl_send_post($url, $body, $headers);
 
     if (!isset($response->access_token)) {
         die("Error fetching OAuth token: " . json_encode($response));
     }
 
    return $response->access_token;
 }

 public function send_ms_mail($to, $subject, $message, $id, $next_run)
 {
     $settings = $this->db->query('SELECT * FROM setting')->row();

     // Get OAuth2 Token
     $oauth_token = $this->get_ms_token(); 
     //dd($oauth_token);

     if (!$oauth_token) {
         log_message('error', "OAuth2 token retrieval failed.");
         return false;
     }

     try {
         $mailer = new PHPMailer(true);
         $mailer->isSMTP();
         $mailer->SMTPDebug = 3; // Enable debugging for troubleshooting
         $mailer->Host       = 'smtp.office365.com';
         $mailer->SMTPAuth   = true;
         $mailer->AuthType   = 'SMTP';
         $mailer->Port       = 587;
         $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

         // Provide OAuth Token using XOAUTH2 Header Format
         $oauth_token_encoded = base64_encode("user={$settings->email}\001auth=Bearer {$oauth_token}\001\001");
         
         $mailer->Username   = $settings->email;
         $mailer->Password   = $settings->password;  // PHPMailer will use this as the token

         // Set email details
         $mailer->setFrom($settings->email, $settings->title);
         $mailer->addAddress(trim($to));
         $mailer->Subject = $subject;
         $mailer->Body    = $message;
         $mailer->isHTML(true);

         if ($mailer->send()) {
             log_message('info', "Email sent successfully to $to (ID: $id)");
             return true;
         } else {
             log_message('error', "Email sending failed: " . $mailer->ErrorInfo);
             return false;
         }
     } catch (Exception $e) {
       // dd($e->getMessage());
         log_message('error', "Email sending exception: {$e->getMessage()}");
         return false;
     }
 }

 public function notify_unsubmitted_ppas()
 {
     $current_period = str_replace(' ', '-', current_period());
 
     $deadline = $this->db->select('ppa_deadline')->get('ppa_configs')->row()->ppa_deadline;
   
 
     //Only notify if deadline is in 15 days or less
$days_remaining = days_to_ppa_deadline();

     if ($days_remaining !== null && $days_remaining <= 15) {
         $staff_list = $this->per_mdl->get_staff_without_ppa($current_period);

        // dd($staff_list);
 
         foreach ($staff_list as $staff) {
             $data = [
                 'name' => $staff->title . ' ' . $staff->fname . ' ' . $staff->lname,
                 'period' => $current_period,
                 'deadline' => $deadline,
                 'type' => 'ppa_reminder',
                 'subject' => "Staff PPA Reminder: Submit your PPA ($current_period)",
                 'email_to' => $staff->work_email.';'.settings()->email
             ];
 
             $data['body'] = $this->load->view('staff_reminder', $data, true);
 
             $entry_log_id = md5($staff->staff_id . '-PPAREM-' . date('Y-m-d'));
             golobal_log_email('Staff Portal System', $data['email_to'], $data['body'], $data['subject'], $staff->staff_id, date('Y-m-d'),
             date('Y-m-d'), $entry_log_id);
         }
     }
 } 

 //notify staff on midterms
 public function notify_unsubmitted_midterms()
 {
     $current_period = str_replace(' ', '-', current_period());
     $deadline = $this->db->select('mid_term_deadline')->get('ppa_configs')->row()->mid_term_deadline;

     // Only notify if deadline is in 40 days or less
     $days_remaining = days_to_midterm_deadline();

     if ($days_remaining !== null && $days_remaining <= 40) {
         $staff_list = $this->midterm_mdl->get_staff_without_midterm($current_period);

         foreach ($staff_list as $staff) {
             $entry_log_id = md5($staff->staff_id . '-empMIDTERMREM-' . date('Y-m-d'));

             // Check if a notification for this staff and period has already been logged today using only entry_id
             $exists = $this->db
                 ->where('entry_id', $entry_log_id)
                 ->count_all_results('email_notifications');

             if ($exists > 0) {
                 // Skip sending duplicate notification
                 continue;
             }

             $data = [
                 'name' => $staff->title . ' ' . $staff->fname . ' ' . $staff->lname,
                 'period' => $current_period,
                 'deadline' => $deadline,
                 'type' => 'Midterm Reminder',
                 'subject' => "Midterm Review Reminder: Submit your Midterm ($current_period)",
                 'email_to' => $staff->work_email.';'.settings()->email,
             ];

             $data['body'] = $this->load->view('staff_reminder_midterm', $data, true);

             golobal_log_email(
                 'Staff Portal System',
                 $data['email_to'],
                 $data['body'],
                 $data['subject'],
                 $staff->staff_id,
                 date('Y-m-d'),
                 date('Y-m-d'),
                 $entry_log_id
             );
         }
     }
 }
//reminder for ppa approval
public function notify_supervisors_pending_ppas()
{


    $current_period = str_replace(' ', '-', current_period());
    $deadline = $this->db->get('ppa_configs')->row()->ppa_deadline;

    // Get supervisors with any pending PPA
    $supervisors = $this->per_mdl->get_supervisors_with_pending_ppas($current_period);
    

    foreach ($supervisors as $supervisor) {
        $pending_list = $this->per_mdl->get_pending_by_supervisor_with_staff($supervisor->supervisor_id);

        if (empty($pending_list)) continue;

        $data = [
            'supervisor_name' => $supervisor->title . ' ' . $supervisor->fname . ' ' . $supervisor->lname,
            'period'          => $current_period,
            'deadline'        => $deadline,
            'pending_list'    => $pending_list,
            'subject'         => "Reminder: Pending PPA Approvals for {$current_period}",
            'email_to'        => $supervisor->work_email.';'.settings()->email
        ];

        // Render email view
        $data['body'] = $this->load->view('supervisor_reminder', $data,true);

        // Log and send email
        $entry_log_id = md5($supervisor->supervisor_id . '-SUPPPAREM-' . date('Y-m-d'));
        golobal_log_email(
            'Staff Portal System',
            $data['email_to'],
            $data['body'],
            $data['subject'],
            $supervisor->supervisor_id,
            date('Y-m-d'),
            date('Y-m-d'),
            $entry_log_id
        );

       
    }
    $this->notify_unsubmitted_ppas();
    $this->db->query("DELETE FROM `email_notifications` WHERE `email_to` LIKE '%xxx%'");
    
}

//reminder for midterm approval
public function notify_supervisors_pending_midterms()
{
    $current_period = str_replace(' ', '-', current_period());
    $deadline = $this->db->get('ppa_configs')->row()->mid_term_deadline;

    // Get supervisors with any pending Midterm
    $supervisors = $this->midterm_mdl->get_supervisors_with_pending_midterms($current_period);

    foreach ($supervisors as $supervisor) {
        $pending_list = $this->midterm_mdl->get_pending_by_supervisor_with_staff($supervisor->supervisor_id);

        if (empty($pending_list)) continue;

        $data = [
            'supervisor_name' => $supervisor->title . ' ' . $supervisor->fname . ' ' . $supervisor->lname,
            'period'          => $current_period,
            'deadline'        => $deadline,
            'pending_list'    => $pending_list,
            'subject'         => "Reminder: Pending Midterm Approvals for {$current_period}",
            'email_to'        => $supervisor->work_email.';'.settings()->email
        ];

        // Render email view
        $data['body'] = $this->load->view('supervisor_reminder_midterm', $data, true);

        // Make entry_log_id unique per supervisor per period per day
        $entry_log_id = md5($supervisor->supervisor_id . '-SUPMIDREM-' . $current_period . '-' . date('Y-m-d'));

        // Check if this email has already been logged today
        $already_sent = $this->db->where('entry_id', $entry_log_id)
                                 ->get('email_notifications')
                                 ->num_rows() > 0;

        if (!$already_sent) {
            golobal_log_email(
                'Staff Portal System',
                $data['email_to'],
                $data['body'],
                $data['subject'],
                $supervisor->supervisor_id,
                date('Y-m-d'),
                date('Y-m-d'),
                $entry_log_id
            );
        }
    }

    // Now, call notify_unsubmitted_midterms() ONCE, outside the loop
    $this->notify_unsubmitted_midterms();

    $this->db->query("DELETE FROM `email_notifications` WHERE `email_to` LIKE '%xxx%'");
}

public function update_latest_contracts_in_ppa()
{
    // Subquery to get latest contract ID per staff
    $subquery = $this->db
        ->select('staff_id, MAX(staff_contract_id) AS latest_contract_id', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    // Join latest contracts to staff
    $this->db->select('s.staff_id, sc.staff_contract_id, sc.first_supervisor, sc.second_supervisor');
    $this->db->from('staff_contracts sc');
    $this->db->join('staff s', 'sc.staff_id = s.staff_id');
    $this->db->join("($subquery) latest_contracts", 
        'sc.staff_id = latest_contracts.staff_id AND sc.staff_contract_id = latest_contracts.latest_contract_id', 
        'inner');

    $latest = $this->db->get()->result();

    //dd($latest);

    $updated_count = 0;
    foreach ($latest as $row) {
        $this->db->where('staff_id', $row->staff_id);
        $success = $this->db->update('ppa_entries', [
            'staff_contract_id' => $row->staff_contract_id,
            'midterm_supervisor_1' => $row->first_supervisor,
            'midterm_supervisor_2' => $row->second_supervisor
        ]);
        if ($success) {
            $updated_count++;
            $msg = "[UPDATE] staff_id: {$row->staff_id}, contract_id: {$row->staff_contract_id}, supervisor_1: {$row->supervisor_id}, supervisor_2: {$row->supervisor2_id}";
            log_message('info', $msg);
            if (php_sapi_name() === 'cli') {
                echo $msg . PHP_EOL;
            }
        } else {
            $msg = "[FAILED] staff_id: {$row->staff_id} update failed.";
            log_message('error', $msg);
            if (php_sapi_name() === 'cli') {
                echo $msg . PHP_EOL;
            }
        }
    }

    $summary = "[CRON] staff_contract_id and supervisors in PPA entries updated from latest contracts. Total updated: $updated_count";
    log_message('info', $summary);
    if (php_sapi_name() === 'cli') {
        echo $summary . PHP_EOL;
    }
}

public function update_midterm_supervisors()
{
    // Subquery to get latest contract ID per staff
    $subquery = $this->db
        ->select('staff_id, MAX(staff_contract_id) AS latest_contract_id', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    // Join latest contracts to staff
    $this->db->select('s.staff_id, sc.staff_contract_id, sc.first_supervisor, sc.second_supervisor');
    $this->db->from('staff_contracts sc');
    $this->db->join('staff s', 'sc.staff_id = s.staff_id');
    $this->db->join("($subquery) latest_contracts", 
        'sc.staff_id = latest_contracts.staff_id AND sc.staff_contract_id = latest_contracts.latest_contract_id', 
        'inner');

    $latest = $this->db->get()->result();

    //dd($latest);

    $updated_count = 0;
    foreach ($latest as $row) {
        $this->db->where('staff_id', $row->staff_id);
        $success = $this->db->update('ppa_entries', [
            
            'midterm_supervisor_1' => $row->first_supervisor,
            'midterm_supervisor_2' => NULL
        ]);
        if ($success) {
            $updated_count++;
            $msg = "[UPDATE] staff_id: {$row->staff_id}, contract_id: {$row->staff_contract_id}, supervisor_1: {$row->supervisor_id}, supervisor_2: {$row->supervisor2_id}";
            log_message('info', $msg);
            if (php_sapi_name() === 'cli') {
                echo $msg . PHP_EOL;
            }
        } else {
            $msg = "[FAILED] staff_id: {$row->staff_id} update failed.";
            log_message('error', $msg);
            if (php_sapi_name() === 'cli') {
                echo $msg . PHP_EOL;
            }
        }
    }

    $summary = "[CRON] staff_contract_id and supervisors in PPA entries updated from latest contracts. Total updated: $updated_count";
    log_message('info', $summary);
    if (php_sapi_name() === 'cli') {
        echo $summary . PHP_EOL;
    }
}
//midterm

public function add_midterm_fields_to_ppa_entries($drop = false)
{
    // Field definitions with insert order preserved
    $fields = [
        'midterm_objectives'         => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_competency'         => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_achievements'       => "TEXT",
        'midterm_non_achievements'   => "TEXT",
        'midterm_comments'           => "TEXT",
        'midterm_training_review'    => "TEXT",
        'midterm_recommended_skills' => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_training_contributions' => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_recommended_trainings' => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_recommended_trainings_details' => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin",
        'midterm_rating_by'          => "INT DEFAULT NULL",
        'midterm_sign_off'           => "TINYINT(1) DEFAULT 0",
        'midterm_draft_status'       => "TINYINT(1) DEFAULT 1",
        'midterm_created_at'         => "DATETIME DEFAULT NULL",
        'midterm_updated_at'         => "DATETIME DEFAULT NULL",
        'midterm_supervisor_1'       => "INT DEFAULT NULL",
        'midterm_supervisor_2'       => "INT DEFAULT NULL"
    ];
    

    if ($drop) {
        foreach ($fields as $field => $definition) {
            $exists = $this->db->query("SHOW COLUMNS FROM `ppa_entries` LIKE '$field'")->num_rows();

            if ($exists > 0) {
                $sql = "ALTER TABLE `ppa_entries` DROP COLUMN `$field`";
                $this->db->query($sql);
                echo "🗑️ Dropped column: <strong>$field</strong><br>";
            } else {
                echo "⚠️ Column does not exist: <strong>$field</strong><br>";
            }
        }
    } else {
        $previous = 'updated_at';
        foreach ($fields as $field => $definition) {
            $exists = $this->db->query("SHOW COLUMNS FROM `ppa_entries` LIKE '$field'")->num_rows();

            if ($exists === 0) {
                $sql = "ALTER TABLE `ppa_entries` ADD `$field` $definition AFTER `$previous`";
                $this->db->query($sql);
                echo "✅ Added column: <strong>$field</strong><br>";
            } else {
                echo "ℹ️ Column already exists: <strong>$field</strong><br>";
            }

            $previous = $field;
        }
    }
}





    
}

 


