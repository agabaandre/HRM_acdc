<?php

namespace App\Http\Middleware;

use App\Services\CbpModulesNavService;
use App\Support\AppBasePath;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Inertia page URLs must include the mount path (/staff/finance) or the
     * browser resolves /dashboard as http://localhost/dashboard.
     */
    public function urlResolver(): ?Closure
    {
        $prefix = AppBasePath::path();
        if ($prefix === '') {
            return null;
        }

        return static function (Request $request) use ($prefix): string {
            $path = '/'.ltrim($request->path(), '/');
            $url = $prefix.($path === '/' ? '' : $path);
            $query = $request->getQueryString();

            return $query ? $url.'?'.$query : $url;
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = session('user', []);
        $staffWebBaseUrl = CbpModulesNavService::staffWebBaseUrl();
        $assetsBase = config('finance.assets_base_url');
        if ($assetsBase === null || $assetsBase === '') {
            $assetsBase = rtrim($staffWebBaseUrl, '/').'/apm';
        }

        return [
            ...parent::share($request),
            'appUrl' => rtrim((string) config('app.url', ''), '/'),
            'auth' => [
                'user' => is_array($user) ? $user : null,
                'permissions' => array_map('strval', (array) session('permissions', [])),
            ],
            'staffWebBaseUrl' => $staffWebBaseUrl,
            'assetsBaseUrl' => $assetsBase,
            'cbpModulesNav' => CbpModulesNavService::headerNav(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'routes' => [
                'dashboard' => AppBasePath::url('/dashboard'),
                'my-advances' => AppBasePath::url('/my-advances'),
                'my-missions' => AppBasePath::url('/my-missions'),
                'budgets' => AppBasePath::url('/budgets'),
                'logout' => AppBasePath::url('/logout'),
            ],
        ];
    }
}
