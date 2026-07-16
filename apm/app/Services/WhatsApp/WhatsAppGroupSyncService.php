<?php

namespace App\Services\WhatsApp;

use App\Models\Staff;
use Illuminate\Support\Collection;

class WhatsAppGroupSyncService
{
    public function __construct(
        private readonly WhatsAppConfig $config,
    ) {}

    /**
     * Compare active staff phone numbers with WhatsApp group participants.
     *
     * @param  list<array<string, mixed>>  $groupMembers
     * @return array<string, mixed>
     */
    public function staffCoverage(array $groupMembers): array
    {
        $memberDigits = collect($groupMembers)->map(function (array $member): array {
            $jid = (string) ($member['id'] ?? $member['_id'] ?? $member['jid'] ?? '');
            $phone = $this->config->normalizePhone($jid !== '' ? explode('@', $jid)[0] : '');
            $username = (string) ($member['username'] ?? $member['name'] ?? '');

            return [
                'jid' => $jid,
                'phone' => $phone,
                'phone_tail' => $this->config->phoneTail($phone),
                'username' => $username,
            ];
        })->filter(fn (array $row) => $row['phone'] !== '' || $row['username'] !== '');

        $memberTails = $memberDigits->pluck('phone_tail')->filter()->unique()->values();

        $staffRows = Staff::query()
            ->active()
            ->orderBy('fname')
            ->orderBy('lname')
            ->get(['staff_id', 'fname', 'lname', 'tel_1', 'whatsapp', 'division_name', 'job_name']);

        $inGroup = [];
        $missingFromGroup = [];
        $staffTailsMatched = [];

        foreach ($staffRows as $staff) {
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
                $staffTailsMatched[] = $tail;
            } else {
                $missingFromGroup[] = $row;
            }
        }

        $unknownInGroup = $memberDigits->filter(function (array $member) use ($staffTailsMatched) {
            $tail = $member['phone_tail'];
            if ($tail === '') {
                return true;
            }

            return ! in_array($tail, $staffTailsMatched, true);
        })->values()->all();

        return [
            'summary' => [
                'staff_active' => $staffRows->count(),
                'staff_with_phone' => $staffRows->count() - collect($missingFromGroup)->where('reason', 'no_phone')->count(),
                'staff_in_group' => count($inGroup),
                'staff_missing_from_group' => collect($missingFromGroup)->where('reason', 'missing')->count(),
                'staff_without_phone' => collect($missingFromGroup)->where('reason', 'no_phone')->count(),
                'group_participants' => count($groupMembers),
                'unknown_in_group' => count($unknownInGroup),
            ],
            'in_group' => $inGroup,
            'missing_from_group' => array_values(array_filter($missingFromGroup, fn (array $r) => $r['reason'] === 'missing')),
            'without_phone' => array_values(array_filter($missingFromGroup, fn (array $r) => $r['reason'] === 'no_phone')),
            'unknown_in_group' => $unknownInGroup,
        ];
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
            'in_group' => $inGroup,
            'reason' => $reason,
        ];
    }
}
