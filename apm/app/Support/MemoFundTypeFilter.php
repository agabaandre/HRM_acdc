<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class MemoFundTypeFilter
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            '1' => 'Intramural',
            '2' => 'Extramural',
            '3' => 'External Source',
        ];
    }

    public static function selectedId(Request $request): string
    {
        return trim((string) ($request->get('fund_type_id') ?? ''));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function apply(Builder $query, Request $request, string $column = 'fund_type_id'): void
    {
        $selected = self::selectedId($request);
        $options = self::options();

        if ($selected !== '' && array_key_exists($selected, $options)) {
            $query->where($column, (int) $selected);
        }
    }
}
