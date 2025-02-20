<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Errors extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
   // * * * * * cd /var/www/staff_tracker && php index.php person send_mails
   public function send_mails()
   {
       $messages = $this->db->query("SELECT * FROM email_notifications WHERE status = '-1' or status=0")->result();

       // Check if there are any messages to process
       if (count($messages) > 0) {
           foreach ($messages as $message) {
               $body = $message->body;
               $to = $message->email_to;
               $subject = $message->subject;
               $id = $message->id;

               try {
                   $sending = send_email_async($to, $subject, $body, $id);
                   if ($sending) {
                       echo "Message sent to " . $to . "\n";

                      
                    $this->db->query('DELETE FROM email_notifications WHERE created_at < NOW() - INTERVAL 3 DAY');
                   } else {
                       echo "Failed to send message to " . $to . "\n";
                   }
               } catch (Exception $e) {
                   echo "Error sending email to " . $to . ": " . $e->getMessage() . "\n";
               }
           }
       } else {
           echo "No messages to send.\n";
       }
   }
   public function acdc_users($job=FALSE){
    $final=array();
    $staffs =  $this->db->query("SELECT staff.*, staff_contracts.division_id,staff_contracts.staff_contract_id from staff join staff_contracts on staff.staff_id=staff_contracts.staff_id where work_email!='' and staff_contracts.status_id in (1,2) and staff.staff_id not in (SELECT DISTINCT auth_staff_id from user)")->result();
      foreach ($staffs as $staff):
        $users['email'] = $staff->work_email;
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
}
