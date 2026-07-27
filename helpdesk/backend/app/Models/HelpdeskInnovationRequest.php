<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskInnovationRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'helpdesk_innovation_requests';

    protected $fillable = [
        'request_number',
        'status',
        'title',
        'description',
        'innovation_type',
        'requester_user_id',
        'requester_staff_id',
        'requester_name',
        'requester_division_id',
        'requester_division_name',
        'on_behalf_of_staff_id',
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
            ->where('request_number', 'like', "IR-{$year}-%")
            ->orderByDesc('id')
            ->value('request_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/IR-\d{4}-(\d+)/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('IR-%s-%04d', $year, $seq);
    }
}
