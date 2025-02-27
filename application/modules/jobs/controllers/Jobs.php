<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Jobs extends MX_Controller
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
   //render user accounts automatically
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
        $sql= "SELECT staff_contract_id,end_date,staff_id FROM  staff_contracts WHERE status_id=1 || status_id=2";
                $result = $this->db->query($sql)->result_array();                      
                foreach($result as $row){
                                    $date1 = date('Y-m-d');
                                    $date2 = $row['end_date'];
                                    $staff_contract_id = $row['staff_contract_id'];
                                    $staff_id = $row['staff_id'];
                                    $name = staff_name($staff_id);
                                    $dateDiff = dateDiff($date1, $date2);
                    
                     $staff_status = $this->db->query("UPDATE staff SET flag=1 WHERE staff_id=$staff_id");

                    if($dateDiff > 0 && $dateDiff <= 180){
                                    //$status= 'Due';
                                    $data['subject'] ="CONTRACT IS DUE FOR RENEWAL";
                                    $data['email_to'] ="kibiyed@africacdc.org";
                                    $data['body']=$this->load->view('due_contract.php',$data,false);

                                    $this->log_message($data['email_to'], $data['body'], $data['subject']);

                                    $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=2 WHERE staff_contract_id=$staff_contract_id");
                                }elseif($dateDiff < 0){
                                    //$status= 'Expired';
                                    $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=3 WHERE staff_contract_id=$staff_contract_id");
                                }elseif($dateDiff > 180){
                                    //$status= 'Active';
                                    $SQLSC1 = $this->db->query("UPDATE staff_contracts SET status_id=1 WHERE staff_contract_id=$staff_contract_id");
                                }else{
                                    $status= '';

                     }
                    }
        }
private function log_message($email, $message, $subject)
{
    $data = array('email_to' => $email,
                    'body'=>$message,
                    'subject'=>$subject);

    return $this->db->insert('email_notifications',$data);

}
         



   
  
  
    }


