<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PraOrgUnitMapping extends Model
{
    protected $table = 'pra_org_unit_mappings';

    protected $fillable = [
        'pra_code',
        'pra_division_id',
        'pra_name',
        'entity_type',
        'local_division_id',
        'local_directorate_id',
        'match_source',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'local_division_id' => 'integer',
            'local_directorate_id' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function localDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'local_division_id');
    }

    public function localDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'local_directorate_id');
    }
}
