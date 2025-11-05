<?php 
use React\EventLoop\Loop;
use React\Promise\Promise;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('send_email_async_smtp')) {
function send_email_async_smtp($to, $subject, $message, $id,$next_run)
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
            $mailer->addBCC($email);
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

$resolve('Email sent successfully');
} else {
// Log failure in the database
//dd($id);
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


if (!function_exists('push_email_smtp')) {
    function push_email_smtp($to, $subject, $message, $id, $next_run)
    {
        
            $ci = &get_instance();
            $settings = $ci->db->query('SELECT * FROM setting')->row();

            // Server settings
            $mailer = new PHPMailer();
            $mailer->isSMTP();
            $mailer->SMTPDebug = 2;
            $mailer->Host       = $settings->mail_host;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $settings->mail_username;
            $mailer->Password   = $settings->password;
            $mailer->Port       = $settings->mail_smtp_port;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

            // Set email details
            $mailer->setFrom($settings->mail_username, $settings->title);

            // Process recipients: first address as main, others as CC
            $emails = explode(';', $to);
            if (count($emails) > 0) {
                $primaryEmail = trim($emails[0]);
                $mailer->addAddress($primaryEmail);
                for ($i = 1; $i < count($emails); $i++) {
                    $email = trim($emails[$i]);
                    if (!empty($email)) {
                        $mailer->addCC($email);
                    }
                }
            }

            $mailer->Subject = $subject;
            $mailer->Body    = $message;
            $mailer->isHTML(true);

            // Send the email synchronously
            if ($mailer->send()) {
                // Optionally, log success using $id and $next_run if needed.
                
                return true;
            } else {
                // Optionally, log failure using $id and $next_run if needed.
                $error ='Email sending failed: ' . $mailer->ErrorInfo;
                //dd($error);
                return false;
            }
        
    }
}


function logEmailStatus($status, $id, $next_run)
{
        // Get the CodeIgniter instance
        $ci =& get_instance();
        
        // Prepare the data array to update the record
        $data = [
            'status' => $status,
            'next_dispatch' => $next_run
        ];
        // Update the email_notifications table where the id matches
        $ci->db->where('id', $id);
        $ci->db->update('email_notifications', $data);
  
}

function golobal_log_email($trigger, $email, $message, $subject, $staff, $end_date = FALSE, $next = FALSE,$entry_id=FALSE)
{
    $ci =& get_instance();
    $data = array(
        'entry_id'=> $entry_id,
        'trigger'       => $trigger,
        'email_to'      => $email,
        'body'          => $message,
        'staff_id'      => $staff,
        'subject'       => $subject,
        'end_date'      => $end_date,
        'next_dispatch' => $next
    );

   // dd($data);

    // Build the field names and values for the query.
    $fields = array();
    $values = array();
    foreach ($data as $field => $value) {
        $fields[] = $field;
        // If the value is FALSE, insert a NULL value.
        if ($value === FALSE) {
            $values[] = 'NULL';
        } else {
            $values[] = $ci->db->escape($value);
        }
        }
    delete_email_notification($entry_id);

    // Build the INSERT IGNORE SQL query.
    $sql = "INSERT IGNORE INTO email_notifications (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $values) . ")";

    return $ci->db->query($sql);
}
function delete_email_notification($id)
        {
            $ci =& get_instance(); 
            $ci->db->where('entry_id', $id);
            $ci->db->delete('email_notifications');
}

/**
 * Exchange Email Helper Functions
 * Microsoft Graph API integration for sending emails via Exchange
 * 
 * @author Africa CDC Team
 * @version 1.0.0
 */

// Load environment variables from .env file
// Try multiple possible .env locations
$envPaths = [
    defined('FCPATH') ? FCPATH . '../.env' : null,
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
    base_path('.env'), // Laravel base path if available
    realpath(__DIR__ . '/../../.env'),
    realpath(__DIR__ . '/../../../.env'),
];

