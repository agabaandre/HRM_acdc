<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
            try {
                // Get the parent URL from config or environment, fallback to a default if not set
                $parentUrl = env('BASE_URL', 'http://localhost/staff');
                
                // Ensure the URL ends with /auth for login page
                $loginUrl = rtrim($parentUrl, '/') . '/auth';
                
                // If no session, redirect to parent application's login page
                return redirect($loginUrl);
            } catch (\Exception $e) {
                // If redirect fails, try a simple redirect to /auth
                Log::error('CheckSessionMiddleware redirect error: ' . $e->getMessage());
                return redirect('/auth');
            }
        }
        
        return $next($request);
    }
}
