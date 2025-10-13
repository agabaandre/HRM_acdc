<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendScheduledRemindersJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:schedule 
                            {--type=daily : Type of reminder (daily, morning, evening, urgent)}
                            {--time= : Specific time to schedule (HH:MM format)}
                            {--delay= : Delay in minutes from now}
                            {--list : List all scheduled reminders}
                            {--clear : Clear all scheduled reminders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule reminder jobs to be sent at specific times';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if ($this->option('list')) {
                $this->listScheduledReminders();
                return 0;
            }

            if ($this->option('clear')) {
                $this->clearScheduledReminders();
                return 0;
            }

            $type = $this->option('type');
            $time = $this->option('time');
            $delay = $this->option('delay');

            if ($delay) {
                $this->scheduleWithDelay($type, $delay);
            } elseif ($time) {
                $this->scheduleAtTime($type, $time);
            } else {
                $this->scheduleDefault($type);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error scheduling reminders: ' . $e->getMessage());
            Log::error('Schedule reminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Schedule reminder with delay in minutes
     */
    private function scheduleWithDelay(string $type, int $delay): void
    {
        $scheduledTime = now()->addMinutes($delay);
        
        $this->info("⏰ Scheduling {$type} reminder in {$delay} minutes...");
        $this->info("📅 Scheduled for: {$scheduledTime->format('Y-m-d H:i:s')}");

        SendScheduledRemindersJob::dispatch($type, $scheduledTime->format('H:i'))
            ->delay($scheduledTime);

        $this->info("✅ {$type} reminder scheduled successfully!");
    }

    /**
     * Schedule reminder at specific time today
     */
    private function scheduleAtTime(string $type, string $time): void
    {
        try {
            $scheduledTime = Carbon::createFromFormat('H:i', $time);
            $scheduledTime = now()->setTime($scheduledTime->hour, $scheduledTime->minute);
            
            // If time has passed today, schedule for tomorrow
            if ($scheduledTime->isPast()) {
                $scheduledTime->addDay();
            }

            $this->info("⏰ Scheduling {$type} reminder at {$time}...");
            $this->info("📅 Scheduled for: {$scheduledTime->format('Y-m-d H:i:s')}");

            SendScheduledRemindersJob::dispatch($type, $time)
                ->delay($scheduledTime);

            $this->info("✅ {$type} reminder scheduled successfully!");

        } catch (\Exception $e) {
            $this->error("❌ Invalid time format. Use HH:MM (e.g., 09:00)");
            throw $e;
        }
    }

    /**
     * Schedule default reminders
     */
    private function scheduleDefault(string $type): void
    {
        $schedules = $this->getDefaultSchedules();
        
        if (!isset($schedules[$type])) {
            $this->error("❌ Unknown reminder type: {$type}");
            $this->info("Available types: " . implode(', ', array_keys($schedules)));
            return;
        }

        $schedule = $schedules[$type];
        $scheduledTime = now()->setTimeFromTimeString($schedule['time']);

        // If time has passed today, schedule for tomorrow
        if ($scheduledTime->isPast()) {
            $scheduledTime->addDay();
        }

        $this->info("⏰ Scheduling {$type} reminder at {$schedule['time']}...");
        $this->info("📅 Scheduled for: {$scheduledTime->format('Y-m-d H:i:s')}");

        SendScheduledRemindersJob::dispatch($type, $schedule['time'])
            ->delay($scheduledTime);

        $this->info("✅ {$type} reminder scheduled successfully!");
    }

    /**
     * Get default reminder schedules
     */
    private function getDefaultSchedules(): array
    {
        return [
            'daily' => [
                'time' => '09:00',
                'description' => 'Daily pending approvals reminder'
            ],
            'morning' => [
                'time' => '08:00',
                'description' => 'Morning pending approvals reminder'
            ],
            'evening' => [
                'time' => '17:00',
                'description' => 'Evening pending approvals reminder'
            ],
            'urgent' => [
                'time' => '14:00',
                'description' => 'Urgent pending approvals reminder (>3 days)'
            ]
        ];
    }

    /**
     * List all scheduled reminders
     */
    private function listScheduledReminders(): void
    {
        $this->info('📋 Scheduled Reminders:');
        $this->line('');

        $schedules = $this->getDefaultSchedules();
        
        foreach ($schedules as $type => $schedule) {
            $this->line("• {$type}: {$schedule['time']} - {$schedule['description']}");
        }

        $this->line('');
        $this->info('💡 Use --time or --delay to schedule custom reminders');
    }

    /**
     * Clear all scheduled reminders
     */
    private function clearScheduledReminders(): void
    {
        $this->warn('⚠️  This will clear all scheduled reminder jobs from the queue.');
        
        if ($this->confirm('Are you sure you want to continue?')) {
            // Clear jobs with specific tags
            $cleared = \DB::table('jobs')
                ->where('payload', 'like', '%SendScheduledRemindersJob%')
                ->delete();
            
            $this->info("✅ Cleared {$cleared} scheduled reminder jobs");
        } else {
            $this->info('❌ Operation cancelled');
        }
    }
}