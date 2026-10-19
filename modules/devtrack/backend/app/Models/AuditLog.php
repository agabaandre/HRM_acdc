<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_type',
        'external_integration_id',
        'user_name',
        'user_email',
        'action',
        'event_type',
        'http_method',
        'request_uri',
        'target_table',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'is_suspicious',
        'suspicious_reasons',
        'suspicious_resolved_at',
        'suspicious_resolved_by',
        'suspicious_resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'is_suspicious' => 'boolean',
            'suspicious_resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function externalIntegration(): BelongsTo
    {
        return $this->belongsTo(ExternalIntegration::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspicious_resolved_by');
    }

    public function isUnresolvedSuspicious(): bool
    {
        return (bool) $this->is_suspicious && $this->suspicious_resolved_at === null;
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeUnresolvedSuspicious(Builder $query): Builder
    {
        return $query->where('is_suspicious', true)->whereNull('suspicious_resolved_at');
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeResolvedSuspicious(Builder $query): Builder
    {
        return $query->where('is_suspicious', true)->whereNotNull('suspicious_resolved_at');
    }
}
