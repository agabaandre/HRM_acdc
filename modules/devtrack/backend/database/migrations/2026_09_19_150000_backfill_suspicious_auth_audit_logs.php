<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'is_suspicious')) {
            return;
        }

        $reasonByEvent = [
            'auth_failed' => 'Failed login attempt',
            'auth_2fa_failed' => 'Failed two-factor verification',
            'client_auth_failed' => 'Failed client authentication',
            'auth_failed_inactive' => 'Login attempt against a deactivated account',
            'client_auth_ip_denied' => 'Client authentication blocked by IP allowlist',
        ];

        foreach ($reasonByEvent as $eventType => $reason) {
            DB::table('audit_logs')
                ->where('event_type', $eventType)
                ->where(function ($query): void {
                    $query->where('is_suspicious', false)
                        ->orWhereNull('is_suspicious');
                })
                ->update([
                    'is_suspicious' => true,
                    'suspicious_reasons' => $reason,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank — historical flag backfill is not reversed.
    }
};
