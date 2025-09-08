<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class CostItem extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'cost_type'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
