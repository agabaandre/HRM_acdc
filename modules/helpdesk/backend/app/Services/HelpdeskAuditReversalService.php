<?php

namespace App\Services;

use App\Models\HelpdeskAuditLog;
use App\Models\HelpdeskKbArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class HelpdeskAuditReversalService
{
    /** @var list<string> */
    private const REVERSIBLE_PREFIXES = [
        'kb_article.',
    ];

    public function canReverse(HelpdeskAuditLog $log): bool
    {
        if ($log->action === 'audit.reversed') {
            return false;
        }

        if ($this->wasAlreadyReversed($log)) {
            return false;
        }

        foreach (self::REVERSIBLE_PREFIXES as $prefix) {
            if (str_starts_with($log->action, $prefix)) {
                return match ($log->action) {
                    'kb_article.created' => $log->auditable_id !== null,
                    'kb_article.updated' => is_array($log->old_values) && $log->auditable_id !== null,
                    'kb_article.deleted' => is_array($log->old_values) && $log->auditable_id !== null,
                    default => false,
                };
            }
        }

        return false;
    }

    /**
     * @return array{message: string, detail: string}
     */
    public function reverse(
        HelpdeskAuditLog $log,
        string $actionType,
        string $reason,
        User $admin,
        HelpdeskAuditLogger $auditLogger,
    ): array {
        if (! $this->canReverse($log)) {
            throw new InvalidArgumentException('This audit entry cannot be reversed.');
        }

        if (! in_array($actionType, ['restore', 'delete'], true)) {
            throw new InvalidArgumentException('Invalid reversal action.');
        }

        $detail = DB::transaction(function () use ($log, $actionType): string {
            if (str_starts_with($log->action, 'kb_article.')) {
                return $this->reverseKbArticle($log, $actionType);
            }

            throw new InvalidArgumentException('No reversal handler for this action.');
        });

        $auditLogger->log(
            'audit.reversed',
            HelpdeskAuditLog::class,
            $log->id,
            null,
            [
                'original_action' => $log->action,
                'original_log_id' => $log->id,
                'reversal_action_type' => $actionType,
                'reason' => $reason,
                'reversed_by_user_id' => $admin->id,
                'detail' => $detail,
            ],
        );

        return [
            'message' => 'Audit log reversed successfully.',
            'detail' => $detail,
        ];
    }

    private function wasAlreadyReversed(HelpdeskAuditLog $log): bool
    {
        return HelpdeskAuditLog::query()
            ->where('action', 'audit.reversed')
            ->where('auditable_type', HelpdeskAuditLog::class)
            ->where('auditable_id', $log->id)
            ->exists();
    }

    private function reverseKbArticle(HelpdeskAuditLog $log, string $actionType): string
    {
        $articleId = (int) $log->auditable_id;
        $old = $this->cleanValues($log->old_values);

        return match ($log->action) {
            'kb_article.created' => $this->deleteKbArticle($articleId),
            'kb_article.updated' => $actionType === 'delete'
                ? $this->deleteKbArticle($articleId)
                : $this->restoreKbArticleRow($articleId, $old, updating: true),
            'kb_article.deleted' => $actionType === 'delete'
                ? 'Record already deleted — no further action.'
                : $this->restoreKbArticleRow($articleId, $old, updating: false),
            default => throw new InvalidArgumentException('Unsupported KB article action.'),
        };
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    private function cleanValues(?array $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        unset($values['@timestamp']);

        return $values;
    }

    private function deleteKbArticle(int $articleId): string
    {
        $article = HelpdeskKbArticle::query()->find($articleId);
        if ($article === null) {
            throw new RuntimeException('KB article no longer exists.');
        }
        $article->delete();

        return "Deleted KB article #{$articleId}.";
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function restoreKbArticleRow(int $articleId, array $values, bool $updating): string
    {
        if ($values === []) {
            throw new RuntimeException('No stored values to restore.');
        }

        $payload = array_intersect_key($values, array_flip([
            'category_id', 'question', 'answer', 'sort_order', 'is_active',
        ]));

        if ($payload === []) {
            throw new RuntimeException('Stored values are incomplete.');
        }

        $existing = HelpdeskKbArticle::query()->find($articleId);

        if ($updating) {
            if ($existing === null) {
                throw new RuntimeException('KB article no longer exists.');
            }
            $existing->fill($payload);
            $existing->save();

            return "Restored KB article #{$articleId} to its previous state.";
        }

        if ($existing !== null) {
            $existing->fill($payload);
            $existing->save();

            return "Restored KB article #{$articleId} (record already existed — updated in place).";
        }

        $article = new HelpdeskKbArticle($payload);
        $article->id = $articleId;
        $article->save();

        return "Re-created KB article #{$articleId}.";
    }

    public function defaultReversalAction(string $action): string
    {
        return match ($action) {
            'kb_article.created' => 'delete',
            'kb_article.updated' => 'restore',
            'kb_article.deleted' => 'restore',
            default => 'restore',
        };
    }

    public function reversalActionLabel(string $action, string $actionType): string
    {
        if ($action === 'kb_article.created' && $actionType === 'delete') {
            return 'Delete created article';
        }
        if ($action === 'kb_article.deleted' && $actionType === 'restore') {
            return 'Restore deleted article';
        }
        if ($action === 'kb_article.updated' && $actionType === 'restore') {
            return 'Restore previous values';
        }
        if ($action === 'kb_article.updated' && $actionType === 'delete') {
            return 'Delete article';
        }

        return Str::headline($actionType);
    }
}
