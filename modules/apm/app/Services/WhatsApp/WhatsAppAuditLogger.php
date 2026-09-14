<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class WhatsAppAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $action, array $context = []): void
    {
        Log::channel('daily')->info('whatsapp.admin.'.$action, array_merge([
            'staff_id' => user_session('staff_id'),
            'user_id' => user_session('user_id'),
            'ip' => request()?->ip(),
        ], $context));
    }
}
