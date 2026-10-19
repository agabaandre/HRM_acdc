<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffShareSetting extends Model
{
    protected $fillable = [
        'enabled',
        'base_url',
        'api_token',
        'last_synced_at',
        'last_sync_total',
        'last_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_token' => 'encrypted',
            'last_synced_at' => 'datetime',
            'last_sync_total' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => (bool) config('services.staff_share.enabled', true),
            'base_url' => (string) config(
                'services.staff_share.base_url',
                'https://cbp.africacdc.org/staff/backend',
            ),
            'api_token' => filled(config('services.staff_share.api_token'))
                ? (string) config('services.staff_share.api_token')
                : null,
        ]);
    }

    public function resolvedToken(): ?string
    {
        $token = trim((string) ($this->api_token ?? ''));
        if ($token !== '') {
            return $token;
        }

        $env = trim((string) config('services.staff_share.api_token', ''));

        return $env !== '' ? $env : null;
    }

    public function resolvedBaseUrl(): string
    {
        $url = rtrim(trim((string) ($this->base_url ?? '')), '/');
        if ($url !== '') {
            return $url;
        }

        return rtrim((string) config(
            'services.staff_share.base_url',
            'https://cbp.africacdc.org/staff/backend',
        ), '/');
    }

    /**
     * Apply UI/DB settings over config for the current request.
     */
    public function applyToConfig(): void
    {
        config([
            'services.staff_share.enabled' => (bool) $this->enabled,
            'services.staff_share.base_url' => $this->resolvedBaseUrl(),
            'services.staff_share.api_token' => $this->resolvedToken(),
        ]);
    }

    /**
     * Public API shape — never returns the raw token.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $token = $this->resolvedToken();

        return [
            'enabled' => (bool) $this->enabled,
            'base_url' => $this->resolvedBaseUrl(),
            'docs_url' => $this->resolvedBaseUrl().'/share/docs',
            'has_api_token' => filled($token),
            'api_token_hint' => filled($token) ? $this->tokenHint($token) : null,
            'auth_method' => 'bearer',
            'staff_count' => Staff::query()->count(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_sync_total' => $this->last_sync_total,
            'last_sync_message' => $this->last_sync_message,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function tokenHint(string $token): string
    {
        $len = strlen($token);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($token, 0, 4).str_repeat('•', max(4, $len - 8)).substr($token, -4);
    }
}
