<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppBootstrapService;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\WhatsAppWorkerClient;
use Illuminate\Console\Command;

class BootstrapWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:bootstrap
                            {--regenerate-token : Generate a new worker token and rewrite worker .env}
                            {--test : Run connection test after bootstrap}';

    protected $description = 'Generate WhatsApp worker token, write whatsapp-service/.env, and enable native platform';

    public function handle(
        WhatsAppBootstrapService $bootstrap,
        WhatsAppService $whatsapp,
        WhatsAppWorkerClient $worker,
    ): int {
        $this->info('Bootstrapping APM WhatsApp native platform…');

        $result = $bootstrap->bootstrap($this->option('regenerate-token'));

        $this->line('Driver: '.$result['driver']);
        $this->line('Worker URL: '.$result['worker_url']);
        $this->line('Worker env ready: '.($result['worker_env_ready'] ? 'yes' : 'no'));
        $this->line('Bot number: '.($result['bot_number'] !== '' ? $result['bot_number'] : '(not set — add in System configs → WhatsApp)'));
        $this->newLine();
        $this->comment('Start the worker:');
        $this->line('  cd '.base_path('whatsapp-service').' && npm install && npm start');

        if ($this->option('test')) {
            $this->newLine();
            $this->info('Testing worker…');
            if (! $worker->isReachable()) {
                $this->error('Worker is not reachable. Start it with npm start, then run: php artisan whatsapp:bootstrap --test');

                return 1;
            }
            $status = $whatsapp->publicStatus();
            $this->table(['Key', 'Value'], collect($status)->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v])->values()->all());
            if ($status['connected'] ?? false) {
                $stats = $whatsapp->adminStats();
                $this->info('Groups: '.($stats['groupCount'] ?? 0).', members: '.($stats['memberCount'] ?? 0));
            } else {
                $this->warn('Worker is up but WhatsApp is not linked yet. Use pairing in System configs → WhatsApp.');
            }
        }

        $this->newLine();
        $this->info('Bootstrap complete.');

        return 0;
    }
}