$envPath = null;
foreach ($envPaths as $path) {
    if ($path && file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if ($envPath && file_exists($envPath)) {
    $envFile = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Handle lines with = sign
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // Remove quotes if present
        $value = trim($value, '"\'');
        // Set in both $_ENV and putenv() for compatibility
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

// Exchange OAuth Configuration from .env
$exchange_tenant_id = $_ENV['EXCHANGE_TENANT_ID'] ?? '';
$exchange_client_id = $_ENV['EXCHANGE_CLIENT_ID'] ?? '';
$exchange_client_secret = $_ENV['EXCHANGE_CLIENT_SECRET'] ?? '';
$exchange_redirect_uri = $_ENV['EXCHANGE_REDIRECT_URI'] ?? 'http://localhost/oauth/callback';
$exchange_scope = $_ENV['EXCHANGE_SCOPE'] ?? 'https://graph.microsoft.com/.default';
$exchange_auth_method = $_ENV['EXCHANGE_AUTH_METHOD'] ?? 'client_credentials';

// Email Configuration from .env
$mail_from_address = $_ENV['MAIL_FROM_ADDRESS'] ?? 'notifications@africacdc.org';
$mail_from_name = $_ENV['MAIL_FROM_NAME'] ?? 'CPHIA 2025';
$mail_cc_address = $_ENV['MAIL_CC_ADDRESS'] ?? 'system@africacdc.org';
$exchange_debug = $_ENV['EXCHANGE_DEBUG'] ?? 'false';

/**
 * Check if Exchange OAuth is properly configured
 */
function exchange_is_configured()
{
    global $exchange_tenant_id, $exchange_client_id, $exchange_client_secret;
    return !empty($exchange_tenant_id) && !empty($exchange_client_id) && !empty($exchange_client_secret);
}

/**
 * Get client credentials token for application-based authentication
 */
function exchange_get_client_credentials_token()
{
    global $exchange_tenant_id, $exchange_client_id, $exchange_client_secret, $exchange_scope;
    
    if (!exchange_is_configured()) {
        throw new Exception('OAuth not configured. Please check environment variables.');
    }

    $url = 'https://login.microsoftonline.com/' . $exchange_tenant_id . '/oauth2/v2.0/token';
    
    $data = [
        'client_id' => $exchange_client_id,
        'client_secret' => $exchange_client_secret,
        'scope' => $exchange_scope,
        'grant_type' => 'client_credentials'
    ];

    $response = exchange_make_http_request($url, 'POST', $data);
    
    if ($response && isset($response['access_token'])) {
        // Store token in session
        $_SESSION['exchange_access_token'] = $response['access_token'];
        $_SESSION['exchange_token_expires'] = time() + ($response['expires_in'] ?? 3600);
        return $response['access_token'];
    }

    throw new Exception('Failed to get client credentials token: ' . ($response['error_description'] ?? 'Unknown error'));
}

/**
 * Check if we have a valid access token
 */
function exchange_has_valid_token()
{
    return !empty($_SESSION['exchange_access_token']) && 
           !empty($_SESSION['exchange_token_expires']) && 
           time() < $_SESSION['exchange_token_expires'];
}

/**
 * Get valid access token with automatic refresh
 */
function exchange_get_access_token()
{
    // Check if token needs refresh (5 minutes buffer)
    if (exchange_has_valid_token()) {
        return $_SESSION['exchange_access_token'];
    }

    // Get new token
    return exchange_get_client_credentials_token();
}

/**
 * Send email using Microsoft Graph API
 */
function exchange_send_email($to, $subject, $body, $isHtml = true, $fromEmail = null, $fromName = null, $cc = [], $bcc = [], $attachments = [])
{
    global $mail_from_address, $mail_from_name;
    
    if (!exchange_has_valid_token()) {
        exchange_get_client_credentials_token();
    }

    $fromEmail = $fromEmail ?: $mail_from_address;
    $fromName = $fromName ?: $mail_from_name;

    // Prepare recipients
    $recipients = [];
    foreach (explode(',', $to) as $email) {
        $email = trim($email);
        if ($email) {
            $recipients[] = ['emailAddress' => ['address' => $email]];
        }
    }

    // Add CC recipients
    foreach ($cc as $email) {
        $email = trim($email);
        if ($email) {
            $recipients[] = ['emailAddress' => ['address' => $email]];
        }
    }

    // Add BCC recipients
    foreach ($bcc as $email) {
        $email = trim($email);
        if ($email) {
            $recipients[] = ['emailAddress' => ['address' => $email]];
        }
    }

    // Prepare email message
    $message = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => $isHtml ? 'HTML' : 'Text',
                'content' => $body
            ],
            'toRecipients' => $recipients,
            'from' => [
                'emailAddress' => [
                    'address' => $fromEmail,
                    'name' => $fromName
                ]
            ]
        ]
    ];

    // Add attachments if provided
    if (!empty($attachments)) {
        $message['message']['attachments'] = [];
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $message['message']['attachments'][] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => basename($file),
                    'contentBytes' => base64_encode(file_get_contents($file))
                ];
            }
        }
    }

    // Send email via Microsoft Graph API
    $url = 'https://graph.microsoft.com/v1.0/users/' . urlencode($fromEmail) . '/sendMail';
    $response = exchange_make_http_request($url, 'POST', $message, [
        'Authorization: Bearer ' . exchange_get_access_token(),
        'Content-Type: application/json'
    ]);

    return $response !== false;
}

