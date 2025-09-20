<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendMatrixNotificationJob;
use App\Jobs\SendDailyPendingApprovalsNotificationJob;
use App\Models\Matrix;
use App\Models\Staff;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TestEmailSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-system 
                            {--test-type=all : Type of test to run (basic, matrix, daily, queue, all)}
                            {--email= : Email address to send test emails to}
                            {--detailed : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the email notification system comprehensively';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Email System Test...');
        $this->newLine();

        $testType = $this->option('test-type');
        $testEmail = $this->option('email') ?: 'test@example.com';
        $verbose = $this->option('detailed');

        $this->info("📧 Test Email: {$testEmail}");
        $this->info("🔍 Test Type: {$testType}");
        $this->newLine();

        $results = [];

        try {
            switch ($testType) {
                case 'basic':
                    $results['basic'] = $this->testBasicEmail($testEmail, $verbose);
                    break;
                case 'matrix':
                    $results['matrix'] = $this->testMatrixNotification($testEmail, $verbose);
                    break;
                case 'daily':
                    $results['daily'] = $this->testDailyNotification($verbose);
                    break;
                case 'queue':
                    $results['queue'] = $this->testQueueSystem($verbose);
                    break;
                case 'all':
                default:
                    $results['basic'] = $this->testBasicEmail($testEmail, $verbose);
                    $results['matrix'] = $this->testMatrixNotification($testEmail, $verbose);
                    $results['daily'] = $this->testDailyNotification($verbose);
                    $results['queue'] = $this->testQueueSystem($verbose);
                    break;
            }

            $this->displayResults($results);

        } catch (\Exception $e) {
            $this->error("❌ Test failed with exception: " . $e->getMessage());
            Log::error('Email system test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Test basic email sending
     */
    private function testBasicEmail($email, $verbose = false)
    {
        $this->info('📧 Testing Basic Email...');
        
        try {
            Mail::raw('Test email from APM system - ' . now(), function($msg) use ($email) {
                $msg->to($email)->subject('APM Email Test - ' . now());
            });

            $this->info('✅ Basic email sent successfully');
            return ['status' => 'success', 'message' => 'Basic email sent successfully'];
        } catch (\Exception $e) {
            $this->error('❌ Basic email failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test matrix notification
     */
    private function testMatrixNotification($email, $verbose = false)
    {
        $this->info('📋 Testing Matrix Notification...');
        
        try {
            // Get test data
            $matrix = Matrix::with(['staff', 'division'])->first();
            $staff = Staff::first();

            if (!$matrix || !$staff) {
                $this->warn('⚠️  No matrix or staff data found for testing');
                return ['status' => 'warning', 'message' => 'No test data available'];
            }

            if ($verbose) {
                $this->line("   Matrix ID: {$matrix->id}");
                $this->line("   Staff: {$staff->fname} {$staff->lname}");
            }

            // Dispatch matrix notification job
            SendMatrixNotificationJob::dispatch($matrix, $staff, 'test', 'This is a test matrix notification');

            $this->info('✅ Matrix notification job dispatched');
            return ['status' => 'success', 'message' => 'Matrix notification job dispatched'];
        } catch (\Exception $e) {
            $this->error('❌ Matrix notification failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test daily notification
     */
    private function testDailyNotification($verbose = false)
    {
        $this->info('📅 Testing Daily Notification...');
        
        try {
            // Get approver count
            $approverCount = $this->getApproverCount();
            
            if ($verbose) {
                $this->line("   Approvers found: {$approverCount}");
            }

            if ($approverCount == 0) {
                $this->warn('⚠️  No approvers found for testing');
                return ['status' => 'warning', 'message' => 'No approvers found'];
            }

            // Dispatch daily notification job
            SendDailyPendingApprovalsNotificationJob::dispatch();

            $this->info('✅ Daily notification job dispatched');
            return ['status' => 'success', 'message' => 'Daily notification job dispatched'];
        } catch (\Exception $e) {
            $this->error('❌ Daily notification failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test queue system
     */
    private function testQueueSystem($verbose = false)
    {
        $this->info('⚙️  Testing Queue System...');
        
        try {
            // Check queue status
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            if ($verbose) {
                $this->line("   Queue size: {$queueSize}");
                $this->line("   Failed jobs: {$failedJobs}");
            }

            // Test queue processing
            $this->info('   Processing one job...');
            $this->call('queue:work', ['--once' => true, '--verbose' => $verbose]);

            $this->info('✅ Queue system test completed');
            return [
                'status' => 'success', 
                'message' => "Queue size: {$queueSize}, Failed: {$failedJobs}"
            ];
        } catch (\Exception $e) {
            $this->error('❌ Queue test failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get approver count
     */
    private function getApproverCount()
    {
        try {
            $divisionApprovers = DB::table('divisions')
                ->select('division_head as staff_id')
                ->whereNotNull('division_head')
                ->union(
                    DB::table('divisions')
                        ->select('focal_person as staff_id')
                        ->whereNotNull('focal_person')
                )
                ->union(
                    DB::table('divisions')
                        ->select('admin_assistant as staff_id')
                        ->whereNotNull('admin_assistant')
                )
                ->union(
                    DB::table('divisions')
                        ->select('finance_officer as staff_id')
                        ->whereNotNull('finance_officer')
                )
                ->get()
                ->pluck('staff_id')
                ->unique()
                ->count();
            
            return $divisionApprovers;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Display test results
     */
    private function displayResults($results)
    {
        $this->newLine();
        $this->info('📊 Test Results Summary:');
        $this->newLine();

        $successCount = 0;
        $totalCount = count($results);

        foreach ($results as $test => $result) {
            $status = $result['status'];
            $message = $result['message'];

            switch ($status) {
                case 'success':
                    $this->info("✅ {$test}: {$message}");
                    $successCount++;
                    break;
                case 'warning':
                    $this->warn("⚠️  {$test}: {$message}");
                    $successCount++;
                    break;
                case 'error':
                    $this->error("❌ {$test}: {$message}");
                    break;
            }
        }

        $this->newLine();
        $this->info("📈 Overall Result: {$successCount}/{$totalCount} tests passed");

        if ($successCount == $totalCount) {
            $this->info('🎉 All tests passed! Email system is working correctly.');
        } else {
            $this->warn('⚠️  Some tests failed. Check the logs for more details.');
        }

        // Show system information
        $this->newLine();
        $this->info('🔧 System Information:');
        $this->line("   PHP Version: " . PHP_VERSION);
        $this->line("   Laravel Version: " . app()->version());
        $this->line("   Queue Driver: " . config('queue.default'));
        $this->line("   Mail Driver: " . config('mail.default'));
        $this->line("   Environment: " . app()->environment());
    }
}