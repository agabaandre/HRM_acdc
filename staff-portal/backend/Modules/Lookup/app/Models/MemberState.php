<?php

namespace Modules\Lookup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Member states / nationalities (legacy table: nationalities).
 */
class MemberState extends Model
{
    protected $table = 'nationalities';

    protected $primaryKey = 'nationality_id';

    public $timestamps = false;

    protected $fillable = [
        'nationality',
        'nationality_name',
        'continent',
        'region_id',
        'iso2',
        'iso3',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }
}
