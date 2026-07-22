<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskSoftwareRequest extends Model
{
    protected $table = 'helpdesk_software_requests';

    protected $fillable = [
        'request_number',
        'requester_user_id',
        'requester_name',
        'department',
        'division_id',
        'directorate_id',
        'division_name',
        'directorate_name',
        'email',
        'phone',
        'request_title',
        'problem_statement',
        'proposed_solution',
        'business_justification',
        'affected_stakeholders',
        'mandate_alignment',
        'priority',
        'desired_timeline',
        'budget_estimate',
        'existing_alternatives',
        'additional_comments',
        'status',
        'decision',
        'received_at',
        'team_lead_review_at',
        'assigned_ba_staff_id',
        'assigned_ba_name',
        'project_id',
        'project_team_formed_at',
        'team_lead_user_id',
    ];

    protected function casts(): array
    {
        return [
            'requester_user_id' => 'integer',
            'division_id' => 'integer',
            'directorate_id' => 'integer',
            'budget_estimate' => 'decimal:2',
            'received_at' => 'datetime',
            'team_lead_review_at' => 'datetime',
            'assigned_ba_staff_id' => 'integer',
            'project_team_formed_at' => 'datetime',
            'team_lead_user_id' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_user_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(HelpdeskSoftwareRequestTeamMember::class, 'software_request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(HelpdeskSoftwareRequestApproval::class, 'software_request_id');
    }

    public static function generateRequestNumber(): string
    {
        $year = date('Y');
        $latest = self::query()
            ->where('request_number', 'like', "SWR-{$year}-%")
            ->orderByDesc('id')
            ->value('request_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/SWR-\d{4}-(\d+)/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('SWR-%s-%04d', $year, $seq);
    }
}
