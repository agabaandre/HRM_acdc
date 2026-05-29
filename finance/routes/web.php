<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsureFinanceSession;
use App\Support\AppBasePath;
use App\Support\StaffSsoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    $rawToken = $request->query('token');

    if ($rawToken) {
        try {
            $json = StaffSsoToken::decode(is_string($rawToken) ? $rawToken : null);
            if (! $json) {
                throw new \RuntimeException('Invalid token format');
            }

            session([
                'user' => $json,
                'base_url' => $json['base_url'] ?? '',
                'permissions' => $json['permissions'] ?? [],
                'last_activity' => now(),
            ]);
            session()->save();

            return redirect()->to(AppBasePath::url('/dashboard'));
        } catch (\Throwable $e) {
            Log::error('Finance token processing error: '.$e->getMessage());
            $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

            return redirect($base.'/auth/login');
        }
    }

    $user = session('user', []);
    if (is_array($user) && ! empty($user['staff_id'])) {
        return redirect()->to(AppBasePath::url('/dashboard'));
    }

    $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

    return redirect($base.'/auth/login');
});

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(EnsureFinanceSession::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-advances', fn () => Inertia::render('Placeholder', [
        'pageTitle' => 'My Advances',
    ]))->name('my-advances');
    Route::get('/my-missions', fn () => Inertia::render('Placeholder', [
        'pageTitle' => 'My Missions',
    ]))->name('my-missions');
    Route::get('/budgets', fn () => Inertia::render('Placeholder', [
        'pageTitle' => 'Budgets',
    ]))->name('budgets');
});
