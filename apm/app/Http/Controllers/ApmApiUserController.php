<?php

namespace App\Http\Controllers;

use App\Models\ApmApiUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApmApiUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = ApmApiUser::query()->with('staff')->orderBy('user_id');

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $q->where(function ($query) use ($search) {
                $query->where('email', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('user_id', $search)
                    ->orWhere('auth_staff_id', $search);
            });
        }

        $users = $q->paginate(25)->withQueryString();

        return view('apm-api-users.index', [
            'users' => $users,
            'staffDbLinked' => $this->staffAppDatabaseConfigured(),
        ]);
    }

    public function updateAllowEmailLogin(Request $request, ApmApiUser $apmApiUser): RedirectResponse
    {
        $validated = $request->validate([
            'allow_email_login' => 'required|in:0,1',
        ]);

        $allow = (bool) (int) $validated['allow_email_login'];

        $apmApiUser->allow_email_login = $allow;
        $apmApiUser->save();

        $this->syncAllowEmailLoginToStaffApp((int) $apmApiUser->user_id, $allow);

        return back()
            ->with('msg', 'Email/password login setting updated for user #' . $apmApiUser->user_id . '.')
            ->with('type', 'success');
    }

    private function staffAppDatabaseConfigured(): bool
    {
        $db = config('database.connections.staff_app.database');

        return is_string($db) && $db !== '';
    }

    private function syncAllowEmailLoginToStaffApp(int $userId, bool $allow): void
    {
        if (!$this->staffAppDatabaseConfigured()) {
            return;
        }
        try {
            DB::connection('staff_app')->table('user')->where('user_id', $userId)->update([
                'allow_email_login' => $allow ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
