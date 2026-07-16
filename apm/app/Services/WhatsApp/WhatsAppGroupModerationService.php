<?php

namespace App\Services\WhatsApp;

use App\Models\Staff;
use App\Models\WhatsAppGroup;
use App\Models\WhatsAppGroupMember;

class WhatsAppGroupModerationService
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppGroupSyncService $sync,
    ) {}

    public function canModerateGroup(string $groupJid): bool
    {
        if (whatsapp_config_can_access()) {
            return true;
        }

        $staffId = (int) user_session('staff_id');
        if ($staffId <= 0) {
            return false;
        }

        return $this->staffIsGroupAdmin($groupJid, $staffId);
    }

    /**
     * Whether the linked WhatsApp bot account is an admin of this group.
     * UI "can moderate" is separate — adds/removes still require bot admin in WhatsApp.
     */
    public function botIsGroupAdmin(string $groupJid): bool
    {
        $botPhone = $this->config->botNumber();
        $botTail = $this->config->phoneTail($botPhone);
        if ($botTail === '') {
            return false;
        }

        $admins = WhatsAppGroupMember::query()
            ->where('group_jid', $groupJid)
            ->where('is_admin', true)
            ->get(['member_jid', 'phone']);

        foreach ($admins as $admin) {
            $adminPhone = $this->config->normalizePhone((string) ($admin->phone ?: explode('@', (string) $admin->member_jid)[0]));
            if ($this->config->phoneTail($adminPhone) === $botTail) {
                return true;
            }
        }

        return false;
    }

    public function canRemoveMember(string $groupJid, string $memberJid): bool
    {
        if (! $this->canModerateGroup($groupJid)) {
            return false;
        }

        $members = WhatsAppGroupMember::query()
            ->where('group_jid', $groupJid)
            ->get()
            ->map(fn (WhatsAppGroupMember $member) => [
                'id' => $member->member_jid,
                '_id' => $member->member_jid,
                'jid' => $member->member_jid,
                'phone' => $member->phone,
                'username' => $member->username,
                'name' => $member->username,
                'isAdmin' => $member->is_admin,
            ])
            ->all();

        $coverage = $this->sync->staffCoverage($members);

        $removable = collect($coverage['unknown_in_group'] ?? [])
            ->pluck('jid')
            ->merge(collect($coverage['inactive_in_group'] ?? [])->pluck('member_jid'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return in_array($memberJid, $removable, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inactiveMembersForRemoval(string $groupJid): array
    {
        $members = WhatsAppGroupMember::query()
            ->where('group_jid', $groupJid)
            ->get()
            ->map(fn (WhatsAppGroupMember $member) => [
                'id' => $member->member_jid,
                '_id' => $member->member_jid,
                'jid' => $member->member_jid,
                'phone' => $member->phone,
                'username' => $member->username,
                'name' => $member->username,
                'isAdmin' => $member->is_admin,
            ])
            ->all();

        $botTail = $this->config->phoneTail($this->config->botNumber());

        return collect($this->sync->staffCoverage($members)['inactive_in_group'] ?? [])
            ->filter(function (array $row) use ($botTail) {
                if (($row['member_jid'] ?? '') === '') {
                    return false;
                }
                if ($botTail === '') {
                    return true;
                }

                return $this->config->phoneTail((string) ($row['phone'] ?? '')) !== $botTail;
            })
            ->values()
            ->all();
    }

    private function staffIsGroupAdmin(string $groupJid, int $staffId): bool
    {
        $staff = Staff::query()->find($staffId);
        if ($staff === null) {
            return false;
        }

        $phone = $this->staffPhone($staff);
        $tail = $this->config->phoneTail($phone);
        if ($tail === '') {
            return false;
        }

        $admins = WhatsAppGroupMember::query()
            ->where('group_jid', $groupJid)
            ->where('is_admin', true)
            ->get(['member_jid']);

        foreach ($admins as $admin) {
            $adminPhone = $this->config->normalizePhone(explode('@', (string) $admin->member_jid)[0]);
            if ($this->config->phoneTail($adminPhone) === $tail) {
                return true;
            }
        }

        return false;
    }

    private function staffPhone(Staff $staff): string
    {
        $whatsapp = $this->config->normalizePhone($staff->whatsapp);
        if ($whatsapp !== '') {
            return $whatsapp;
        }

        return $this->config->normalizePhone($staff->tel_1);
    }
}
