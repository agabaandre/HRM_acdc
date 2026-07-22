<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskEmailMessage extends Model
{
    protected $table = 'helpdesk_email_messages';

    protected $fillable = [
        'business_unit_id',
        'graph_message_id',
        'internet_message_id',
        'ticket_id',
        'from_email',
        'subject',
        'processed_at',
        'raw_meta',
    ];

    protected function casts(): array
    {
        return [
            'business_unit_id' => 'integer',
            'ticket_id' => 'integer',
            'processed_at' => 'datetime',
            'raw_meta' => 'array',
        ];
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(HelpdeskBusinessUnit::class, 'business_unit_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }
}
