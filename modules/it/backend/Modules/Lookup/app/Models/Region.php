<?php

namespace Modules\Lookup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'regions';

    public $timestamps = false;

    protected $fillable = ['region_name'];

    public function memberStates(): HasMany
    {
        return $this->hasMany(MemberState::class, 'region_id', 'id');
    }
}
