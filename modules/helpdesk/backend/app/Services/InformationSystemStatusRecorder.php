<?php

namespace App\Services;

use App\Models\HelpdeskInformationSystemStatusEvent;

class InformationSystemStatusRecorder
{
    public function record(
        string $entityType,
        int $entityId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedByUserId = null,
        ?string $note = null,
    ): void {
        if ($fromStatus !== null && $fromStatus === $toStatus) {
            return;
        }

        HelpdeskInformationSystemStatusEvent::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_user_id' => $changedByUserId,
            'changed_at' => now(),
            'note' => $note,
        ]);
    }
}
