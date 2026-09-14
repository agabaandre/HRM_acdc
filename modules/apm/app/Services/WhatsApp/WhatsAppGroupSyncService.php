<?php

namespace App\Services\WhatsApp;

use App\Models\Staff;

class WhatsAppGroupSyncService
{
    private const ACTIVE_STATUSES = ['Active', 'Due', 'Under Renewal'];

    public function __construct(
        private readonly WhatsAppConfig $config,
    ) {}

    /**
     * Compare staff phone numbers with WhatsApp group participants.
     *
     * @param  list<array<string, mixed>>  $groupMembers
     * @return array<string, mixed>
     */
    public function staffCoverage(array $groupMembers): array
    {
        $memberDigits = collect($groupMembers)->map(function (array $member): array {
            $jid = (string) ($member['id'] ?? $member['_id'] ?? $member['jid'] ?? '');
            $phone = $this->memberPhone($member);
            $username = (string) ($member['username'] ?? $member['name'] ?? '');

            return [
                'jid' => $jid,
                'phone' => $phone,
                'phone_tail' => $this->config->phoneTail($phone),
                'username' => $username,
                'is_lid' => str_ends_with($jid, '@lid'),
            ];
        })->filter(fn (array $row) => $row['phone'] !== '' || $row['username'] !== '' || $row['jid'] !== '');

        $memberTails = $memberDigits->pluck('phone_tail')->filter()->unique()->values();
        $membersByTail = $memberDigits
            ->filter(fn (array $m) => ($m['phone_tail'] ?? '') !== '')
            ->groupBy('phone_tail');

        $staffRows = Staff::query()
            ->orderBy('fname')
            ->orderBy('lname')
            ->get(['staff_id', 'fname', 'lname', 'tel_1', 'whatsapp', 'division_name', 'job_name', 'status']);

        $activeStaff = $staffRows->filter(fn (Staff $s) => $this->isActiveContract($s));
        $inactiveStaff = $staffRows->reject(fn (Staff $s) => $this->isActiveContract($s));

        $inGroup = [];
        $missingFromGroup = [];
        $inactiveInGroup = [];
        $matchedTails = [];

        foreach ($activeStaff as $staff) {
            $phone = $this->staffPhone($staff);
            $tail = $this->config->phoneTail($phone);
            if ($tail === '') {
                $missingFromGroup[] = $this->staffRow($staff, $phone, false, 'no_phone');

                continue;
            }

            $matched = $memberTails->contains($tail);
            $row = $this->staffRow($staff, $phone, $matched, $matched ? 'in_group' : 'missing');
            if ($matched) {
                $inGroup[] = $row;
                $matchedTails[] = $tail;
            } else {
                $missingFromGroup[] = $row;
            }
        }

        foreach ($inactiveStaff as $staff) {
            $phone = $this->staffPhone($staff);
            $tail = $this->config->phoneTail($phone);
            if ($tail === '' || ! $memberTails->contains($tail)) {
                continue;
            }

            $participant = $membersByTail->get($tail)?->first();
            $row = $this->staffRow($staff, $phone, true, 'inactive_contract');
            $row['member_jid'] = (string) ($participant['jid'] ?? ($phone !== '' ? $phone.'@s.whatsapp.net' : ''));
            $row['can_remove'] = $row['member_jid'] !== '';
            $inactiveInGroup[] = $row;
            $matchedTails[] = $tail;
        }

        $matchedTails = array_values(array_unique($matchedTails));

        $unknownInGroup = $memberDigits->filter(function (array $member) use ($matchedTails) {
            $tail = $member['phone_tail'];
            if ($tail === '') {
                return true;
            }

            return ! in_array($tail, $matchedTails, true);
        })->values()->all();

        return [
            'summary' => [
                'staff_active' => $activeStaff->count(),
                'staff_with_phone' => $activeStaff->count() - collect($missingFromGroup)->where('reason', 'no_phone')->count(),
                'staff_in_group' => count($inGroup),
                'staff_missing_from_group' => collect($missingFromGroup)->where('reason', 'missing')->count(),
                'staff_without_phone' => collect($missingFromGroup)->where('reason', 'no_phone')->count(),
                'inactive_in_group' => count($inactiveInGroup),
                'group_participants' => count($groupMembers),
                'unknown_in_group' => count($unknownInGroup),
            ],
            'in_group' => $inGroup,
            'inactive_in_group' => $inactiveInGroup,
            'missing_from_group' => array_values(array_filter($missingFromGroup, fn (array $r) => $r['reason'] === 'missing')),
            'without_phone' => array_values(array_filter($missingFromGroup, fn (array $r) => $r['reason'] === 'no_phone')),
            'unknown_in_group' => $unknownInGroup,
            'participants' => $memberDigits->values()->all(),
        ];
    }

    public function isActiveContract(Staff $staff): bool
    {
        return in_array((string) $staff->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function memberPhone(array $member): string
    {
        $explicit = $this->config->normalizePhone((string) ($member['phone'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $jid = (string) ($member['id'] ?? $member['_id'] ?? $member['jid'] ?? '');
        if ($jid === '' || str_ends_with($jid, '@lid')) {
            return '';
        }

        return $this->config->normalizePhone(explode('@', $jid)[0] ?? '');
    }

    private function staffPhone(Staff $staff): string
    {
        $whatsapp = $this->config->normalizePhone($staff->whatsapp);
        if ($whatsapp !== '') {
            return $whatsapp;
        }

        return $this->config->normalizePhone($staff->tel_1);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffRow(Staff $staff, string $phone, bool $inGroup, string $reason): array
    {
        return [
            'staff_id' => (int) $staff->staff_id,
            'name' => trim($staff->fname.' '.$staff->lname),
            'division' => (string) ($staff->division_name ?? ''),
            'job_title' => (string) ($staff->job_name ?? ''),
            'phone' => $phone,
            'status' => (string) ($staff->status ?? ''),
            'contract_active' => $this->isActiveContract($staff),
            'in_group' => $inGroup,
            'reason' => $reason,
        ];
    }
}
