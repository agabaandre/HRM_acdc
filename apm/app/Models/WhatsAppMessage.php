<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'group_jid',
        'wa_message_id',
        'sender_jid',
        'sender_phone',
        'sender_name',
        'from_me',
        'message_type',
        'body',
        'media_path',
        'media_mime',
        'media_size',
        'sent_at',
    ];

    protected $casts = [
        'from_me' => 'boolean',
        'media_size' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGroup::class, 'group_jid', 'jid');
    }

    public function hasPreviewableMedia(): bool
    {
        if (! $this->media_path || ! $this->media_mime) {
            return false;
        }

        return str_starts_with((string) $this->media_mime, 'image/');
    }
}
