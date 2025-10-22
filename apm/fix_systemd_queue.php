<?php

/**
 * Systemd Queue Service Fix Script
 * Run this on your production server: php fix_systemd_queue.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Systemd Queue Service Fix ===\n\n";

try {
    // Get the current working directory
    $currentPath = getcwd();
    $phpPath = PHP_BINARY;
    
    echo "Current Path: {$currentPath}\n";
    echo "PHP Path: {$phpPath}\n\n";
    
    // Check if systemd service exists
    echo "Checking systemd service...\n";
    
    $serviceName = 'laravel-queue-apm.service';
    $serviceFile = "/etc/systemd/system/{$serviceName}";
    
    if (file_exists($serviceFile)) {
        echo "✅ Service file exists: {$serviceFile}\n";
        
        // Read current service file
        $currentService = file_get_contents($serviceFile);
        echo "\nCurrent service configuration:\n";
        echo "---\n";
        echo $currentService;
        echo "---\n\n";
        
    } else {
        echo "❌ Service file not found: {$serviceFile}\n";
    }
    
    // Create the correct service file
    echo "Creating corrected service file...\n";
    
    $serviceContent = "[Unit]
Description=Laravel Queue Worker for ACDC APM
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory={$currentPath}
ExecStart={$phpPath} artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-queue-apm

# Environment variables
Environment=APP_ENV=production
Environment=APP_DEBUG=false

# Resource limits
LimitNOFILE=65536
MemoryLimit=512M

[Install]
WantedBy=multi-user.target";

    // Write the service file
    $tempServiceFile = "/tmp/{$serviceName}";
    file_put_contents($tempServiceFile, $serviceContent);
    
    echo "✅ Service file created: {$tempServiceFile}\n";
    echo "\nNew service configuration:\n";
    echo "---\n";
    echo $serviceContent;
    echo "---\n\n";
    
    // Provide commands to install
    echo "=== Installation Commands ===\n";
    echo "Run these commands as root:\n\n";
    echo "1. Copy service file:\n";
    echo "   sudo cp {$tempServiceFile} {$serviceFile}\n\n";
    
    echo "2. Reload systemd:\n";
    echo "   sudo systemctl daemon-reload\n\n";
    
    echo "3. Enable service:\n";
    echo "   sudo systemctl enable {$serviceName}\n\n";
    
    echo "4. Start service:\n";
    echo "   sudo systemctl start {$serviceName}\n\n";
    
    echo "5. Check status:\n";
    echo "   sudo systemctl status {$serviceName}\n\n";
    
    echo "6. View logs:\n";
    echo "   sudo journalctl -u {$serviceName} -f\n\n";
    
    // Alternative: Create a simple service file
    echo "=== Alternative: Simple Service File ===\n";
    $simpleServiceFile = "/tmp/laravel-queue-simple.service";
    $simpleContent = "[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory={$currentPath}
ExecStart={$phpPath} artisan queue:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target";

    file_put_contents($simpleServiceFile, $simpleContent);
    
    echo "Simple service file created: {$simpleServiceFile}\n";
    echo "Use this if the complex one doesn't work:\n";
    echo "sudo cp {$simpleServiceFile} {$serviceFile}\n\n";
    
    // Check current service status
    echo "=== Current Service Status ===\n";
    $statusOutput = [];
    exec("systemctl status {$serviceName} 2>&1", $statusOutput);
    foreach ($statusOutput as $line) {
        echo $line . "\n";
    }
    
    echo "\n=== Fix Complete ===\n";
    echo "The service file has been created with the correct configuration.\n";
    echo "Follow the installation commands above to fix the systemd service.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
