<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class Funder extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'funders';

    protected $fillable = [
        'name',
        'description',
        'contact_person',
        'email',
        'phone',
        'address',
        'website',
        'is_active',
        'is_partners',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'is_partners' => 'boolean',
    ];

    /**
     * A funder can have many fund codes.
     */
    public function fundCodes(): HasMany
    {
        return $this->hasMany(FundCode::class, 'funder_id');
    }
}
