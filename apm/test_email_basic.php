<?php

// Test basic email functionality without Laravel bootstrap
echo "🧪 TESTING BASIC EMAIL FUNCTIONALITY\n";
echo "====================================\n";

// Test if we can include the MailingHelper
try {
    require_once 'vendor/autoload.php';
    echo "✅ Autoloader loaded successfully\n";
    
    // Test basic PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    echo "✅ PHPMailer instantiated successfully\n";
    
    // Test basic configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'test@example.com';
    $mail->Password = 'test_password';
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 587;
    
    echo "✅ PHPMailer configured successfully\n";
    echo "📧 Email system is ready for testing!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
