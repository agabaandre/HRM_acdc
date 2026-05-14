<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\ReferenceDataController;

/**
 * Normalises Staff Share API payloads (same shapes as {@see ReferenceDataController}).
 */
final class StaffShareNormalizer
{
    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string,short_name:?string,directorate_id:?int}
     */
    public static function division(array $r): array
    {
        $id = (int) ($r['division_id'] ?? $r['id'] ?? 0);

        return [
            'id' => $id,
            'name' => (string) ($r['division_name'] ?? $r['name'] ?? ''),
            'short_name' => isset($r['division_short_name']) ? (string) $r['division_short_name'] : (isset($r['short_name']) ? (string) $r['short_name'] : null),
            'directorate_id' => isset($r['directorate_id']) ? (int) $r['directorate_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string}
     */
    public static function directorate(array $r): array
    {
        $id = (int) ($r['directorate_id'] ?? $r['id'] ?? 0);

        return [
            'id' => $id,
            'name' => (string) ($r['name'] ?? $r['directorate_name'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string,fname:string,lname:string,title:?string,work_email:?string,division_id:?int,directorate_id:?int,duty_station_name:?string}
     */
    public static function staff(array $r): array
    {
        $id = (int) ($r['staff_id'] ?? $r['id'] ?? 0);
        $fname = trim((string) ($r['fname'] ?? ''));
        $lname = trim((string) ($r['lname'] ?? ''));
        $name = trim($fname.' '.$lname);
        if ($name === '') {
            $name = 'Staff '.$id;
        }
        $duty = $r['duty_station_name'] ?? $r['duty_station'] ?? null;
        $dutyStr = $duty !== null && $duty !== '' ? trim((string) $duty) : null;

        return [
            'id' => $id,
            'name' => $name,
            'fname' => $fname,
            'lname' => $lname,
            'title' => isset($r['title']) ? (string) $r['title'] : null,
            'work_email' => isset($r['work_email']) ? (string) $r['work_email'] : null,
            'division_id' => isset($r['division_id']) ? (int) $r['division_id'] : null,
            'directorate_id' => null,
            'duty_station_name' => $dutyStr,
        ];
    }
}
