<?php

namespace App\Http\Controllers;

use App\Models\ApmApiUser;
use App\Services\ApmImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApmApiUserController extends Controller
{
    public function __construct(
        private readonly ApmImpersonationService $impersonation
    ) {}

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
            'canImpersonate' => $this->impersonation->canImpersonate(),
            'isImpersonating' => $this->impersonation->isImpersonating(),
        ]);
    }

    public function impersonate(ApmApiUser $apmApiUser): RedirectResponse
    {
        try {
            $this->impersonation->impersonate($apmApiUser);
        } catch (HttpException $e) {
            return back()
                ->with('msg', $e->getMessage())
                ->with('type', 'danger');
        }

        return redirect()
            ->route('home')
            ->with('msg', 'You are now impersonating ' . ($apmApiUser->name ?? $apmApiUser->email ?? ('user #' . $apmApiUser->user_id)) . '. Use “Revert to Admin” to return.')
            ->with('type', 'success');
    }

    public function revertImpersonation(): RedirectResponse
    {
        if (! $this->impersonation->revert()) {
            return redirect()
                ->route('apm-api-users.index')
                ->with('msg', 'You are not impersonating any user.')
                ->with('type', 'warning');
        }

        return redirect()
            ->route('apm-api-users.index')
            ->with('msg', 'You have returned to your admin session.')
            ->with('type', 'success');
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
