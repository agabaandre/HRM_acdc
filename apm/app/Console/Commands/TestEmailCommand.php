<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email=andrewa@africacdc.org}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify email configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Sending test email to: {$email}");
        
        try {
            // Use Laravel's mail system
            \Illuminate\Support\Facades\Mail::to($email)
                ->bcc('system@africacdc.org')
                ->send(new \App\Mail\TestEmailMail([
                    'recipient' => $email,
                    'test_type' => 'configuration_test'
                ]));
            
            $this->info('✅ Email sent successfully using Laravel Mail!');
            Log::info('Test email sent successfully using Laravel Mail', [
                'recipient' => $email,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Test email failed', [
                'recipient' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
}