/**
 * Make HTTP request with proper error handling
 */
function exchange_make_http_request($url, $method = 'GET', $data = null, $headers = [])
{
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: Exchange-Email-Integration/1.0',
            'Accept: application/json'
        ], $headers)
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        // For OAuth token requests, use form-encoded data
        if (strpos($url, '/oauth2/v2.0/token') !== false) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
                'User-Agent: Exchange-Email-Integration/1.0',
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ], $headers));
        } else {
            // For other requests, use JSON
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Exchange OAuth cURL Error: $error");
        return false;
    }

    $decodedResponse = json_decode($response, true);
    
    if ($httpCode >= 400) {
        error_log("Exchange OAuth HTTP Error $httpCode: " . ($decodedResponse['error']['message'] ?? $response));
        return false;
    }

    return $decodedResponse ?: true;
}

/**
 * Exchange-based async email function
 */
if (!function_exists('send_email_async')) {
    function send_email_async($to, $subject, $message, $id, $next_run)
    {
        return new Promise(function ($resolve, $reject) use ($to, $subject, $message, $id, $next_run) {
            try {
                global $mail_cc_address, $mail_from_address, $mail_from_name;
                
                // Check if service is configured
                if (!exchange_is_configured()) {
                    $reject('Exchange email service not configured. Please check OAuth settings.');
                    return;
                }

                // Convert comma-separated strings to arrays
                $ccArray = [$mail_cc_address]; // Always CC system@africacdc.org
                $bccArray = [];
                
                // Clean up arrays
                $ccArray = array_filter(array_map('trim', $ccArray));
                $bccArray = array_filter(array_map('trim', $bccArray));
                
                // Remove duplicates from CC array
                $ccArray = array_unique($ccArray);

                // Determine if message is HTML
                $isHtml = (strpos($message, '<html') !== false || strpos($message, '<body') !== false || strpos($message, '<p') !== false);

                // Send email
                $result = exchange_send_email(
                    $to,
                    $subject,
                    $message,
                    $isHtml,
                    $mail_from_address,
                    $mail_from_name,
                    $ccArray,
                    $bccArray,
                    []
                );

                if ($result) {
                    $resolve('Email sent successfully via Exchange');
                } else {
                    $reject('Email sending failed via Exchange');
                }

            } catch (Exception $e) {
                $reject('Email sending failed: ' . $e->getMessage());
            }
        });
    }
}

/**
 * Exchange-based push email function
 */
