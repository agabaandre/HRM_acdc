<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppGroup extends Model
{
    public const TYPE_STANDARD = 'standard';

    public const TYPE_ALL_STAFF = 'all_staff';

    public const TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_ALL_STAFF,
    ];

    protected $table = 'whatsapp_groups';

    protected $primaryKey = 'jid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'jid',
        'name',
        'description',
        'group_type',
        'is_bot_on',
        'is_chat_bot_on',
        'is_img_on',
        'is_91_only',
        'is_auto_sticker_on',
        'is_rank_notif_on',
        'total_msg_count',
        'synced_at',
    ];

    protected $casts = [
        'is_bot_on' => 'boolean',
        'is_chat_bot_on' => 'boolean',
        'is_img_on' => 'boolean',
        'is_91_only' => 'boolean',
        'is_auto_sticker_on' => 'boolean',
        'is_rank_notif_on' => 'boolean',
        'total_msg_count' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(WhatsAppGroupMember::class, 'group_jid', 'jid');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'group_jid', 'jid');
    }
}
