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
     * @return array{
     *     id:int,
     *     name:string,
     *     short_name:?string,
     *     directorate_id:?int,
     *     division_head:?int,
     *     head_oic_id:?int,
     *     head_oic_start_date:?string,
     *     head_oic_end_date:?string
     * }
     */
    public static function division(array $r): array
    {
        $id = (int) ($r['division_id'] ?? $r['id'] ?? 0);
        $head = isset($r['division_head']) && $r['division_head'] !== '' && (int) $r['division_head'] > 0
            ? (int) $r['division_head']
            : null;
        $oic = isset($r['head_oic_id']) && $r['head_oic_id'] !== '' && (int) $r['head_oic_id'] > 0
            ? (int) $r['head_oic_id']
            : null;

        return [
            'id' => $id,
            'name' => (string) ($r['division_name'] ?? $r['name'] ?? ''),
            'short_name' => isset($r['division_short_name']) ? (string) $r['division_short_name'] : (isset($r['short_name']) ? (string) $r['short_name'] : null),
            'directorate_id' => isset($r['directorate_id']) ? (int) $r['directorate_id'] : null,
            'division_head' => $head,
            'head_oic_id' => $oic,
            'head_oic_start_date' => self::nullableDateString($r['head_oic_start_date'] ?? null),
            'head_oic_end_date' => self::nullableDateString($r['head_oic_end_date'] ?? null),
        ];
    }

    private static function nullableDateString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string,director_id:?int,director:?array{id:int,name:string,fname:string,lname:string,title:?string}}
     */
    public static function directorate(array $r): array
    {
        $id = (int) ($r['directorate_id'] ?? $r['id'] ?? 0);
        $rawDir = $r['director_id'] ?? null;
        $directorId = ($rawDir !== null && $rawDir !== '' && (int) $rawDir > 0) ? (int) $rawDir : null;

        $director = null;
        if (isset($r['director']) && is_array($r['director'])) {
            $nested = $r['director'];
            $nid = (int) ($nested['id'] ?? $nested['staff_id'] ?? 0);
            if ($nid > 0) {
                $fname = trim((string) ($nested['fname'] ?? ''));
                $lname = trim((string) ($nested['lname'] ?? ''));
                $title = isset($nested['title']) ? trim((string) $nested['title']) : '';
                $titleOrNull = $title !== '' ? $title : null;
                $name = trim((string) ($nested['name'] ?? ''));
                if ($name === '') {
                    $name = trim(implode(' ', array_filter([$titleOrNull, $fname, $lname])));
                }
                if ($name === '') {
                    $name = 'Staff '.$nid;
                }
                $director = [
                    'id' => $nid,
                    'fname' => $fname,
                    'lname' => $lname,
                    'title' => $titleOrNull,
                    'name' => $name,
                ];
                if ($directorId === null) {
                    $directorId = $nid;
                }
            }
        } elseif ($directorId !== null) {
            $fname = trim((string) ($r['director_fname'] ?? ''));
            $lname = trim((string) ($r['director_lname'] ?? ''));
            $title = isset($r['director_title']) ? trim((string) $r['director_title']) : '';
            $titleOrNull = $title !== '' ? $title : null;
            $name = trim(implode(' ', array_filter([$titleOrNull, $fname, $lname])));
            if ($name === '') {
                $name = 'Staff '.$directorId;
            }
            $director = [
                'id' => $directorId,
                'fname' => $fname,
                'lname' => $lname,
                'title' => $titleOrNull,
                'name' => $name,
            ];
        }

        return [
            'id' => $id,
            'name' => (string) ($r['name'] ?? $r['directorate_name'] ?? ''),
            'director_id' => $directorId,
            'director' => $director,
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
        $duty = $r['duty_station_name'] ?? $r['duty_station'] ?? $r['region_name'] ?? $r['physical_location'] ?? null;
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
