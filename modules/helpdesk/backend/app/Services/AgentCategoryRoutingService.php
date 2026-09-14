<?php

namespace App\Services;

use App\Models\HelpdeskSupportGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves effective issue categories for agents (direct + support group inheritance).
 */
class AgentCategoryRoutingService
{
    /**
     * Category IDs an agent may handle.
     * Empty list means no explicit category access (not eligible unless in a catch-all group).
     *
     * @return list<int>
     */
    public function effectiveCategoryIdsForUser(int $userId): array
    {
        $direct = DB::table('helpdesk_agent_categories')
            ->where('user_id', $userId)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fromGroups = DB::table('helpdesk_support_group_categories')
            ->join('helpdesk_support_group_members', 'helpdesk_support_group_members.group_id', '=', 'helpdesk_support_group_categories.group_id')
            ->join('helpdesk_support_groups', 'helpdesk_support_groups.id', '=', 'helpdesk_support_group_members.group_id')
            ->where('helpdesk_support_group_members.user_id', $userId)
            ->where('helpdesk_support_groups.is_active', true)
            ->distinct()
            ->pluck('helpdesk_support_group_categories.category_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($direct === [] && $fromGroups === []) {
            return [];
        }

        return array_values(array_unique(array_merge($direct, $fromGroups)));
    }

    public function agentHandlesCategory(int $userId, int $categoryId): bool
    {
        $profile = User::query()->with('helpdeskProfile')->find($userId)?->helpdeskProfile;
        if ($profile !== null && ! $profile->isEligibleForTicketRouting()) {
            return false;
        }

        $effective = $this->effectiveCategoryIdsForUser($userId);
        if ($effective !== []) {
            return in_array($categoryId, $effective, true);
        }

        // No direct/group categories: only eligible via an active catch-all support group.
        return $this->userBelongsToCatchAllGroup($userId);
    }

