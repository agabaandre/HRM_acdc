<?php

namespace App\Console\Commands;

use App\Jobs\SendPendingApprovalsFcmJob;
use App\Models\ApmApiUser;
use App\Services\FirebaseMessagingService;
use App\Services\PendingApprovalsService;
use Illuminate\Console\Command;

/**
 * Manual test for Firebase FCM "pending approvals" pushes (API/mobile users).
 *
 * Examples:
 *   php artisan notifications:test-fcm-pending-approvals
 *   php artisan notifications:test-fcm-pending-approvals --dry-run
 *   php artisan notifications:test-fcm-pending-approvals --user=123
 *   php artisan notifications:test-fcm-pending-approvals --queue
 */
class TestFcmPendingApprovalsCommand extends Command
{
    protected $signature = 'notifications:test-fcm-pending-approvals
                            {--user= : Only consider this apm_api_users.user_id}
                            {--dry-run : Show who would get a push; do not send or queue}
                            {--queue : Dispatch SendPendingApprovalsFcmJob to the queue instead of sending immediately}';

    protected $description = 'Test Firebase FCM pending-approval notifications (sync by default; no queue worker needed)';

    public function handle(FirebaseMessagingService $fcm): int
    {
        $this->info('FCM pending approvals — test run');
        $this->newLine();

        if (!$fcm->isConfigured()) {
            $this->error('Firebase is not configured. Set FIREBASE_PROJECT_ID and a valid FIREBASE_CREDENTIALS file (or storage/app/firebase-credentials.json).');
            return self::FAILURE;
        }

        $this->line('✓ Firebase project + credentials file OK');
        $this->newLine();

        $query = ApmApiUser::query()
            ->whereNotNull('firebase_token')
            ->where('firebase_token', '!=', '')
            ->where('status', true);

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No active API users with a registered firebase_token' . ($this->option('user') ? ' for --user=' . $this->option('user') : '') . '.');
            $this->line('Register a token from the app: PUT/POST /api/apm/v1/me/firebase-token');
            return self::SUCCESS;
        }

        $rows = [];
        $toSend = [];

        foreach ($users as $user) {
            $sessionData = $user->toSessionArray();
            $pendingService = new PendingApprovalsService($sessionData);
            $summary = $pendingService->getSummaryStats();
            $total = (int) ($summary['total_pending'] ?? 0);

            $tokenPreview = substr((string) $user->firebase_token, 0, 24) . '…';

            $rows[] = [
                'user_id' => $user->user_id,
                'name' => $user->name ?? '—',
                'pending' => $total,
                'token' => $tokenPreview,
            ];

            if ($total > 0) {
                $toSend[] = $user;
            }
        }

        $this->table(['user_id', 'name', 'pending', 'token (preview)'], $rows);

        if (empty($toSend)) {
            $this->newLine();
            $this->warn('Nobody has pending approvals — no FCM message would be sent (same as production command).');
            $this->line('To see a real push, ensure this user has pending items in APM or test with staff that does.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run: no messages sent or queued.');
            return self::SUCCESS;
        }

        $useQueue = (bool) $this->option('queue');
        if ($useQueue) {
            $this->newLine();
            $this->warn('Using queue — ensure a worker is running: php artisan queue:work');
        } else {
            $this->newLine();
            $this->info('Sending immediately (sync)…');
        }

        $sent = 0;
        foreach ($toSend as $user) {
            if ($useQueue) {
                SendPendingApprovalsFcmJob::dispatch($user);
                $this->line("  Queued FCM for user_id {$user->user_id}");
            } else {
                (new SendPendingApprovalsFcmJob($user))->handle($fcm);
                $this->line("  Sent FCM for user_id {$user->user_id}");
            }
            $sent++;
        }

        $this->newLine();
        $this->info("Done. {$sent} notification(s) " . ($useQueue ? 'queued.' : 'sent (sync).'));

        return self::SUCCESS;
    }
}
