<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\File;
use Staff\Shared\StaffStorage;
use Symfony\Component\Process\Process;

/**
 * Host-side shared storage for CI3 / APM / Helpdesk / staff-portal uploads.
 * Files stay outside the git tree under STAFF_DATA_ROOT.
 */
class SharedStorageService
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $repoRoot = $this->repoRoot();
        $paths = StaffStorage::recommendedPaths();
        $modules = [];

        foreach (StaffStorage::migrationSources($repoRoot) as $row) {
            $key = $row['module'];
            $legacy = $row['source'];
            $host = $row['destination'];
            $legacyStats = $this->directoryStats($legacy);
            $hostStats = $this->directoryStats($host);
            $modules[$key] = [
                'key' => $key,
                'label' => $this->labelFor($key),
                'legacy_path' => $legacy,
                'host_path' => $host,
                'legacy_files' => $legacyStats['files'],
                'legacy_bytes' => $legacyStats['bytes'],
                'host_files' => $hostStats['files'],
                'host_bytes' => $hostStats['bytes'],
                'legacy_is_symlink' => is_link($legacy),
                'needs_migration' => $this->needsMigration($legacyStats, $hostStats),
                'can_purge_legacy' => $this->canPurgeCi($key, $legacy, $host, $legacyStats, $hostStats),
                'env_var' => $this->envVarFor($key),
                'migrate_script' => $this->migrateScriptFor($key),
            ];
        }

        return [
            'using_host_storage' => StaffStorage::useHostStorage(),
            'site_id' => StaffStorage::siteId(),
            'data_root' => StaffStorage::hostDataRoot(),
            'repo_root' => $repoRoot,
            'recommended' => $paths,
            'modules' => array_values($modules),
            'scripts_dir' => $repoRoot.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'storage',
            'docs' => 'docs/STORAGE.md',
        ];
    }

    /**
     * @return array{status: string, message: string, output: string}
     */
    public function migrate(string $module): array
    {
        $module = strtolower(trim($module));
        if ($module === 'all') {
            $outputs = [];
            foreach (['ci', 'apm', 'helpdesk', 'staff-portal'] as $m) {
                $result = $this->migrate($m);
                $outputs[] = "=== {$m} ===\n".$result['output'];
                if ($result['status'] !== 'completed') {
                    return [
                        'status' => 'error',
                        'message' => $result['message'],
                        'output' => implode("\n", $outputs),
                    ];
                }
            }

            return [
                'status' => 'completed',
                'message' => 'Migrated all modules to host storage.',
                'output' => implode("\n", $outputs),
            ];
        }

        $script = $this->migrateScriptPath($module);
        if ($script === null || ! is_file($script)) {
            return ['status' => 'error', 'message' => "Unknown module or missing script: {$module}", 'output' => ''];
        }

        $this->ensureHostPermissions();

        $paths = StaffStorage::recommendedPaths();

        return $this->runBash($script, [
            'STAFF_DATA_ROOT' => $paths['data_root'],
            'STAFF_SITE_ID' => $paths['site_id'],
            'STAFF_USE_HOST_STORAGE' => 'true',
            'BASE_URL' => (string) (env('BASE_URL') ?: config('app.url', 'http://localhost/staff')),
            'STAFF_PORTAL_UPLOADS_ROOT' => $paths['ci'],
            'STAFF_APM_FILES_ROOT' => $paths['apm'],
            'STAFF_HELPDESK_FILES_ROOT' => $paths['helpdesk'],
            'STAFF_PORTAL_MODULE_FILES_ROOT' => $paths['staff_portal'],
        ], "Migration completed for {$module}.", "Migration failed for {$module}.");
    }

    /**
     * Enable host storage flags in staff-portal backend .env (does not rewrite other apps).
     *
     * @return array{status: string, message: string}
     */
    public function enableHostStorage(): array
    {
        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            return ['status' => 'error', 'message' => 'backend/.env is missing or not writable.'];
        }

        $root = StaffStorage::hostDataRoot();
        $this->upsertEnv($envPath, [
            'STAFF_DATA_ROOT' => $root,
            'STAFF_USE_HOST_STORAGE' => 'true',
            'STAFF_SITE_ID' => StaffStorage::siteId(),
            'STAFF_PORTAL_UPLOADS_ROOT' => $root.DIRECTORY_SEPARATOR.'ci',
            'STAFF_PORTAL_MODULE_FILES_ROOT' => $root.DIRECTORY_SEPARATOR.'staff-portal',
        ]);

        return [
            'status' => 'completed',
            'message' => 'Host storage enabled in staff-portal backend/.env. Restart PHP / clear config cache if needed.',
        ];
    }

    /**
     * Archive legacy CI uploads/ and symlink to host after verified migrate.
     *
     * @return array{status: string, message: string, output: string}
     */
    public function purgeCiLegacy(bool $dryRun = false): array
    {
        $script = $this->repoRoot().'/scripts/storage/purge-ci-uploads.sh';
        if (! is_file($script)) {
            return ['status' => 'error', 'message' => 'purge-ci-uploads.sh not found.', 'output' => ''];
        }

        $env = [
            'CONFIRM' => 'DELETE_CI_UPLOADS',
            'STAFF_DATA_ROOT' => StaffStorage::hostDataRoot(),
            'STAFF_PORTAL_UPLOADS_ROOT' => StaffStorage::hostDataRoot().DIRECTORY_SEPARATOR.'ci',
            'BASE_URL' => (string) env('BASE_URL', 'http://localhost/staff'),
        ];
        if ($dryRun) {
            $env['DRY_RUN'] = 'true';
        }

        return $this->runBash(
            $script,
            $env,
            $dryRun ? 'Dry-run purge completed.' : 'Legacy CI uploads archived and symlinked to host storage.',
            'Purge failed.'
        );
    }

    public function repoRoot(): string
    {
        // staff-portal/backend → staff/
        $detected = realpath(dirname(base_path(), 2));

        return $detected !== false ? $detected : dirname(base_path(), 2);
    }

    /**
     * @param  array{files: int, bytes: int}  $legacy
     * @param  array{files: int, bytes: int}  $host
     */
    protected function needsMigration(array $legacy, array $host): bool
    {
        if ($legacy['files'] === 0) {
            return false;
        }

        return $host['files'] < $legacy['files'] || $host['bytes'] < $legacy['bytes'];
    }

    /**
     * @param  array{files: int, bytes: int}  $legacy
     * @param  array{files: int, bytes: int}  $host
     */
    protected function canPurgeCi(string $key, string $legacy, string $host, array $legacyStats, array $hostStats): bool
    {
        if ($key !== StaffStorage::MODULE_CI) {
            return false;
        }
        if (is_link($legacy) || ! is_dir($legacy)) {
            return false;
        }
        if (! is_dir($host)) {
            return false;
        }
        if ($legacyStats['files'] === 0) {
            return false;
        }

        return $hostStats['files'] >= $legacyStats['files'] && $hostStats['bytes'] >= $legacyStats['bytes'];
    }

    /**
     * @return array{files: int, bytes: int}
     */
    protected function directoryStats(string $path): array
    {
        if (! is_dir($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $files++;
            $bytes += (int) $file->getSize();
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    protected function labelFor(string $key): string
    {
        return match ($key) {
            'ci' => 'CI3 staff uploads (photos, contracts, leave)',
            'apm' => 'APM attachments',
            'helpdesk' => 'Helpdesk attachments',
            'staff-portal' => 'Staff portal module files',
            default => $key,
        };
    }

    protected function envVarFor(string $key): string
    {
        return match ($key) {
            'ci' => 'STAFF_PORTAL_UPLOADS_ROOT',
            'apm' => 'STAFF_APM_FILES_ROOT',
            'helpdesk' => 'STAFF_HELPDESK_FILES_ROOT',
            'staff-portal' => 'STAFF_PORTAL_MODULE_FILES_ROOT',
            default => '',
        };
    }

    protected function migrateScriptFor(string $key): string
    {
        return match ($key) {
            'ci' => 'scripts/storage/migrate-ci-uploads.sh',
            'apm' => 'scripts/storage/migrate-apm-uploads.sh',
            'helpdesk' => 'scripts/storage/migrate-helpdesk-uploads.sh',
            'staff-portal' => 'scripts/storage/migrate-staff-portal-uploads.sh',
            default => '',
        };
    }

    protected function migrateScriptPath(string $module): ?string
    {
        $relative = $this->migrateScriptFor($module);
        if ($relative === '') {
            return null;
        }

        return $this->repoRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function ensureHostPermissions(): void
    {
        $script = $this->repoRoot().'/scripts/storage/fix-staff-storage-permissions.sh';
        if (is_file($script)) {
            $this->runBash($script, [
                'STAFF_DATA_ROOT' => StaffStorage::hostDataRoot(),
            ], 'ok', 'permissions failed');
        }
    }

    /**
     * @param  array<string, string>  $env
     * @return array{status: string, message: string, output: string}
     */
    protected function runBash(string $script, array $env, string $okMessage, string $failMessage): array
    {
        $process = new Process(
            ['bash', $script],
            $this->repoRoot(),
            array_merge($_ENV, $env)
        );
        $process->setTimeout(3600);
        $process->run();
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            return ['status' => 'error', 'message' => $failMessage, 'output' => $output];
        }

        return ['status' => 'completed', 'message' => $okMessage, 'output' => $output];
    }

    /**
     * @param  array<string, string>  $pairs
     */
    protected function upsertEnv(string $path, array $pairs): void
    {
        $content = File::get($path);
        foreach ($pairs as $key => $value) {
            $line = $key.'='.$this->envExport($value);
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
                $content = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content) ?? $content;
            } else {
                $content = rtrim($content)."\n{$line}\n";
            }
        }
        File::put($path, $content);
    }

    protected function envExport(string $value): string
    {
        if ($value === '' || preg_match('/[\s#\'"$\\\\]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
