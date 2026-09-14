<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskAgentMonthlyReport extends Model
{
    protected $table = 'helpdesk_agent_monthly_reports';

    protected $fillable = [
        'user_id',
        'period_year',
        'period_month',
        'metrics_json',
        'ai_summary',
        'ai_model',
        'storage_path',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'metrics_json' => 'array',
            'emailed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
