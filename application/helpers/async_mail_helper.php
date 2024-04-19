<?php
use React\EventLoop\Loop;
use React\Promise\Promise;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('send_email_async')) {
    function send_email_async($to, $subject, $message)
    {
        return new Promise(function ($resolve, $reject) use ($to, $subject, $message) {
            try {
                $ci = &get_instance();
                $settings = $ci->db->query('SELECT * FROM setting')->row();

                // Create a new event loop
                $loop = Loop::get();

                // Server settings
                $mailer = new PHPMailer();
                $mailer->isSMTP();
				$mailer->SMTPDebug = 2;
                $mailer->Host = $settings->mail_host;
                $mailer->SMTPAuth = true;
                $mailer->Username = $settings->mail_username;
				//$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    
                $mailer->Password = $settings->password;
                //$mailer->SMTPSecure = $settings->mail_encryption;
                $mailer->Port = $settings->mail_smtp_port;

                // Set email details
                $mailer->setFrom($settings->mail_username, $settings->site_name);
                $mailer->addAddress($to);
                $mailer->Subject = $subject;
                $mailer->Body = $message;

                // Send the email asynchronously
                $loop->addTimer(0.0001, function () use ($mailer, $resolve, $reject, $message, $to) {
                    if ($mailer->send()) {
                        // Log success in the database
                        logEmailStatus($message, $to, 1, 'Email sent successfully');
                        $resolve('Email sent successfully');
                    } else {
                        // Log failure in the database
                        logEmailStatus($message, $to, 0, $mailer->ErrorInfo);
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

    function logEmailStatus($message, $to, $status, $notification)
    {
        try {
         
            $ci = &get_instance();
            $data = [
                'address' => $to,
                'message_body' => $message,
                'status' => $status,
                'mail_log' => $notification,
				'unique_key'=>$to.'-'.date('Y-m-d-h:i:s')
            ];
            $ci->db->replace('notifications', $data);
        } catch (Exception $e) {
            
        }
    }
}
