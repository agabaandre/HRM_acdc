<?php 
use React\EventLoop\Loop;
use React\Promise\Promise;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('send_email_async')) {
function send_email_async($to, $subject, $message, $id)
{
return new Promise(function ($resolve, $reject) use ($to, $subject, $message, $id) {
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
foreach ($emails as $email) {
   
$mailer->addAddress(trim($email));
}

$mailer->Subject = $subject;
$mailer->Body = $message;
$mailer->isHTML(true); // Ensure the email is sent as HTML

// Send the email asynchronously
$loop->addTimer(0.0001, function () use ($mailer, $resolve, $reject, $id) {
if ($mailer->send()) {
// Log success in the database
logEmailStatus(1, $id);
$resolve('Email sent successfully');
} else {
// Log failure in the database
logEmailStatus(0, $id);
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

function logEmailStatus($status, $id)
{
try {
$ci = &get_instance();
$data = [
'status' => $status,
];
$ci->db->where('id', $id);
$ci->db->update('email_notifications', $data);
} catch (Exception $e) {
// Handle logging exception if necessary
}
}

}



