<?php

namespace App\Support;

use App\Models\MemoTypeDefinition;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OtherMemoCc
{
    /**
     * @return array{cc_on_approval_enabled_snapshot: bool, cc_config: array<string, mixed>|null}
     */
    public static function attributesFromRequest(Request $request, MemoTypeDefinition $definition): array
    {
        $typeAllows = (bool) $definition->cc_on_approval_enabled;
        $config = null;
        if ($typeAllows && $request->boolean('cc_include')) {
            $config = self::buildConfigFromRequest($request);
        }

        return [
            'cc_on_approval_enabled_snapshot' => $typeAllows,
            'cc_config' => $config,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildConfigFromRequest(Request $request): array
    {
        $mode = strtolower(trim((string) $request->input('cc_mode', 'all')));
        if (! in_array($mode, ['all', 'specific'], true)) {
            $mode = 'all';
        }

        if ($mode === 'all') {
            return [
                'mode' => 'all',
                'all_staff_heading' => self::nullableString($request->input('cc_all_staff_heading')),
                'all_staff_label' => self::labelOrDefault($request->input('cc_all_staff_label')),
            ];
        }

        $rawIds = $request->input('cc_staff_ids', []);
        if (! is_array($rawIds)) {
            $rawIds = [];
        }
        $staffIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn (int $id) => $id > 0)));

        if ($staffIds === []) {
            throw ValidationException::withMessages([
                'cc_staff_ids' => 'Select at least one staff member to CC, or choose All staff.',
            ]);
        }

        $staffById = Staff::query()
            ->whereIn('staff_id', $staffIds)
            ->get(['staff_id', 'title', 'fname', 'lname', 'oname', 'job_name'])
            ->keyBy('staff_id');

        $lines = [];
        foreach ($staffIds as $sid) {
            $staff = $staffById->get($sid);
            if (! $staff) {
                continue;
            }
            $lines[] = [
                'staff_id' => $sid,
                'name' => self::staffDisplayName($staff),
                'role_label' => self::staffRoleLabel($staff),
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'cc_staff_ids' => 'Selected CC staff could not be resolved.',
            ]);
        }

        return [
            'mode' => 'specific',
            'staff' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public static function hasCcForPdf(?array $config): bool
    {
        if (! is_array($config) || ($config['mode'] ?? '') === '') {
            return false;
        }

        if (($config['mode'] ?? '') === 'all') {
            return true;
        }

        return is_array($config['staff'] ?? null) && count($config['staff']) > 0;
    }

    public static function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    public static function labelOrDefault(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : 'All Africa CDC Staff';
    }

    public static function staffDisplayName(Staff $staff): string
    {
        return trim(
            ($staff->title ? $staff->title.' ' : '')
            .$staff->fname.' '
            .$staff->lname
            .($staff->oname ? ' '.$staff->oname : '')
        );
    }

    public static function staffRoleLabel(Staff $staff): string
    {
        $job = html_entity_decode(trim((string) ($staff->job_name ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $job !== '' ? $job : 'Staff';
    }
}
