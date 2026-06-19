<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ApmPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait CachesApmPageResponses
{
    /**
     * @param  list<string>  $queryKeys
     * @param  array<string, mixed>  $extra
     */
    protected function apmCacheKeyFromRequest(Request $request, array $queryKeys, array $extra = []): array
    {
        return ApmPageCache::keyFromRequest($request, $queryKeys, $extra);
    }

    /**
     * @param  array<string, mixed>  $keyParts
     */
    protected function apmCachedJson(string $scope, Request $request, array $keyParts, callable $builder, ?int $ttl = null): JsonResponse
    {
        if ($request->boolean('export') || $request->boolean('nocache')) {
            return $builder();
        }

        $payload = ApmPageCache::remember($scope, $keyParts, function () use ($builder) {
            $response = $builder();

            return $response instanceof JsonResponse ? $response->getData(true) : $response;
        }, $ttl);

        return response()->json($payload);
    }

    /**
     * @param  list<string>  $queryKeys
     * @param  array<string, mixed>  $extra
     * @param  callable(): array<string, mixed>  $dataBuilder
     */
    protected function apmCachedView(
        string $scope,
        Request $request,
        string $view,
        array $queryKeys,
        callable $dataBuilder,
        array $extra = [],
        ?int $ttl = null
    ): View {
        if ($request->boolean('nocache')) {
            return view($view, $dataBuilder());
        }

        $keyParts = $this->apmCacheKeyFromRequest($request, $queryKeys, $extra);
        $data = ApmPageCache::remember($scope, $keyParts, $dataBuilder, $ttl);

        return view($view, is_array($data) ? $data : []);
    }
}
