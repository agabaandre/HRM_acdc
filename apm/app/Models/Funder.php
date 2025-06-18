<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funder extends Model
{
    use HasFactory;

    protected $table = 'funders';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * A funder can have many fund codes.
     */
    public function fundCodes(): HasMany
    {
        return $this->hasMany(FundCode::class, 'funder_id');
    }
}
