<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ask crawlers not to index this application (robots.txt + meta still recommended).
 */
class DisableSearchEngineIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive, nosnippet',
            false
        );

        return $response;
    }
}
