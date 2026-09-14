<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppGroupMember extends Model
{
    protected $table = 'whatsapp_group_members';

    protected $fillable = [
        'group_jid',
        'member_jid',
        'phone',
        'lid',
        'username',
        'is_admin',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGroup::class, 'group_jid', 'jid');
    }
}
