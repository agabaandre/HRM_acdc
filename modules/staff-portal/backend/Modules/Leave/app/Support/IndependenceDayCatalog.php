<?php

namespace Modules\Leave\Support;

final class IndependenceDayCatalog
{
    /**
     * Month/day of national independence (or the commonly observed national day).
     * Ethiopia is omitted — Adwa / Patriots Day are ET public holidays.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function monthDayByIso2(): array
    {
        return [
            'DZ' => [7, 5],
            'AO' => [11, 11],
            'BJ' => [8, 1],
            'BW' => [9, 30],
            'BF' => [8, 5],
            'BI' => [7, 1],
            'CM' => [1, 1],
            'CV' => [7, 5],
            'CF' => [8, 13],
            'TD' => [8, 11],
            'KM' => [7, 6],
            'CG' => [8, 15],
            'CD' => [6, 30],
            'CI' => [8, 7],
            'DJ' => [6, 27],
            'EG' => [7, 23],
            'GQ' => [10, 12],
            'ER' => [5, 24],
            'SZ' => [9, 6],
            'GA' => [8, 17],
            'GM' => [2, 18],
            'GH' => [3, 6],
            'GN' => [10, 2],
            'GW' => [9, 24],
            'KE' => [12, 12],
            'LS' => [10, 4],
            'LR' => [7, 26],
            'LY' => [12, 24],
            'MG' => [6, 26],
            'MW' => [7, 6],
            'ML' => [9, 22],
            'MR' => [11, 28],
            'MU' => [3, 12],
            'MA' => [11, 18],
            'MZ' => [6, 25],
            'NA' => [3, 21],
            'NE' => [8, 3],
            'NG' => [10, 1],
            'RW' => [7, 1],
            'ST' => [7, 12],
            'SN' => [4, 4],
            'SC' => [6, 29],
            'SL' => [4, 27],
            'SO' => [7, 1],
            'ZA' => [4, 27],
            'SS' => [7, 9],
            'SD' => [1, 1],
            'TZ' => [12, 9],
            'TG' => [4, 27],
            'TN' => [3, 20],
            'UG' => [10, 9],
            'ZM' => [10, 24],
            'ZW' => [4, 18],
        ];
    }
}
