<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BlockSuspiciousAccessRequest;
use App\Http\Requests\Api\V1\Admin\StoreBlockedAccessRequest;
use App\Models\BlockedEmail;
use App\Models\BlockedIp;
use App\Services\AuditLogService;
use App\Services\BlockedEmailService;
use App\Services\BlockedIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedIpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        $query = BlockedIp::query()
            ->with([
                'blocker:id,name,email',
                'unblocker:id,name,email',
            ])
            ->orderByDesc('updated_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($request->filled('q')) {
            $like = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($q) use ($like) {
                $q->where('ip_address', 'like', $like)
                    ->orWhere('reason', 'like', $like);
            });
        }

        $rows = $query->limit(500)->get()->map(fn (BlockedIp $row) => $this->transformIp($row));

        return response()->json(['data' => $rows]);
    }

    public function indexEmails(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        $query = BlockedEmail::query()
            ->with([
                'blocker:id,name,email',
                'unblocker:id,name,email',
            ])
            ->orderByDesc('updated_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($request->filled('q')) {
            $like = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('reason', 'like', $like);
            });
        }

        $rows = $query->limit(500)->get()->map(fn (BlockedEmail $row) => $this->transformEmail($row));

        return response()->json(['data' => $rows]);
    }

    public function store(
        StoreBlockedAccessRequest $request,
        BlockedIpService $blockedIps,
        BlockedEmailService $blockedEmails,
        AuditLogService $audit,
    ): JsonResponse {
        $data = $request->validated();
        $scope = (string) $data['scope'];
        $reason = (string) $data['reason'];
        $auditLogId = isset($data['audit_log_id']) ? (int) $data['audit_log_id'] : null;
        $actor = $request->user();

        $wantIp = in_array($scope, ['ip', 'both'], true);
        $wantEmail = in_array($scope, ['email', 'both'], true);
        $ip = $wantIp ? (string) ($data['ip_address'] ?? '') : '';
        $email = $wantEmail ? (string) ($data['email'] ?? '') : '';

        if ($wantIp && $request->ip() !== null && $blockedIps->normalize($request->ip()) === $blockedIps->normalize($ip)) {
            return response()->json([
                'message' => 'You cannot block your own current IP address.',
            ], 422);
        }

        $actorEmail = $blockedEmails->normalize($actor?->email);
        if ($wantEmail && $actorEmail !== null && $blockedEmails->normalize($email) === $actorEmail) {
            return response()->json([
                'message' => 'You cannot block your own email address.',
            ], 422);
        }

        $ipRow = null;
        $emailRow = null;
        $parts = [];

        if ($wantIp) {
            $ipRow = $blockedIps->block($ip, $reason, $actor, $auditLogId);
            $parts[] = "IP {$ipRow->ip_address}";

            $audit->log('Blocked IP address', [
                'event_type' => 'ip_blocked',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'target_table' => 'blocked_ips',
                'target_id' => $ipRow->id,
                'new_values' => [
                    'ip_address' => $ipRow->ip_address,
                    'reason' => $ipRow->reason,
                    'scope' => $scope,
                    'audit_log_id' => $ipRow->audit_log_id,
                ],
            ]);
        }

        if ($wantEmail) {
            $emailRow = $blockedEmails->block($email, $reason, $actor, $auditLogId);
            $parts[] = "email {$emailRow->email}";

            $audit->log('Blocked email address', [
                'event_type' => 'email_blocked',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'target_table' => 'blocked_emails',
                'target_id' => $emailRow->id,
                'new_values' => [
                    'email' => $emailRow->email,
                    'reason' => $emailRow->reason,
                    'scope' => $scope,
                    'audit_log_id' => $emailRow->audit_log_id,
                ],
            ]);
        }

        return response()->json([
            'message' => count($parts) > 0
                ? 'Blocked '.implode(' and ', $parts).'.'
                : 'Nothing was blocked.',
            'data' => [
                'scope' => $scope,
                'ip' => $ipRow ? $this->transformIp($ipRow) : null,
                'email' => $emailRow ? $this->transformEmail($emailRow) : null,
            ],
        ], 201);
    }

    public function blockSuspicious(
        BlockSuspiciousAccessRequest $request,
        BlockedIpService $blockedIps,
        BlockedEmailService $blockedEmails,
        AuditLogService $audit,
    ): JsonResponse {
        $reason = (string) $request->validated('reason');
        $scope = (string) $request->validated('scope');
        $actor = $request->user();

        $ipResult = ['blocked' => [], 'skipped' => [], 'count' => 0];
        $emailResult = ['blocked' => [], 'skipped' => [], 'count' => 0];

        if (in_array($scope, ['ip', 'both'], true)) {
            $ipResult = $blockedIps->blockAllSuspicious($reason, $actor, $request->ip());
        }

        if (in_array($scope, ['email', 'both'], true)) {
            $emailResult = $blockedEmails->blockAllSuspicious($reason, $actor, $actor?->email);
        }

        $audit->log('Blocked suspicious access', [
            'event_type' => 'access_block_suspicious_bulk',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'blocked_ips',
            'new_values' => [
                'reason' => $reason,
                'scope' => $scope,
                'ips_blocked' => $ipResult['count'],
                'emails_blocked' => $emailResult['count'],
                'ips_skipped' => $ipResult['skipped'],
                'emails_skipped' => $emailResult['skipped'],
            ],
        ]);

        $total = $ipResult['count'] + $emailResult['count'];

        return response()->json([
            'message' => $total > 0
                ? "Blocked {$ipResult['count']} IP(s) and {$emailResult['count']} email(s)."
                : 'No new suspicious IPs or emails to block.',
            'data' => [
                'scope' => $scope,
                'ips' => [
                    'blocked' => array_map(fn (BlockedIp $row) => $this->transformIp($row), $ipResult['blocked']),
                    'skipped' => $ipResult['skipped'],
                    'count' => $ipResult['count'],
                ],
                'emails' => [
                    'blocked' => array_map(fn (BlockedEmail $row) => $this->transformEmail($row), $emailResult['blocked']),
                    'skipped' => $emailResult['skipped'],
                    'count' => $emailResult['count'],
                ],
                'count' => $total,
            ],
        ]);
    }

    public function destroy(
        BlockedIp $blockedIp,
        Request $request,
        BlockedIpService $blockedIps,
        AuditLogService $audit,
    ): JsonResponse {
        if (! $blockedIp->is_active) {
            return response()->json([
                'message' => 'This IP is already unblocked.',
                'data' => $this->transformIp($blockedIp),
            ]);
        }

        $row = $blockedIps->unblock($blockedIp, $request->user());

        $audit->log('Unblocked IP address', [
            'event_type' => 'ip_unblocked',
            'http_method' => 'DELETE',
            'request_uri' => $request->path(),
            'target_table' => 'blocked_ips',
            'target_id' => $row->id,
            'old_values' => [
                'ip_address' => $row->ip_address,
                'reason' => $row->reason,
            ],
        ]);

        return response()->json([
            'message' => "IP {$row->ip_address} has been unblocked.",
            'data' => $this->transformIp($row),
        ]);
    }

    public function destroyEmail(
        BlockedEmail $blockedEmail,
        Request $request,
        BlockedEmailService $blockedEmails,
        AuditLogService $audit,
    ): JsonResponse {
        if (! $blockedEmail->is_active) {
            return response()->json([
                'message' => 'This email is already unblocked.',
                'data' => $this->transformEmail($blockedEmail),
            ]);
        }

        $row = $blockedEmails->unblock($blockedEmail, $request->user());

        $audit->log('Unblocked email address', [
            'event_type' => 'email_unblocked',
            'http_method' => 'DELETE',
            'request_uri' => $request->path(),
            'target_table' => 'blocked_emails',
            'target_id' => $row->id,
            'old_values' => [
                'email' => $row->email,
                'reason' => $row->reason,
            ],
        ]);

        return response()->json([
            'message' => "Email {$row->email} has been unblocked.",
            'data' => $this->transformEmail($row),
        ]);
    }

    private function transformIp(BlockedIp $row): array
    {
        return [
            'id' => $row->id,
            'type' => 'ip',
            'ip_address' => $row->ip_address,
            'reason' => $row->reason,
            'is_active' => (bool) $row->is_active,
            'audit_log_id' => $row->audit_log_id,
            'blocked_by' => $row->blocker ? [
                'id' => $row->blocker->id,
                'name' => $row->blocker->name,
                'email' => $row->blocker->email,
            ] : null,
            'unblocked_by' => $row->unblocker ? [
                'id' => $row->unblocker->id,
                'name' => $row->unblocker->name,
                'email' => $row->unblocker->email,
            ] : null,
            'unblocked_at' => $row->unblocked_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    private function transformEmail(BlockedEmail $row): array
    {
        return [
            'id' => $row->id,
            'type' => 'email',
            'email' => $row->email,
            'reason' => $row->reason,
            'is_active' => (bool) $row->is_active,
            'audit_log_id' => $row->audit_log_id,
            'blocked_by' => $row->blocker ? [
                'id' => $row->blocker->id,
                'name' => $row->blocker->name,
                'email' => $row->blocker->email,
            ] : null,
            'unblocked_by' => $row->unblocker ? [
                'id' => $row->unblocker->id,
                'name' => $row->unblocker->name,
                'email' => $row->unblocker->email,
            ] : null,
            'unblocked_at' => $row->unblocked_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
