<?php

namespace App\Services;

use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskRiskMatrixEntry;
use App\Models\HelpdeskSlaRule;
use App\Models\HelpdeskTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryBusinessUnitRemapService
{
    /**
     * Move all references from $source to $target, then delete $source.
     *
     * @return array{tickets:int,kb_articles:int,sla_rules:int,agent_links:int,group_links:int,risk_matrix:int}
     */
    public function remapCategory(HelpdeskCategory $source, HelpdeskCategory $target): array
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages([
                'target_category_id' => 'Choose a different category to remap into.',
            ]);
        }

        $counts = [
            'tickets' => 0,
            'kb_articles' => 0,
            'sla_rules' => 0,
            'agent_links' => 0,
            'group_links' => 0,
            'risk_matrix' => 0,
        ];

        DB::transaction(function () use ($source, $target, &$counts) {
            $from = $source->id;
            $to = $target->id;
            $targetBuId = (int) $target->business_unit_id;

            $ticketUpdate = [
                'category_id' => $to,
                'updated_at' => now(),
            ];
            if ($targetBuId > 0) {
                $ticketUpdate['business_unit_id'] = $targetBuId;
            }

            $counts['tickets'] = HelpdeskTicket::query()
                ->where('category_id', $from)
                ->update($ticketUpdate);

            $counts['kb_articles'] = HelpdeskKbArticle::query()
                ->where('category_id', $from)
                ->update(['category_id' => $to, 'updated_at' => now()]);

            $counts['sla_rules'] = HelpdeskSlaRule::query()
                ->where('category_id', $from)
                ->update(['category_id' => $to, 'updated_at' => now()]);

            $counts['agent_links'] = $this->mergePivot(
                'helpdesk_agent_categories',
                'user_id',
                'category_id',
                $from,
                $to,
            );

            $counts['group_links'] = $this->mergePivot(
                'helpdesk_support_group_categories',
                'group_id',
                'category_id',
                $from,
                $to,
            );

            $counts['risk_matrix'] = $this->mergeRiskMatrix($from, $to);

            $source->delete();
        });

        TicketReadCache::bust(['tickets', 'reports']);

        return $counts;
    }

    /**
     * Move categories, tickets, and email messages from $source to $target, then delete $source.
     *
     * @return array{categories:int,tickets:int,email_messages:int}
     */
    public function remapBusinessUnit(HelpdeskBusinessUnit $source, HelpdeskBusinessUnit $target): array
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages([
                'target_business_unit_id' => 'Choose a different business unit to remap into.',
            ]);
        }

        $counts = [
            'categories' => 0,
            'tickets' => 0,
            'email_messages' => 0,
        ];

        DB::transaction(function () use ($source, $target, &$counts) {
            $from = $source->id;
            $to = $target->id;

            $counts['categories'] = HelpdeskCategory::query()
                ->where('business_unit_id', $from)
                ->update(['business_unit_id' => $to, 'updated_at' => now()]);

            $counts['tickets'] = HelpdeskTicket::query()
                ->where('business_unit_id', $from)
                ->update(['business_unit_id' => $to, 'updated_at' => now()]);

            $counts['email_messages'] = HelpdeskEmailMessage::query()
                ->where('business_unit_id', $from)
                ->update(['business_unit_id' => $to, 'updated_at' => now()]);

            $source->delete();
        });

        TicketReadCache::bust(['tickets', 'reports']);

        return $counts;
    }

    /**
     * Move pivot rows; drop source rows that would collide with an existing target row.
     */
    private function mergePivot(string $table, string $ownerKey, string $categoryKey, int $from, int $to): int
    {
        $ownerIdsAlreadyOnTarget = DB::table($table)
            ->where($categoryKey, $to)
            ->pluck($ownerKey)
            ->all();

        $moved = 0;
        if ($ownerIdsAlreadyOnTarget !== []) {
            $moved += DB::table($table)
                ->where($categoryKey, $from)
                ->whereNotIn($ownerKey, $ownerIdsAlreadyOnTarget)
                ->update([$categoryKey => $to]);

            DB::table($table)
                ->where($categoryKey, $from)
                ->whereIn($ownerKey, $ownerIdsAlreadyOnTarget)
                ->delete();
        } else {
            $moved = DB::table($table)
                ->where($categoryKey, $from)
                ->update([$categoryKey => $to]);
        }

        return $moved;
    }

    private function mergeRiskMatrix(int $from, int $to): int
    {
        $staffAlreadyOnTarget = HelpdeskRiskMatrixEntry::query()
            ->where('category_id', $to)
            ->pluck('staff_id')
            ->all();

        $moved = 0;
        if ($staffAlreadyOnTarget !== []) {
            $moved += HelpdeskRiskMatrixEntry::query()
                ->where('category_id', $from)
                ->whereNotIn('staff_id', $staffAlreadyOnTarget)
                ->update(['category_id' => $to, 'updated_at' => now()]);

            HelpdeskRiskMatrixEntry::query()
                ->where('category_id', $from)
                ->whereIn('staff_id', $staffAlreadyOnTarget)
                ->delete();
        } else {
            $moved = HelpdeskRiskMatrixEntry::query()
                ->where('category_id', $from)
                ->update(['category_id' => $to, 'updated_at' => now()]);
        }

        return $moved;
    }
}
