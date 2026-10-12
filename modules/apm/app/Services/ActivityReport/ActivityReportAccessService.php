<?php

namespace App\Services\ActivityReport;

use App\Models\ActivityReport;

class ActivityReportAccessService
{
    /** @var list<string> */
    private const EDITABLE_STATUSES = ['draft', 'returned'];

    /**
     * @param  object|ActivityReport  $sourceOrReport  Source memo or ActivityReport (uses nested source attrs)
     * @return list<int>
     */
    public function participantStaffIds(object $sourceOrReport): array
    {
        $source = $sourceOrReport;
        if ($sourceOrReport instanceof ActivityReport && $sourceOrReport->relationLoaded('source') && $sourceOrReport->source) {
            $source = $sourceOrReport->source;
        }

        $ids = [];

        foreach (['staff_id', 'responsible_person_id', 'created_by', 'updated_by'] as $attr) {
            if (isset($source->{$attr}) && is_numeric($source->{$attr}) && (int) $source->{$attr} > 0) {
                $ids[] = (int) $source->{$attr};
            }
        }

        $raw = $source->internal_participants ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?? [];
        }
        if (is_array($raw)) {
            $ids = array_merge($ids, $this->flattenParticipantIds($raw));
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));

        return $ids;
    }

    public function canView(object $sourceOrReport, int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        return in_array($staffId, $this->participantStaffIds($sourceOrReport), true);
    }

    public function isEditableStatus(object $sourceOrReport): bool
    {
        if (! $sourceOrReport instanceof ActivityReport) {
            return true;
        }

        $status = strtolower(trim((string) ($sourceOrReport->overall_status ?? 'draft')));
        if ($status === '') {
            $status = 'draft';
        }

        return in_array($status, self::EDITABLE_STATUSES, true);
    }

    /**
     * @param  bool  $privileged  Admin / registry processor — may edit without being a memo participant
     */
    public function canEdit(object $sourceOrReport, int $staffId, bool $privileged = false): bool
    {
        if (! $this->isEditableStatus($sourceOrReport)) {
            return false;
        }

        if ($privileged) {
            return true;
        }

        return $this->canView($sourceOrReport, $staffId);
    }

    /**
     * @param  mixed  $data
     * @return list<int>
     */
    private function flattenParticipantIds(mixed $data): array
    {
        $ids = [];

        if (! is_array($data)) {
            if (is_numeric($data)) {
                $ids[] = (int) $data;
            }

            return $ids;
        }

        foreach ($data as $key => $item) {
            if (is_array($item)) {
                if (isset($item['staff_id']) && is_numeric($item['staff_id'])) {
                    $ids[] = (int) $item['staff_id'];
                } elseif (isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (is_numeric($key)) {
                    $ids[] = (int) $key;
                    $ids = array_merge($ids, $this->flattenParticipantIds($item));
                } else {
                    $ids = array_merge($ids, $this->flattenParticipantIds($item));
                }
            } elseif (is_numeric($key)) {
                $ids[] = (int) $key;
            } elseif (is_numeric($item)) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }
}
