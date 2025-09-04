<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user session exists
        if (!session()->has('user')) {
            // Get the parent URL from config or environment, fallback to a default if not set
            $parentUrl = env('PARENT_APP_URL', 'http://localhost');
            $staffRoute = '/staff';
            
            // If no session, redirect to parent application's staff route
            return redirect($parentUrl . $staffRoute);
        }
        
        return $next($request);
    }
}
