<?php

namespace App\Services;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskRiskMatrixEntry;

class TicketPriorityResolver
{
    /** @var list<string> */
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function resolveForCreate(HelpdeskCategory $category, int $requesterStaffId): string
    {
        $categoryPriority = $this->normalize($category->default_priority ?? 'medium');

        if ($requesterStaffId < 1) {
            return $categoryPriority;
        }

        $matrixPriority = $this->riskMatrixPriority($requesterStaffId, (int) $category->id);

        return $matrixPriority ?? $categoryPriority;
    }

    private function riskMatrixPriority(int $staffId, int $categoryId): ?string
    {
        $categorySpecific = HelpdeskRiskMatrixEntry::query()
            ->where('staff_id', $staffId)
            ->where('is_active', true)
            ->where('category_id', $categoryId)
            ->value('priority');

        if (is_string($categorySpecific) && $categorySpecific !== '') {
            return $this->normalize($categorySpecific);
        }

        $global = HelpdeskRiskMatrixEntry::query()
            ->where('staff_id', $staffId)
            ->where('is_active', true)
            ->whereNull('category_id')
            ->value('priority');

        if (is_string($global) && $global !== '') {
            return $this->normalize($global);
        }

        return null;
    }

    private function normalize(?string $priority): string
    {
        $p = strtolower(trim((string) $priority));

        return in_array($p, self::PRIORITIES, true) ? $p : 'medium';
    }
}
