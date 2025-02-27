<?php 
use React\EventLoop\Loop;
use React\Promise\Promise;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('send_email_async')) {
function send_email_async($to, $subject, $message, $id,$next_run)
{
return new Promise(function ($resolve, $reject) use ($to, $subject, $message, $id,$next_run) {
try {
$ci = &get_instance();
$settings = $ci->db->query('SELECT * FROM setting')->row();

// Create a new event loop
$loop = Loop::get();

// Server settings
$mailer = new PHPMailer();
$mailer->isSMTP();
$mailer->SMTPDebug = 0;
$mailer->Host = $settings->mail_host;
$mailer->SMTPAuth = true;
$mailer->Username = $settings->mail_username;
$mailer->Password = $settings->password;
$mailer->Port = $settings->mail_smtp_port;
$mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

// Set email details
$mailer->setFrom($settings->mail_username, $settings->title);

// Split the $to string by ";" and add each email address
$emails = explode(';', $to);
if (count($emails) > 0) {
    // Add the first email as the main recipient
    $primaryEmail = trim($emails[0]);
    $mailer->addAddress($primaryEmail);
    
    // Add the rest as CC recipients
    for ($i = 1; $i < count($emails); $i++) {
        $email = trim($emails[$i]);
        if (!empty($email)) {
            $mailer->addCC($email);
        }
    }
}


$mailer->Subject = $subject;
$mailer->Body = $message;
$mailer->isHTML(true); // Ensure the email is sent as HTML

// Send the email asynchronously
$loop->addTimer(0.0001, function () use ($mailer, $resolve, $reject, $id,$next_run) {
if ($mailer->send()) {
// Log success in the database
logEmailStatus(1, $id,$next_run);
$resolve('Email sent successfully');
} else {
// Log failure in the database
logEmailStatus(0, $id,$next_run);
$reject('Email sending failed: ' . $mailer->ErrorInfo);
}
});

$loop->run();
} catch (Exception $e) {
// Handle any exceptions here
$reject('Email sending failed: ' . $e->getMessage());
}
});
}

function logEmailStatus($status, $id, $next_run)
{
try {
$ci = &get_instance();
$data = [
'status' => $status,
'next_dispatch'=>$next_run
];
$ci->db->where('id', $id);
$ci->db->update('email_notifications', $data);
} catch (Exception $e) {
// Handle logging exception if necessary
}
}

function golobal_log_email($trigger,$email, $message, $subject, $staff, $end_date = FALSE,$next=FALSE)
{
    $ci = &get_instance();
    $data = array(
                    'trigger'=>$trigger,
                    'email_to' => $email,
                    'body'=>$message,
                    'staff_id'=>$staff,
                    'subject'=>$subject,
                    'end_date'=>$end_date,
                    'next_dispatch'=>$next);

    return $ci->db->insert('email_notifications',$data);

}

}



