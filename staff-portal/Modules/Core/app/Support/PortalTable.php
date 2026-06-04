<?php

namespace Modules\Core\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PortalTable
{
    /**
     * Paginate a query that may return duplicate rows from joins — counts distinct column.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return LengthAwarePaginator<int, object>
     */
    public static function paginateDistinct(
        $query,
        string $distinctColumn,
        int $perPage = 20,
        ?int $page = null,
        string $pageName = 'page'
    ): LengthAwarePaginator {
        $perPage = min(100, max(10, $perPage));
        $page = $page ?? max(1, (int) request()->input($pageName, 1));

        $countQuery = clone $query;
        $total = $countQuery->count(DB::raw('DISTINCT '.$distinctColumn));

        $items = (clone $query)->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
                'query' => request()->except($pageName),
            ]
        );
    }

    /**
     * @return array{from: int, to: int, total: int}
     */
    public static function range(LengthAwarePaginator $paginator): array
    {
        $total = $paginator->total();
        if ($total === 0) {
            return ['from' => 0, 'to' => 0, 'total' => 0];
        }

        $from = (($paginator->currentPage() - 1) * $paginator->perPage()) + 1;
        $to = min($from + $paginator->count() - 1, $total);

        return ['from' => $from, 'to' => $to, 'total' => $total];
    }
}
