<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BlockedEmail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BlockedEmailService
{
    private const CACHE_KEY = 'blocked_emails:active';

    private const CACHE_SECONDS = 60;

    public function isBlocked(?string $email): bool
    {
        $email = $this->normalize($email);
        if ($email === null || ! $this->tableReady()) {
            return false;
        }

        return $this->activeEmailSet()->has($email);
    }

    public function block(
        string $email,
        string $reason,
        ?User $actor = null,
        ?int $auditLogId = null,
    ): BlockedEmail {
        $email = $this->normalize($email);
        if ($email === null) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A block reason is required.');
        }

        $row = BlockedEmail::query()->firstOrNew(['email' => $email]);
        $row->fill([
            'reason' => $reason,
            'blocked_by' => $actor?->id,
            'audit_log_id' => $auditLogId ?? $row->audit_log_id,
            'is_active' => true,
            'unblocked_at' => null,
            'unblocked_by' => null,
        ]);
        $row->save();

        $this->forgetCache();

        // Revoke any active sessions for this account.
        $user = User::query()->where('email', $email)->first();
        $user?->tokens()->delete();

        return $row->fresh(['blocker:id,name,email']);
    }

    /**
     * @return array{blocked: list<BlockedEmail>, skipped: list<string>, count: int}
     */
    public function blockAllSuspicious(string $reason, ?User $actor = null, ?string $skipEmail = null): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A block reason is required.');
        }

        if (! Schema::hasColumn('audit_logs', 'is_suspicious')) {
            return ['blocked' => [], 'skipped' => [], 'count' => 0];
        }

        $skipNormalized = $this->normalize($skipEmail);

        /** @var Collection<int, string> $emails */
        $emails = AuditLog::query()
            ->where('is_suspicious', true)
            ->whereNotNull('user_email')
            ->where('user_email', '!=', '')
            ->distinct()
            ->orderBy('user_email')
            ->pluck('user_email')
            ->map(fn ($email) => $this->normalize(is_string($email) ? $email : null))
            ->filter()
            ->unique()
            ->values();

        $blocked = [];
        $skipped = [];

        foreach ($emails as $email) {
            if ($skipNormalized !== null && $email === $skipNormalized) {
                $skipped[] = $email;
                continue;
            }

            if ($this->isBlocked($email)) {
                $skipped[] = $email;
                continue;
            }

            $latest = AuditLog::query()
                ->where('is_suspicious', true)
                ->where('user_email', $email)
                ->orderByDesc('id')
                ->value('id');

            $blocked[] = $this->block($email, $reason, $actor, $latest ? (int) $latest : null);
        }

        return [
            'blocked' => $blocked,
            'skipped' => $skipped,
            'count' => count($blocked),
        ];
    }

    public function unblock(BlockedEmail $blockedEmail, ?User $actor = null): BlockedEmail
    {
        $blockedEmail->update([
            'is_active' => false,
            'unblocked_at' => now(),
            'unblocked_by' => $actor?->id,
        ]);

        $this->forgetCache();

        return $blockedEmail->fresh(['blocker:id,name,email', 'unblocker:id,name,email']);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<string, true>
     */
    private function activeEmailSet(): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $emails = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return BlockedEmail::query()
                ->where('is_active', true)
                ->pluck('email')
                ->map(fn ($email) => (string) $email)
                ->values()
                ->all();
        });

        if (! is_array($emails)) {
            $this->forgetCache();
            $emails = BlockedEmail::query()
                ->where('is_active', true)
                ->pluck('email')
                ->map(fn ($email) => (string) $email)
                ->values()
                ->all();
        }

        return collect($emails)->mapWithKeys(fn (string $email) => [$email => true]);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('blocked_emails');
        } catch (\Throwable) {
            return false;
        }
    }

    public function normalize(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
