<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'action',
        'resource_type',
        'resource_id',
        'route_name',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'description',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by resource type.
     */
    public function scopeByResourceType($query, $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by route.
     */
    public function scopeByRoute($query, $routeName)
    {
        return $query->where('route_name', $routeName);
    }

    /**
     * Get formatted action badge class.
     */
    public function getActionBadgeClassAttribute(): string
    {
        return match(strtoupper($this->action)) {
            'CREATE', 'INSERT' => 'bg-success',
            'UPDATE' => 'bg-warning',
            'DELETE' => 'bg-danger',
            'APPROVE' => 'bg-success',
            'REJECT' => 'bg-danger',
            'LOGIN' => 'bg-info',
            'LOGOUT' => 'bg-secondary',
            default => 'bg-primary'
        };
    }

    /**
     * Get formatted action icon.
     */
    public function getActionIconAttribute(): string
    {
        return match(strtoupper($this->action)) {
            'CREATE', 'INSERT' => 'bx-plus',
            'UPDATE' => 'bx-edit',
            'DELETE' => 'bx-trash',
            'APPROVE' => 'bx-check',
            'REJECT' => 'bx-x',
            'LOGIN' => 'bx-log-in',
            'LOGOUT' => 'bx-log-out',
            default => 'bx-info-circle'
        };
    }

    /**
     * Get human readable description.
     */
    public function getHumanDescriptionAttribute(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $user = $this->user_name ?: 'Unknown User';
        $action = strtolower($this->action);
        $resource = $this->resource_type;

        return "{$user} {$action} {$resource}" . ($this->resource_id ? " #{$this->resource_id}" : '');
    }
}
