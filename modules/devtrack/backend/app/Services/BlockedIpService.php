<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BlockedIpService
{
    private const CACHE_KEY = 'blocked_ips:active';

    private const CACHE_SECONDS = 60;

    public function isBlocked(?string $ip): bool
    {
        $ip = $this->normalize($ip);
        if ($ip === null || ! $this->tableReady()) {
            return false;
        }

        return $this->activeIpSet()->has($ip);
    }

    public function findActive(?string $ip): ?BlockedIp
    {
        $ip = $this->normalize($ip);
        if ($ip === null || ! $this->tableReady()) {
            return null;
        }

        return BlockedIp::query()
            ->where('ip_address', $ip)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return BlockedIp
     */
    public function block(
        string $ip,
        string $reason,
        ?User $actor = null,
        ?int $auditLogId = null,
    ): BlockedIp {
        $ip = $this->normalize($ip);
        if ($ip === null) {
            throw new \InvalidArgumentException('A valid IP address is required.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A block reason is required.');
        }

        $row = BlockedIp::query()->firstOrNew(['ip_address' => $ip]);
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

        return $row->fresh(['blocker:id,name,email']);
    }

    /**
     * Block every distinct IP that currently has a suspicious audit log.
     *
     * @return array{blocked: list<BlockedIp>, skipped: list<string>, count: int}
     */
    public function blockAllSuspicious(string $reason, ?User $actor = null, ?string $skipIp = null): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A block reason is required.');
        }

        if (! Schema::hasColumn('audit_logs', 'is_suspicious')) {
            return ['blocked' => [], 'skipped' => [], 'count' => 0];
        }

        $skipNormalized = $this->normalize($skipIp);

        /** @var Collection<int, string> $ips */
        $ips = AuditLog::query()
            ->where('is_suspicious', true)
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->distinct()
            ->orderBy('ip_address')
            ->pluck('ip_address')
            ->map(fn ($ip) => $this->normalize(is_string($ip) ? $ip : null))
            ->filter()
            ->unique()
            ->values();

        $blocked = [];
        $skipped = [];

        foreach ($ips as $ip) {
            if ($skipNormalized !== null && $ip === $skipNormalized) {
                $skipped[] = $ip;
                continue;
            }

            if ($this->isBlocked($ip)) {
                $skipped[] = $ip;
                continue;
            }

            $latest = AuditLog::query()
                ->where('is_suspicious', true)
                ->where('ip_address', $ip)
                ->orderByDesc('id')
                ->value('id');

            $blocked[] = $this->block($ip, $reason, $actor, $latest ? (int) $latest : null);
        }

        return [
            'blocked' => $blocked,
            'skipped' => $skipped,
            'count' => count($blocked),
        ];
    }

    public function unblock(BlockedIp $blockedIp, ?User $actor = null): BlockedIp
    {
        $blockedIp->update([
            'is_active' => false,
            'unblocked_at' => now(),
            'unblocked_by' => $actor?->id,
        ]);

        $this->forgetCache();

        return $blockedIp->fresh(['blocker:id,name,email', 'unblocker:id,name,email']);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<string, true>
     */
    private function activeIpSet(): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $ips = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return BlockedIp::query()
                ->where('is_active', true)
                ->pluck('ip_address')
                ->map(fn ($ip) => (string) $ip)
                ->values()
                ->all();
        });

        if (! is_array($ips)) {
            $this->forgetCache();
            $ips = BlockedIp::query()
                ->where('is_active', true)
                ->pluck('ip_address')
                ->map(fn ($ip) => (string) $ip)
                ->values()
                ->all();
        }

        return collect($ips)->mapWithKeys(fn (string $ip) => [$ip => true]);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('blocked_ips');
        } catch (\Throwable) {
            return false;
        }
    }

    public function normalize(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }
}
