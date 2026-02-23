<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApmApiUser;
use App\Models\Approver;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return [
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
        ];
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
