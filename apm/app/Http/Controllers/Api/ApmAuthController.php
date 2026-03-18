<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApmApiUser;
use App\Models\Approver;
use App\Models\Division;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApmAuthController extends Controller
{
    /**
     * Login: email + password. Returns JWT access token (use refresh endpoint to get new one).
     * Uses apm_api_users table only. Passwords are Argon2i hashes (same as CodeIgniter app).
     * We use password_verify() so both Argon2i and bcrypt hashes are supported.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = ApmApiUser::where('email', $request->email)->first();

        if (!$user || !$user->is_active || !$user->password || !password_verify($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect or the API user is inactive.'],
            ]);
        }

        $user->update(['last_used_at' => now()]);

        $token = auth('api')->login($user);

        return $this->respondWithTokenAndUser($token, $user);
    }

    /**
     * Refresh the JWT. Returns new access token and current user data.
     */
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();
        $user = auth('api')->user();
        return $this->respondWithTokenAndUser($token, $user);
    }

    /**
     * Microsoft SSO login for mobile app.
     * Accepts either:
     *   - access_token: Microsoft Graph access token (mobile gets from MSAL); we call Graph /me to get email and find APM user.
     *   - code + redirect_uri: Authorization code from Microsoft redirect; we exchange for token then same as above.
     * Returns same payload as email/password login (JWT + user + divisions).
     * Uses same Azure app as CodeIgniter auth (CLIENT_ID, TENANT_ID, etc.).
     */
    public function microsoftLogin(Request $request): JsonResponse
    {
        $accessToken = null;

        if ($request->filled('access_token')) {
            $accessToken = trim($request->input('access_token'));
        } elseif ($request->filled('code') && $request->filled('redirect_uri')) {
            $accessToken = $this->exchangeMicrosoftCodeForToken(
                $request->input('code'),
                $request->input('redirect_uri')
            );
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired authorization code.',
                ], 400);
            }
        } else {
            throw ValidationException::withMessages([
                'access_token' => ['Either access_token or (code and redirect_uri) is required.'],
            ]);
        }

        $graphUser = $this->getMicrosoftGraphUser($accessToken);
        if (!$graphUser || empty($graphUser['mail'] ?? $graphUser['userPrincipalName'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not get user identity from Microsoft.',
            ], 400);
        }

        $email = $graphUser['mail'] ?? $graphUser['userPrincipalName'];
        $email = is_string($email) ? trim($email) : '';

        $user = ApmApiUser::where('email', $email)->first();
        if (!$user) {
            $staff = Staff::where('work_email', $email)->first();
            if ($staff) {
                $user = ApmApiUser::where('auth_staff_id', $staff->staff_id)->first();
            }
        }

        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Staff profile missing or inactive. Contact HR.',
            ], 403);
        }

        $user->update(['last_used_at' => now()]);
        $token = auth('api')->login($user);

        return $this->respondWithTokenAndUser($token, $user);
    }

    /**
     * Web OAuth callback: Microsoft redirects the browser here with ?code=... (and optionally ?state=...).
     * Exchanges the code for a token using the configured redirect_uri, finds the APM user, sets web session, redirects to /home.
     * Configure MICROSOFT_REDIRECT_URI to the exact callback URL (e.g. https://cbp.africacdc.org/demo_staff/apm/oauth/callback).
     */
    public function microsoftCallback(Request $request): RedirectResponse
    {
        $baseUrl = rtrim(config('staff_api.base_url', env('BASE_URL', 'http://localhost/staff/')), '/');
        $loginUrl = $baseUrl . '/auth/login';

        if ($request->filled('error')) {
            Log::warning('Microsoft OAuth callback error', [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);
            $message = $request->input('error_description', $request->input('error', 'Login was cancelled or failed.'));
            return redirect($loginUrl . '?error=' . urlencode($message));
        }

        $code = $request->query('code');
        if (empty($code)) {
            return redirect($loginUrl . '?error=' . urlencode('No authorization code received.'));
        }

        $redirectUri = config('services.microsoft.redirect_uri');
        if (empty($redirectUri)) {
            $redirectUri = $request->url();
        }
        $accessToken = $this->exchangeMicrosoftCodeForToken($code, $redirectUri);
        if (!$accessToken) {
            Log::warning('Microsoft OAuth code exchange failed');
            return redirect($loginUrl . '?error=' . urlencode('Invalid or expired authorization code.'));
        }

        $graphUser = $this->getMicrosoftGraphUser($accessToken);
        if (!$graphUser || empty($graphUser['mail'] ?? $graphUser['userPrincipalName'] ?? null)) {
            Log::warning('Microsoft OAuth: could not get Graph user');
            return redirect($loginUrl . '?error=' . urlencode('Could not get user identity from Microsoft.'));
        }

        $email = $graphUser['mail'] ?? $graphUser['userPrincipalName'];
        $email = is_string($email) ? trim($email) : '';

        $user = ApmApiUser::where('email', $email)->first();
        if (!$user) {
            $staff = Staff::where('work_email', $email)->first();
            if ($staff) {
                $user = ApmApiUser::where('auth_staff_id', $staff->staff_id)->first();
            }
        }

        if (!$user || !$user->is_active) {
            return redirect($loginUrl . '?error=' . urlencode('Staff profile missing or inactive. Contact HR.'));
        }

        $user->update(['last_used_at' => now()]);

        $sessionUser = $user->toSessionArray();
        $sessionUser['user_id'] = $user->auth_staff_id;

        session([
            'user' => $sessionUser,
            'base_url' => $sessionUser['base_url'] ?? config('app.url'),
            'permissions' => $sessionUser['permissions'] ?? [],
            'last_activity' => now(),
        ]);
        session()->save();

        return redirect('/home');
    }

    private function exchangeMicrosoftCodeForToken(string $code, string $redirectUri): ?string
    {
        $tenantId = config('services.microsoft.tenant_id');
        $clientId = config('services.microsoft.client_id');
        $clientSecret = config('services.microsoft.client_secret');
        if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $response = Http::asForm()->post($url, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            return null;
        }
        $data = $response->json();
        return $data['access_token'] ?? null;
    }

    private function getMicrosoftGraphUser(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me');

        if (!$response->successful()) {
            return null;
        }
        return $response->json();
    }

    /**
     * Logout. Can be called with or without Bearer token.
     * If token is present, invalidates it (blacklist). Always returns 200 so clients can clear token safely.
     */
    public function logout(): JsonResponse
    {
        try {
            if (auth('api')->check()) {
                auth('api')->logout();
            }
        } catch (\Throwable $e) {
            // If blacklist/cache fails, still return success so client can discard token
            report($e);
        }
        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }

    /**
     * Get current authenticated API user and linked staff info.
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userDataForApp($user),
                'divisions' => $this->getDivisionsForUser($user),
            ],
        ]);
    }

    /**
     * User payload for app (login, refresh, me). Uses Argon/auth from CodeIgniter user table.
     * Includes role flags: is_division_head, is_admin_assistant, is_director, is_finance_officer.
     */
    private function userDataForApp($user): array
    {
        $user->loadMissing('staff');
        $session = $user->toSessionArray();
        $staffId = $user->auth_staff_id;

        $isDivisionHead = $staffId ? Division::where('division_head', $staffId)->exists() : false;
        $isAdminAssistant = $staffId && (
            Division::where('admin_assistant', $staffId)->exists() ||
            Approver::where('admin_assistant', $staffId)->exists()
        );
        $isDirector = $staffId ? Division::where('director_id', $staffId)->exists() : false;
        $isFinanceOfficer = $staffId ? Division::where('finance_officer', $staffId)->exists() : false;

        $staff = $user->staff;
        $associatedDivisions = $staff && isset($staff->associated_divisions)
            ? (is_array($staff->associated_divisions) ? $staff->associated_divisions : [])
            : [];

        $data = [
            'user_id' => $user->user_id,
            'auth_staff_id' => $user->auth_staff_id,
            'email' => $user->email,
            'name' => $session['name'],
            'division_id' => $session['division_id'],
            'associated_divisions' => $associatedDivisions,
            'role' => $user->role,
            'status' => (bool) $user->status,
            'is_division_head' => $isDivisionHead,
            'is_admin_assistant' => $isAdminAssistant,
            'is_director' => $isDirector,
            'is_finance_officer' => $isFinanceOfficer,
            'job' => $this->staffJobForApp($staff),
            'supervisors' => $this->staffSupervisorsForApp($staff),
        ];

        $staffImageBase64 = $this->getStaffImageBase64($user);
        if ($staffImageBase64 !== null) {
            $data['staff_image_base64'] = $staffImageBase64;
            $data['photo_data'] = $staffImageBase64; // compatibility with session/helper expecting photo_data
        }

        return $data;
    }

    /**
     * Job fields from APM staff row (login / me response).
     */
    private function staffJobForApp(?Staff $staff): ?array
    {
        if (!$staff) {
            return null;
        }
        return [
            'job_name' => $staff->job_name,
            'title' => $staff->title,
            'grade' => $staff->grade ?? null,
        ];
    }

    /**
     * Supervisors from staff.supervisor_id (APM staff table). Ordered: primary supervisor first.
     */
    private function staffSupervisorsForApp(?Staff $staff): array
    {
        if (!$staff) {
            return [];
        }
        $id = (int) ($staff->supervisor_id ?? 0);
        if ($id <= 0) {
            return [];
        }
        $sup = Staff::query()->where('staff_id', $id)->first();
        if (!$sup) {
            return [];
        }
        return [[
            'staff_id' => $sup->staff_id,
            'name' => $sup->name,
            'email' => $sup->work_email,
            'job_name' => $sup->job_name,
            'title' => $sup->title,
        ]];
    }

    /**
     * Staff image as base64. Always reads current row from APM staff table (same as web auth: staff.photo).
     * Web (CodeIgniter): session user from staff table, image at base_url/uploads/staff/{photo}.
     * APM staff UI may use profile_photo on staff (storage/staff-photos/…) when that column exists.
     */
    private function getStaffImageBase64($user): ?string
    {
        $staffId = (int) ($user->auth_staff_id ?? 0);
        if ($staffId <= 0) {
            $fallback = trim((string) ($user->photo ?? ''));
            return $fallback !== '' ? $this->encodeStaffUploadsPhotoAsBase64(basename($fallback)) : null;
        }

        $staffRow = Staff::query()->where('staff_id', $staffId)->first();
        if (!$staffRow) {
            $fallback = trim((string) ($user->photo ?? ''));
            return $fallback !== '' ? $this->encodeStaffUploadsPhotoAsBase64(basename($fallback)) : null;
        }

        // APM Staff create/edit: profile_photo path on public disk (e.g. staff-photos/xxx.jpg)
        if (Schema::hasColumn('staff', 'profile_photo')) {
            $profilePath = trim((string) ($staffRow->getAttributes()['profile_photo'] ?? $staffRow->profile_photo ?? ''));
            if ($profilePath !== '' && !str_contains($profilePath, '..')) {
                $disk = Storage::disk('public');
                if ($disk->exists($profilePath)) {
                    $content = $disk->get($profilePath);
                    if ($content !== false && $content !== '') {
                        return base64_encode($content);
                    }
                }
            }
        }

        // Same as web auth: staff.photo filename under uploads/staff/
        $photo = trim((string) ($staffRow->photo ?? ''));
        if ($photo === '') {
            return null;
        }

        return $this->encodeStaffUploadsPhotoAsBase64(basename($photo));
    }

    /**
     * Encode uploads/staff/{filename} to base64: disk paths then HTTP (main staff BASE_URL).
     */
    private function encodeStaffUploadsPhotoAsBase64(string $filename): ?string
    {
        if ($filename === '' || str_contains($filename, '..')) {
            return null;
        }
        $path = $this->resolveStaffPhotoPath($filename);
        if ($path !== null) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                return base64_encode($content);
            }
        }
        $content = $this->fetchStaffPhotoViaUrl($filename);
        if ($content !== null) {
            return base64_encode($content);
        }
        if (config('app.debug')) {
            Log::debug('Staff photo not found (disk or URL)', ['filename' => $filename]);
        }
        return null;
    }

    /**
     * Try multiple possible roots for uploads/staff (public, parent dirs for staff/apm, storage, config).
     */
    private function resolveStaffPhotoPath(string $filename): ?string
    {
        $candidates = [
            public_path('uploads/staff/' . $filename),
            base_path('uploads/staff/' . $filename),
            base_path('../uploads/staff/' . $filename),
            base_path('../../uploads/staff/' . $filename),
            storage_path('app/public/uploads/staff/' . $filename),
        ];
        $customRoot = config('services.staff_api.uploads_path') ?? env('STAFF_UPLOADS_PATH');
        if (!empty($customRoot) && is_string($customRoot)) {
            $candidates[] = rtrim($customRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . $filename;
        }
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Fetch staff photo via the same URL(s) the web app uses (base_url + /uploads/staff/ + filename).
     * The web often uses the main staff app BASE_URL (e.g. .../demo_staff/) for uploads, not the APM path
     * (.../demo_staff/apm/), so we try multiple base URLs.
     */
    private function fetchStaffPhotoViaUrl(string $filename): ?string
    {
        $candidateBaseUrls = $this->getStaffPhotoBaseUrls();
        foreach ($candidateBaseUrls as $baseUrl) {
            if ($baseUrl === '') {
                continue;
            }
            $url = rtrim($baseUrl, '/') . '/uploads/staff/' . $filename;
            $content = $this->fetchUrlAsImage($url);
            if ($content !== null) {
                return $content;
            }
        }
        return null;
    }

    /**
     * Base URLs to try for staff uploads (web may use main staff app URL, not APM).
     */
    private function getStaffPhotoBaseUrls(): array
    {
        $appUrl = rtrim(config('app.url'), '/');
        $staffBaseUrl = rtrim(config('services.staff_api.base_url', ''), '/');
        $urls = array_filter([$appUrl, $staffBaseUrl], fn ($u) => $u !== '');
        // If app is at .../demo_staff/apm, also try .../demo_staff (uploads often live under main app)
        if ($appUrl !== '' && str_ends_with($appUrl, '/apm')) {
            $urls[] = preg_replace('#/apm$#', '', $appUrl);
        }
        return array_values(array_unique($urls));
    }

    /**
     * GET a URL and return body if response looks like an image; null otherwise.
     */
    private function fetchUrlAsImage(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) {
                return null;
            }
            $body = $response->body();
            if ($body === '' || strlen($body) < 50) {
                return null;
            }
            $contentType = $response->header('Content-Type') ?? '';
            if (!str_contains($contentType, 'image/') && !str_contains($contentType, 'octet-stream')) {
                return null;
            }
            return $body;
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::debug('Staff photo URL fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
            return null;
        }
    }

    /**
     * Division detail shape (for API response).
     */
    private function divisionToArray($row): array
    {
        return [
            'id' => $row->id,
            'division_name' => $row->division_name,
            'division_short_name' => $row->division_short_name ?? null,
            'division_head' => $row->division_head ?? null,
            'focal_person' => $row->focal_person ?? null,
            'admin_assistant' => $row->admin_assistant ?? null,
            'finance_officer' => $row->finance_officer ?? null,
            'director_id' => $row->director_id ?? null,
            'directorate_id' => $row->directorate_id ?? null,
            'category' => $row->category ?? null,
        ];
    }

    /**
     * Divisions for the logged-in user only: primary division + associated divisions.
     * If no associated divisions, returns only the primary division (one object).
     */
    private function getDivisionsForUser($user): array
    {
        $user->loadMissing('staff');
        $session = $user->toSessionArray();
        $primaryId = $session['division_id'] ?? null;
        $staff = $user->staff;
        $associated = $staff && isset($staff->associated_divisions)
            ? (is_array($staff->associated_divisions) ? $staff->associated_divisions : [])
            : [];

        $divisionIds = array_values(array_filter(array_unique(array_merge(
            $primaryId ? [(int) $primaryId] : [],
            array_map('intval', $associated)
        ))));

        if (empty($divisionIds)) {
            return [];
        }

        return Division::whereIn('id', $divisionIds)
            ->orderBy('division_name')
            ->get([
                'id', 'division_name', 'division_short_name',
                'division_head', 'focal_person', 'admin_assistant', 'finance_officer',
                'director_id', 'directorate_id', 'category',
            ])
            ->map(fn ($row) => $this->divisionToArray($row))
            ->values()
            ->all();
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => (int) config('jwt.ttl') * 60, // seconds
            ],
        ]);
    }

    private function respondWithTokenAndUser(string $token, $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => (int) config('jwt.ttl') * 60,
                'user' => $this->userDataForApp($user),
                'divisions' => $this->getDivisionsForUser($user),
            ],
        ]);
    }
}
