<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class CreateUploadsSymlink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:link {--force : Force recreation of existing symlink}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create symbolic link from /opt/homebrew/var/www/staff/uploads to public/uploads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating uploads symbolic link...');

        $sourcePath = '/opt/homebrew/var/www/staff/uploads';
        $targetPath = public_path('uploads');

        // Check if source directory exists
        if (!File::exists($sourcePath)) {
            $this->error("Source directory does not exist: {$sourcePath}");
            $this->error('Please ensure the uploads directory exists at the specified path.');
            return 1;
        }

        // Check if target symlink already exists
        if (is_link($targetPath)) {
            if ($this->option('force')) {
                $this->info('Removing existing symlink...');
                File::delete($targetPath);
            } else {
                $this->warn('Symlink already exists. Use --force to recreate it.');
                return 1;
            }
        }

        // Check if target directory exists (not symlink)
        if (File::exists($targetPath) && !is_link($targetPath)) {
            if ($this->option('force')) {
                $this->info('Removing existing directory...');
                File::deleteDirectory($targetPath);
            } else {
                $this->error("Target path exists and is not a symlink: {$targetPath}");
                $this->error('Use --force to remove it and create symlink.');
                return 1;
            }
        }

        try {
            // Create the symbolic link
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows requires different approach
                $this->info('Windows detected, using junction...');
                $command = "mklink /J \"{$targetPath}\" \"{$sourcePath}\"";
                exec($command, $output, $returnCode);
                
                if ($returnCode !== 0) {
                    throw new \Exception('Failed to create Windows junction');
                }
            } else {
                // Unix-like systems
                symlink($sourcePath, $targetPath);
            }

            $this->info("Symbolic link created successfully!");
            $this->info("Source: {$sourcePath}");
            $this->info("Target: {$targetPath}");
            
            // Verify the symlink
            if (is_link($targetPath) && readlink($targetPath) === $sourcePath) {
                $this->info('Symlink verification successful!');
            } else {
                $this->warn('Symlink created but verification failed. Please check manually.');
            }

        } catch (\Exception $e) {
            $this->error("Failed to create symbolic link: " . $e->getMessage());
            return 1;
        }

        $this->info('Uploads symbolic link is ready!');
        $this->info('You can now access uploads at: ' . url('uploads'));
        
        return 0;
    }
}
