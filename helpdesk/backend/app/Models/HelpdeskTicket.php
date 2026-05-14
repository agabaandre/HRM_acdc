<?php

namespace App\Models;

use App\Services\TicketAssignmentNotifier;
use App\Services\TicketHistoryLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskTicket extends Model
{
    protected $table = 'helpdesk_tickets';

    protected static function booted(): void
    {
        static::created(function (HelpdeskTicket $ticket) {
            app(TicketHistoryLogger::class)->log($ticket, 'ticket.created', auth()->id(), [
                'ticket_number' => $ticket->ticket_number,
            ]);
            if ($ticket->assigned_user_id) {
                app(TicketAssignmentNotifier::class)->notifyOnInitialAssign($ticket);
            }
        });

        static::updated(function (HelpdeskTicket $ticket) {
            $changes = $ticket->getChanges();
            unset($changes['updated_at']);
            if ($changes !== []) {
                app(TicketHistoryLogger::class)->log($ticket, 'ticket.updated', auth()->id(), [
                    'changes' => $changes,
                ]);
            }
            app(TicketAssignmentNotifier::class)->notifyIfAssigneeChanged($ticket);
        });
    }

    protected $fillable = [
        'created_by_user_id',
        'ticket_number',
        'category_id',
        'subject',
        'description',
        'resolution_summary',
        'resolution_confirm_token',
        'resolution_confirmed_at',
        'resolution_submitted_by_user_id',
        'resolved_by_user_id',
        'priority',
        'status',
        'source',
        'agent_logged_for_requester',
        'requester_staff_id',
        'requester_name',
        'requester_email',
        'assigned_user_id',
        'directorate_id',
        'division_id',
        'country_id',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'sla_response_due_at',
        'sla_resolution_due_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'agent_logged_for_requester' => 'boolean',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'resolution_confirmed_at' => 'datetime',
            'sla_response_due_at' => 'datetime',
            'sla_resolution_due_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function resolutionSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolution_submitted_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HelpdeskTicketComment::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HelpdeskTicketAttachment::class, 'ticket_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(HelpdeskTicketHistory::class, 'ticket_id')->orderByDesc('id');
    }
}
