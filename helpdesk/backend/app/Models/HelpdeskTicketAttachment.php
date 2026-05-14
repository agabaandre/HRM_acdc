<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskTicketAttachment extends Model
{
    protected $table = 'helpdesk_ticket_attachments';

    protected $fillable = [
        'ticket_id',
        'disk',
        'path',
        'original_name',
        'size_bytes',
        'mime_type',
        'uploaded_by',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
