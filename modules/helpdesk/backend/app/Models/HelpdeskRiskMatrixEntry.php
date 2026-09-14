<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskRiskMatrixEntry extends Model
{
    protected $table = 'helpdesk_risk_matrix_entries';

    /** @var list<string> */
    protected $fillable = [
        'staff_id',
        'priority',
        'category_id',
        'notes',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'staff_id' => 'integer',
            'category_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }
}
