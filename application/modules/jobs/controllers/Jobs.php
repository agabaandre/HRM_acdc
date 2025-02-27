<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Jobs extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
 
   //render user accounts automatically
public function manage_accounts(){
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

     echo json_encode($msg);

     $this->disbale_accounts();

     }

     public function disbale_accounts(){
        $final=array();
        $staffs =  $this->db->query("SELECT staff.*, staff_contracts.division_id,staff_contracts.staff_contract_id from staff join staff_contracts on staff.staff_id=staff_contracts.staff_id where work_email!='' and staff_contracts.status_id NOT IN (1,2,7)")->result();
          foreach ($staffs as $staff):
            $data['status'] =1;
            $id = $staff->staff_id;
            $this->db->where('auth_staff_id',"$id");
            $this->db->update('user', $data);
          endforeach;
           $accts = $this->db->affected_rows();
         
      
          $msg = array(
            'msg' => $accts .'Staff Accounts Disbaled .',
            'type' => 'info'
          );
          
             echo json_encode($msg);
         }
//get the date difference for contract status
function dateDiff($date1, $date2)
                {
                    $date1_ts = strtotime($date1);
                    $date2_ts = strtotime($date2);
                    $diff = $date2_ts - $date1_ts;
                    return round($diff / 86400);
}
    
//contract status job.            
public function mark_due_contracts(){
    $sql = "SELECT staff_contract_id, end_date, staff_id FROM staff_contracts WHERE status_id = 1 OR status_id = 2 LIMIT 2";
    $result = $this->db->query($sql)->result_array();

    foreach($result as $row){
        $date1 = date('Y-m-d');
        $date2 = $row['end_date'];
        $staff_contract_id = $row['staff_contract_id'];
        $staff_id = $row['staff_id'];
        $data['name'] = staff_name($staff_id);
        $dateDiff = dateDiff($date1, $date2);
        $data['date2'] = $date2;
        $data['remaining_days']= $dateDiff;

       
    

        // Update the flag for the staff member
        $this->db->query("UPDATE staff SET flag = 1 WHERE staff_id = $staff_id LIMIT 8");

        if($dateDiff > 0 && $dateDiff <= 360){
            $data['subject'] = "CONTRACT IS DUE FOR RENEWAL";
            $supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
            //dd($supervisor_id);
			$first_supervisor_mail =staff_details($supervisor_id)->work_email;
			$copied_mails = settings()->contracts_status_copied_emails;
			$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
            // Set the third parameter to true to return the view as a string
            $data['body'] = $this->load->view('due_contract', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            golobal_log_email('system',$data['email_to'], $data['body'], $data['subject'],$staff_id,$data['date2'],$dispatch);
            $this->db->query("UPDATE staff_contracts SET status_id = 2 WHERE staff_contract_id = $staff_contract_id");
        } elseif($dateDiff < 0){
            $data['subject'] = "EXPIRED CONTRACT";
            $supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
			$first_supervisor_mail =staff_details($supervisor_id)->work_email;
			$copied_mails = settings()->contracts_status_copied_emails;
			$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
            // Set the third parameter to true to return the view as a string
            $data['body'] = $this->load->view('expired_contract', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            golobal_log_email('system',$data['email_to'], $data['body'], $data['subject'],$staff_id,$data['date2'],$dispatch);
            $this->db->query("UPDATE staff_contracts SET status_id = 3 WHERE staff_contract_id = $staff_contract_id");
        } elseif($dateDiff > 360){
            $this->db->query("UPDATE staff_contracts SET status_id = 1 WHERE staff_contract_id = $staff_contract_id");
        }
    }
}

         
public function staff_birthday() {
    $todays = $this->staff_mdl->getBirthdaysForToday();

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
            $data['subject'] = "AFRICA CDC BIRTHDAY GREETINGS";
            $data['email_to'] = $row->work_email.';'.settings()->email;
            $data['name'] = staff_name($row->staff_id);
            $staff_id = $row->staff_id;
            $data['date_2'] = $today->format('Y-m-d');
            // Load the view and return its output as a string.
            $data['body'] = $this->load->view('staff_bd', $data, true);
            $dispatch = date('Y-m-d H:i:s');
            golobal_log_email('system',$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch);
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
        $today = date('Y-m-d');
        $messages = $this->db->query("SELECT * FROM email_notifications WHERE next_dispatch like '$today%' and status!='1' and id=1")->result();
        //dd($this->db->last_query());

        // Check if there are any messages to process
        if (count($messages) > 0) {
            foreach ($messages as $message) {
                $body = $message->body;
                $to = $message->email_to;
                $subject = $message->subject;
                $id = $message->id;
                $next_run = $this->getNextRunDate($message->end_date);
                $next_run = $next_run->format('Y-m-d');
               // dd($next_run);
                    $sending = push_email($to, $subject, $body, $id, $next_run);
                    if ($sending) {
                        echo "Test Message sent to " . $to . "\n";
                        $today = date("Y-m-d");

                        if ($today == $next_run) {
                            $status = 1;
                        } else {
                            $status = 0;
                        }

                    $this->db->query("UPDATE `email_notifications` SET `status` = '$status',next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");
                    $this->db->query("DELETE FROM email_notifications WHERE next_dispatch < DATE_SUB(NOW(), INTERVAL 1 WEEK) AND status = '1'");

                    } else {
                        echo "Failed to send message to " . $to . "\n";
                    $this->db->query("UPDATE `email_notifications` SET `status` = '0',next_dispatch = '$next_run' WHERE `email_notifications`.`id` = $id");

                    }
                } 
            }
     else {
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
        $thresholds = [180, 30, 21, 14, 7, 6, 5, 4, 3, 2, 1];
        
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
    
}

 