    public function userBelongsToCatchAllGroup(int $userId): bool
    {
        $groupIds = DB::table('helpdesk_support_group_members')
            ->join('helpdesk_support_groups', 'helpdesk_support_groups.id', '=', 'helpdesk_support_group_members.group_id')
            ->where('helpdesk_support_group_members.user_id', $userId)
            ->where('helpdesk_support_groups.is_active', true)
            ->pluck('helpdesk_support_groups.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($groupIds === []) {
            return false;
        }

        foreach ($groupIds as $groupId) {
            $hasCategories = DB::table('helpdesk_support_group_categories')
                ->where('group_id', $groupId)
                ->exists();
            if (! $hasCategories) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active groups that route the given category (empty group categories = all).
     *
     * @return Collection<int, HelpdeskSupportGroup>
     */
    public function eligibleGroupsForCategory(int $categoryId): Collection
    {
        return HelpdeskSupportGroup::query()
            ->where('is_active', true)
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (HelpdeskSupportGroup $group) use ($categoryId): bool {
                $catIds = $group->categories()->pluck('helpdesk_categories.id')->all();
                if ($catIds === []) {
                    return true;
                }

                return in_array($categoryId, array_map('intval', $catIds), true);
            })
            ->values();
    }

    /**
     * Agent user IDs who are members of the group and handle the category.
     *
     * @return list<int>
     */
    public function eligibleMemberUserIdsForGroup(HelpdeskSupportGroup $group, int $categoryId): array
    {
        $memberIds = $group->members()
            ->actsAsHelpdeskAgent()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter(
            $memberIds,
            fn (int $uid) => $this->agentHandlesCategory($uid, $categoryId)
        ));
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function supportGroupsForUser(int $userId): array
    {
        return DB::table('helpdesk_support_group_members')
            ->join('helpdesk_support_groups', 'helpdesk_support_groups.id', '=', 'helpdesk_support_group_members.group_id')
            ->where('helpdesk_support_group_members.user_id', $userId)
            ->where('helpdesk_support_groups.is_active', true)
            ->orderBy('helpdesk_support_groups.sort_order')
            ->orderBy('helpdesk_support_groups.name')
            ->get(['helpdesk_support_groups.id', 'helpdesk_support_groups.name', 'helpdesk_support_groups.slug'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
            ])
            ->all();
    }

    /**
     * Categories inherited from support group membership only.
     *
     * @return list<array{id: int, name: string}>
     */
    public function groupInheritedCategoriesForUser(int $userId): array
    {
        return DB::table('helpdesk_support_group_categories')
            ->join('helpdesk_support_group_members', 'helpdesk_support_group_members.group_id', '=', 'helpdesk_support_group_categories.group_id')
            ->join('helpdesk_support_groups', 'helpdesk_support_groups.id', '=', 'helpdesk_support_group_members.group_id')
            ->join('helpdesk_categories', 'helpdesk_categories.id', '=', 'helpdesk_support_group_categories.category_id')
            ->where('helpdesk_support_group_members.user_id', $userId)
            ->where('helpdesk_support_groups.is_active', true)
            ->where('helpdesk_categories.is_active', true)
            ->distinct()
            ->orderBy('helpdesk_categories.name')
            ->get(['helpdesk_categories.id', 'helpdesk_categories.name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function inheritedCategoriesForUser(int $userId): array
    {
        $direct = DB::table('helpdesk_agent_categories')
            ->where('user_id', $userId)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fromGroups = DB::table('helpdesk_support_group_categories')
            ->join('helpdesk_support_group_members', 'helpdesk_support_group_members.group_id', '=', 'helpdesk_support_group_categories.group_id')
            ->join('helpdesk_support_groups', 'helpdesk_support_groups.id', '=', 'helpdesk_support_group_members.group_id')
            ->join('helpdesk_categories', 'helpdesk_categories.id', '=', 'helpdesk_support_group_categories.category_id')
            ->where('helpdesk_support_group_members.user_id', $userId)
            ->where('helpdesk_support_groups.is_active', true)
            ->where('helpdesk_categories.is_active', true)
            ->distinct()
            ->get(['helpdesk_categories.id', 'helpdesk_categories.name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();

        if ($direct === [] && $fromGroups === []) {
            return [];
        }

        $directRows = DB::table('helpdesk_categories')
            ->whereIn('id', $direct)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();

        $merged = collect(array_merge($fromGroups, $directRows))
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();

        return $merged;
    }

    /**
     * Active agent user IDs eligible for automatic ticket routing (not disabled).
     *
     * @return list<int>
     */
    public function eligibleRoutingAgentUserIds(): array
    {
        return User::query()
            ->actsAsHelpdeskAgent()
            ->whereHas('helpdeskProfile', function ($q) {
                $q->where(function ($q) {
                    $q->where('is_agent_disabled', false)
                        ->orWhereNull('is_agent_disabled');
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * True when at least one eligible agent belongs to an active catch-all support group
     * (no categories on the group) and has no direct/group category restrictions.
     */
    public function hasEligibleCatchAllAgent(): bool
    {
        foreach ($this->eligibleRoutingAgentUserIds() as $userId) {
            if ($this->effectiveCategoryIdsForUser($userId) === [] && $this->userBelongsToCatchAllGroup($userId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Active category IDs that at least one eligible agent can handle.
     * Empty when no agents are configured (unless catch-all — then all active categories).
     *
     * @return list<int>
     */
    public function categoryIdsCoveredByEligibleAgents(): array
    {
        if ($this->hasEligibleCatchAllAgent()) {
            return DB::table('helpdesk_categories')
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $covered = [];
        foreach ($this->eligibleRoutingAgentUserIds() as $userId) {
            $covered = array_merge($covered, $this->effectiveCategoryIdsForUser($userId));
        }

        return array_values(array_unique(array_map('intval', $covered)));
    }

    /**
     * Business unit can appear on the request form when it has ≥1 active category
     * that an eligible agent is configured to handle.
     */
    public function businessUnitHasRoutableAgents(int $businessUnitId): bool
    {
        $query = DB::table('helpdesk_categories')
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', true);

        if ($this->hasEligibleCatchAllAgent()) {
            return $query->exists();
        }

        $covered = $this->categoryIdsCoveredByEligibleAgents();
        if ($covered === []) {
            return false;
        }

        return $query->whereIn('id', $covered)->exists();
    }
}
