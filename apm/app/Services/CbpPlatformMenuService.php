<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reads Staff portal table cbp_modules (STAFF_DB_* connection) for APM primary-nav links.
 */
class CbpPlatformMenuService
{
    /**
     * @return list<array{url:string,title:string,description:string,icon:string}>
     */
    public static function primaryNavItems(): array
    {
        $dbName = config('database.connections.staff_app.database');
        if (empty($dbName)) {
            return [];
        }

        try {
            $rows = DB::connection('staff_app')
                ->table('cbp_modules')
                ->where('is_enabled', 1)
                ->where('show_in_apm_menu', 1)
                ->orderBy('sort_order')
                ->orderBy('system_name')
                ->get();
        } catch (\Throwable $e) {
            Log::debug('CbpPlatformMenuService: staff_app cbp_modules unreadable: '.$e->getMessage());

            return [];
        }

        $user = session('user', []);
        $roleId = (int) ($user['role'] ?? 0);
        $permissions = array_map('strval', session('permissions', []));

        $sessionForToken = $user;
        if (! isset($sessionForToken['base_url'])) {
            $sessionForToken['base_url'] = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/').'/';
        }

        $out = [];
        foreach ($rows as $row) {
            $perm = (string) $row->permission_code;
            if (! in_array($perm, $permissions, true)) {
                continue;
            }
            if ((int) $row->is_production === 0 && $roleId !== 10) {
                continue;
            }
            if (($row->module_key ?? '') === 'approvals_management') {
                continue;
            }

            $url = self::resolveUrl($row, $sessionForToken);
            if ($url === null || $url === '') {
                continue;
            }

            $icon = (string) $row->icon_class;
            if ($icon !== '' && strncmp($icon, 'fa-', 3) === 0) {
                $icon = 'fas '.$icon;
            }

            $out[] = [
                'url' => $url,
                'title' => (string) $row->system_name,
                'description' => (string) ($row->description ?? ''),
                'icon' => $icon !== '' ? $icon : 'fas fa-external-link-alt',
            ];
        }

        return $out;
    }

    /**
     * @param  object  $row  cbp_modules row
     * @param  array<string,mixed>  $sessionForToken
     */
    public static function resolveUrl(object $row, array $sessionForToken): ?string
    {
        $resolver = $row->target_resolver ?? 'codeigniter';

        if ($resolver === 'codeigniter') {
            $path = $row->base_url ?? '';
            $altRole = isset($row->alternate_for_role_id) ? (int) $row->alternate_for_role_id : 0;
            if ($altRole > 0 && (int) ($sessionForToken['role'] ?? 0) === $altRole && ! empty($row->alternate_base_url)) {
                $path = $row->alternate_base_url;
            }
            $path = trim((string) $path, '/');
            if ($path === '') {
                return null;
            }
            $base = self::staffWebBaseUrl();

            return $base.'/'.$path;
        }

        if ($resolver === 'staff_app_token') {
            $base = rtrim(self::staffWebBaseUrl(), '/');
            $seg = trim((string) ($row->base_url ?? ''), '/');
            if ($seg === '') {
                return null;
            }
            $url = $base.'/'.$seg;
            if (! empty($row->uses_staff_portal_token)) {
                $url = self::appendStaffPortalToken($url, $sessionForToken);
            }

            return $url;
        }

        if ($resolver === 'finance_host') {
            $host = request()->getHost();
            $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
            $url = '';
            if ($isLocal) {
                $devBase = trim((string) ($row->base_url_development ?? ''), '/');
                if ($devBase === '') {
                    $devBase = 'http://localhost:3002';
                }
                if (! preg_match('#^https?://#i', $devBase)) {
                    $devBase = 'http://'.$devBase;
                }
                $url = rtrim($devBase, '/');
            } else {
                $scheme = request()->getScheme();
                $prod = trim((string) ($row->base_url_production ?? ''), '/');
                if ($prod !== '') {
                    if (preg_match('#^https?://#i', $prod)) {
                        $url = rtrim($prod, '/');
                    } else {
                        $url = $scheme.'://'.$host.'/'.$prod;
                    }
                } else {
                    $url = $scheme.'://'.$host.'/finance';
                }
            }
            if (! empty($row->uses_staff_portal_token)) {
                $url = self::appendStaffPortalToken($url, $sessionForToken);
            }

            return $url;
        }

        if ($resolver === 'external_microservice') {
            return self::resolveExternalMicroservice($row, $sessionForToken);
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $sessionForToken
     */
    private static function appendStaffPortalToken(string $url, array $sessionForToken): string
    {
        $token = urlencode(base64_encode(json_encode($sessionForToken)));
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'token='.$token;
    }

    /**
     * @param  object  $row
     * @param  array<string,mixed>  $sessionForToken
     */
    private static function resolveExternalMicroservice(object $row, array $sessionForToken): ?string
    {
        $host = request()->getHost();
        $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
        $url = '';
        if ($isLocal) {
            $url = trim((string) ($row->base_url_development ?? ''));
            if ($url === '') {
                $url = trim((string) ($row->base_url ?? ''));
            }
        } else {
            $url = trim((string) ($row->base_url_production ?? ''));
            if ($url === '') {
                $url = trim((string) ($row->base_url ?? ''));
            }
        }
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim(preg_replace('#^[\\/]+#', '', $url), '/');
        }
        $url = rtrim($url, '/');
        if ($url === '' || preg_match('#^https?:/?$#i', $url)) {
            return null;
        }
        if (! empty($row->uses_staff_portal_token)) {
            $url = self::appendStaffPortalToken($url, $sessionForToken);
        }

        return $url;
    }

    private static function staffWebBaseUrl(): string
    {
        $u = rtrim((string) session('user.base_url', env('BASE_URL', 'http://localhost/staff/')), '/');

        return rtrim(str_replace('/apm', '', $u), '/');
    }
}
