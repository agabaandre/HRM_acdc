<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone',
        'connected',
        'registered',
        'pairing_code',
        'last_error',
        'last_connected_at',
        'last_sync_at',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'registered' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'connected' => false,
            'registered' => false,
        ]);
    }
}
