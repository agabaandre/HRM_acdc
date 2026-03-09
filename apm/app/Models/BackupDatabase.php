<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class BackupDatabase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'host',
        'port',
        'username',
        'password',
        'is_active',
        'is_default',
        'priority',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'priority' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the decrypted password
     */
    public function getDecryptedPasswordAttribute()
    {
        try {
            return Crypt::decryptString($this->password);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted password
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Scope to get active databases
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('name', 'asc');
    }

    /**
     * Get default database
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get all active databases ordered by priority
     */
    public static function getActiveDatabases()
    {
        return static::active()->orderedByPriority()->get();
    }
}
