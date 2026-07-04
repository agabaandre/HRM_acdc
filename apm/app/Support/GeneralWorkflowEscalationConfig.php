<?php

namespace App\Support;

use App\Models\SystemSetting;

final class GeneralWorkflowEscalationConfig
{
    public const WORKFLOW_ID = 1;

    /**
     * Approval orders (general workflow only) that receive escalation email when stale threshold is exceeded.
     *
     * @return list<int>
     */
    public static function escalationOrders(): array
    {
        $raw = (string) SystemSetting::get('general_workflow_stale_escalation_orders', '');
        if ($raw === '') {
            return [];
        }

        $orders = [];
        foreach (preg_split('/[\s,]+/', $raw) as $part) {
            $part = trim((string) $part);
            if ($part === '' || ! is_numeric($part)) {
                continue;
            }
            $n = (int) $part;
            if ($n > 0) {
                $orders[] = $n;
            }
        }

        return array_values(array_unique($orders));
    }
}