if (!function_exists('push_email')) {
    function push_email($to, $subject, $message, $id, $next_run)
    {
        try {
            global $mail_cc_address, $mail_from_address, $mail_from_name;
            
            // Check if service is configured
            if (!exchange_is_configured()) {
                error_log('Exchange email service not configured. Please check OAuth settings.');
                return false;
            }

            // Convert comma-separated strings to arrays
            $ccArray = [$mail_cc_address]; // Always CC system@africacdc.org
            $bccArray = [];
            
            // Clean up arrays
            $ccArray = array_filter(array_map('trim', $ccArray));
            $bccArray = array_filter(array_map('trim', $bccArray));
            
            // Remove duplicates from CC array
            $ccArray = array_unique($ccArray);

            // Determine if message is HTML
            $isHtml = (strpos($message, '<html') !== false || strpos($message, '<body') !== false || strpos($message, '<p') !== false);

            // Send email
            $result = exchange_send_email(
                $to,
                $subject,
                $message,
                $isHtml,
                $mail_from_address,
                $mail_from_name,
                $ccArray,
                $bccArray,
                []
            );

            if ($result) {
                error_log("Exchange email sent successfully to: $to");
                return true;
            } else {
                error_log("Exchange email failed to send to: $to");
                return false;
            }

        } catch (Exception $e) {
            error_log('Exchange email sending failed: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Exchange Email Function
 * Main function for sending emails via Microsoft Graph API
 * 
 * @param string $to Recipient email addresses (comma-separated)
 * @param string $from Sender email address
 * @param string $subject Email subject
 * @param string $message Email message (HTML or plain text)
 * @param string $cc CC recipients (comma-separated)
 * @param string $bcc BCC recipients (comma-separated)
 * @param array $attachments Array of file paths to attach
 * @return bool Success status
 */
function exchange_email($to, $from, $subject, $message, $cc = '', $bcc = '', $attachments = [])
{
    try {
        global $mail_cc_address;
        
        // Check if service is configured
        if (!exchange_is_configured()) {
            error_log('Exchange Email: Service not configured. Please check environment variables.');
            return false;
        }

        // Convert comma-separated strings to arrays
        $ccArray = !empty($cc) ? explode(',', $cc) : [];
        $bccArray = !empty($bcc) ? explode(',', $bcc) : [];
        
        // Always CC system@africacdc.org on all emails
        $ccArray[] = $mail_cc_address;
        
        // Clean up arrays
        $ccArray = array_filter(array_map('trim', $ccArray));
        $bccArray = array_filter(array_map('trim', $bccArray));
        
        // Remove duplicates from CC array
        $ccArray = array_unique($ccArray);

        // Determine if message is HTML
        $isHtml = (strpos($message, '<html') !== false || strpos($message, '<body') !== false || strpos($message, '<p') !== false);

        // Send email
        $result = exchange_send_email(
            $to,
            $subject,
            $message,
            $isHtml,
            $from, // Use the from parameter as sender
            null,  // Use default from name
            $ccArray,
            $bccArray,
            $attachments
        );

        if ($result) {
            $ccList = !empty($ccArray) ? ' (CC: ' . implode(', ', $ccArray) . ')' : '';
            error_log("Exchange Email: Successfully sent email to $to$ccList");
        } else {
            error_log("Exchange Email: Failed to send email to $to");
        }

        return $result;

    } catch (Exception $e) {
        error_log('Exchange Email Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Test Email Function
 * Function to test email service configuration
 * 
 * @param string $testEmail Email address to send test to
 * @return array Test results
 */
function exchange_test_email($testEmail = null)
{
    global $mail_from_address, $mail_cc_address;
    
    $testEmail = $testEmail ?: $mail_from_address;
    
    try {
        $testResults = [
            'configured' => exchange_is_configured(),
            'test_email' => $testEmail,
            'success' => false,
            'error' => null
        ];

        if (!$testResults['configured']) {
            $testResults['error'] = 'Email service not configured. Please check environment variables.';
            return $testResults;
        }

        // Send test email
        $subject = 'Exchange Email Test - ' . date('Y-m-d H:i:s');
        $body = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; text-align: center;">
                <h1>✅ Exchange Email Test</h1>
                <p>Microsoft Graph API Integration</p>
            </div>
            
            <div style="padding: 20px;">
                <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3>🎉 Email Service Working Perfectly!</h3>
                    <p>This email confirms that your Exchange Email integration is working correctly.</p>
                </div>
                
                <h3>Configuration Details:</h3>
                <ul>
                    <li><strong>Method:</strong> Microsoft Graph API</li>
                    <li><strong>Authentication:</strong> OAuth 2.0 Client Credentials</li>
                    <li><strong>Security:</strong> Bearer Token Authentication</li>
                    <li><strong>Sent At:</strong> ' . date('Y-m-d H:i:s T') . '</li>
                    <li><strong>Recipient:</strong> ' . htmlspecialchars($testEmail) . '</li>
                    <li><strong>Auto-CC:</strong> ' . $mail_cc_address . '</li>
                    <li><strong>Service:</strong> Exchange Email Integration</li>
                </ul>
                
                <p><strong>Your Exchange Email integration is ready for production! 🎉</strong></p>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;">
                <p>This is an automated test email from Exchange Email Integration</p>
                <p>Generated on ' . date('Y-m-d H:i:s') . ' | Microsoft Graph API</p>
            </div>
        </body>
        </html>';

        $testResults['success'] = exchange_send_email($testEmail, $subject, $body, true, $mail_from_address, null, [$mail_cc_address], [], []);
        
        if (!$testResults['success']) {
            $testResults['error'] = 'Failed to send test email';
        }

        return $testResults;

    } catch (Exception $e) {
        return [
            'configured' => false,
            'test_email' => $testEmail,
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Exchange Status Check
 * Checks if Exchange is properly configured and authenticated
 * 
 * @return array Status information
 */
function exchange_status()
{
    try {
        return [
            'configured' => exchange_is_configured(),
            'authenticated' => exchange_has_valid_token(),
            'token_valid' => exchange_has_valid_token(),
            'email_service_ready' => exchange_is_configured(),
            'token_expires_at' => $_SESSION['exchange_token_expires'] ?? null,
            'debug_info' => [
                'tenant_id_set' => !empty($GLOBALS['exchange_tenant_id']),
                'client_id_set' => !empty($GLOBALS['exchange_client_id']),
                'client_secret_set' => !empty($GLOBALS['exchange_client_secret']),
                'from_email_set' => !empty($GLOBALS['mail_from_address']),
                'from_name_set' => !empty($GLOBALS['mail_from_name'])
            ]
        ];
    } catch (Exception $e) {
        return [
            'configured' => false,
            'authenticated' => false,
            'token_valid' => false,
            'email_service_ready' => false,
            'error' => $e->getMessage()
        ];
    }
}




}



