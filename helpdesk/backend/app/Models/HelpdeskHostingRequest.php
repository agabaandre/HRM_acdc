<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskHostingRequest extends Model
{
    public const CATEGORY_CLOUD = 'cloud';

    public const CATEGORY_ON_PREMISES = 'on_premises';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_HOD = 'pending_hod';

    public const STATUS_HOD_APPROVED = 'hod_approved';

    public const STATUS_HOD_REJECTED = 'hod_rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'helpdesk_hosting_requests';

    protected $fillable = [
        'request_number',
        'status',
        'category',
        'title',
        'description',
        'cloud_provider',
        'environment_notes',
        'requester_user_id',
        'requester_staff_id',
        'requester_name',
        'requester_division_id',
        'requester_division_name',
        'on_behalf_of_staff_id',
        'hod_staff_id',
        'hod_name',
        'hod_decided_at',
        'hod_decided_by_user_id',
        'hod_decision_notes',
        'processed_by_user_id',
        'processed_at',
        'process_notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'requester_user_id' => 'integer',
            'requester_staff_id' => 'integer',
            'requester_division_id' => 'integer',
            'on_behalf_of_staff_id' => 'integer',
            'hod_staff_id' => 'integer',
            'hod_decided_at' => 'datetime',
            'hod_decided_by_user_id' => 'integer',
            'processed_by_user_id' => 'integer',
            'processed_at' => 'datetime',
            'created_by_user_id' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public static function generateRequestNumber(): string
    {
        $year = date('Y');
        $latest = self::query()
            ->where('request_number', 'like', "HR-{$year}-%")
            ->orderByDesc('id')
            ->value('request_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/HR-\d{4}-(\d+)/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('HR-%s-%04d', $year, $seq);
    }
}
