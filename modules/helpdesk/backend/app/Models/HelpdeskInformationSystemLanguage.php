<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HelpdeskInformationSystemLanguage extends Model
{
    protected $table = 'helpdesk_information_system_languages';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(
            HelpdeskInformationSystem::class,
            'helpdesk_information_system_language',
            'language_id',
            'information_system_id',
        );
    }
}
