<?php

namespace App\Console\Commands;

use App\Models\ApmApiUser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncUsersCommand extends Command
{
    protected $signature = 'users:sync {--force : Force sync even if no changes}';

    protected $description = 'Sync users from staff app /share/users API into apm_api_users (runs hourly)';

    public function handle(): int
    {
        $this->info('Starting users sync from staff API (/share/users)...');

        try {
            if (!Schema::hasTable('apm_api_users')) {
                throw new Exception('Table apm_api_users does not exist. Run migrations first.');
            }

            $username = config('services.staff_api.username');
            $password = config('services.staff_api.password');
            if (empty($username) || empty($password)) {
                throw new Exception('STAFF_API_USERNAME and STAFF_API_PASSWORD must be set in .env to call the staff API.');
            }

            $apiBaseUrl = rtrim(config('services.staff_api.base_url'), '/');
            $apiEndpoint = config('services.staff_api.endpoints.users', '/share/users');
            $apiUrl = $apiBaseUrl . $apiEndpoint;

            $this->info('Fetching users from: ' . $apiUrl);

            $allUsers = [];
            $start = 0;
            $limit = 500;
            do {
                $url = $apiUrl . '?' . http_build_query(['limit' => $limit, 'start' => $start]);
                $response = Http::withBasicAuth($username, $password)
                    ->timeout(60)
                    ->retry(2, 1000)
                    ->get($url);

                if (!$response->successful()) {
                    throw new Exception('Staff API returned ' . $response->status() . ': ' . ($response->body() ?: 'no body'));
                }

                $chunk = $response->json();
                if (!is_array($chunk)) {
                    throw new Exception('Invalid response format from staff API (expected JSON array).');
                }
                $allUsers = array_merge($allUsers, $chunk);
                $start += $limit;
            } while (count($chunk) === $limit);

            $sourceCount = count($allUsers);
            $this->info("Fetched {$sourceCount} user(s) from staff API.");

            $created = 0;
            $updated = 0;
            $failed = 0;
            $skipped = 0;
            $firstError = null;
            $firstErrorUserId = null;

            $progressBar = $this->output->createProgressBar($sourceCount);
            $progressBar->start();

            foreach ($allUsers as $row) {
                try {
                    $user_id = (int) ($row['user_id'] ?? 0);
                    $auth_staff_id = (int) ($row['auth_staff_id'] ?? 0);
                    if ($user_id <= 0) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    $data = [
                        'user_id' => $user_id,
                        'password' => $row['password'] ?? null,
                        'name' => $this->truncate($row['name'] ?? null, 50),
                        'role' => $this->truncate((string) ($row['role'] ?? ''), 255),
                        'auth_staff_id' => $auth_staff_id,
                        'status' => isset($row['status']) ? (bool) $row['status'] : true,
                        'created_at' => $this->normalizeCreatedAt($row['created_at'] ?? null),
                        'changed' => $this->cleanDate($row['changed'] ?? null),
                        'isChanged' => (int) ($row['isChanged'] ?? 0),
                        'photo' => $this->truncate($row['photo'] ?? null, 200),
                        'signature' => $this->truncate($row['signature'] ?? null, 100),
                        'is_approved' => (int) ($row['is_approved'] ?? 0),
                        'is_verfied' => (int) ($row['is_verfied'] ?? 0),
                        'langauge' => $this->truncate($row['langauge'] ?? 'en', 100),
                        'email' => $this->truncate($row['email'] ?? null, 255),
                        'updated_at' => now(),
                    ];

                    $existing = ApmApiUser::find($user_id);
                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        ApmApiUser::create($data);
                        $created++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    if ($firstError === null) {
                        $firstError = $e->getMessage();
                        $firstErrorUserId = $row['user_id'] ?? null;
                    }
                    Log::error('Sync user failed', ['user_id' => $row['user_id'] ?? null, 'message' => $e->getMessage()]);
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $finalCount = ApmApiUser::count();
            $this->info("\n" . str_repeat('=', 50));
            $this->info('USERS SYNC RESULTS');
            $this->info(str_repeat('=', 50));
            $this->line("Source (API) records: {$sourceCount}");
            $this->line("apm_api_users count: {$finalCount}");
            $this->line("Created: {$created}");
            $this->line("Updated: {$updated}");
            $this->line("Failed: {$failed}");
            $this->line("Skipped: {$skipped}");
            if ($firstError !== null) {
                $this->newLine();
                $this->warn('First failure (user_id=' . $firstErrorUserId . '): ' . $firstError);
            }
            $this->info(str_repeat('=', 50));

            Log::info('Users sync completed', [
                'source_count' => $sourceCount,
                'apm_api_users_count' => $finalCount,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ]);

            return 0;
        } catch (Exception $e) {
            Log::error('Users sync failed: ' . $e->getMessage());
            $this->error('Users sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function cleanDate($date): ?string
    {
        if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }
        return $date;
    }

    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $value = (string) $value;
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function normalizeCreatedAt(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $str = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $str)) {
            return strlen($str) === 10 ? $str . ' 00:00:00' : $str;
        }
        return null;
    }
}
