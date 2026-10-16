<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->prefix('performance')->name('performance.')->group(function () use ($spa): void {
    Route::get('/', fn () => $spa('performance'))->name('index');
    Route::get('/ppa_dashboard', fn () => $spa('performance?tab=dashboard'))->name('ppa-dashboard');
    Route::get('/my_ppas', fn () => $spa('performance?tab=my'))->name('my-ppas');
    Route::get('/pending_approval', fn () => $spa('performance?tab=pending'))->name('pending');

    Route::get('/create', function (Request $request) use ($spa) {
        $path = 'performance/create';
        $query = $request->getQueryString();

        if ($query) {
            $path .= '?'.$query;
        }

        return $spa($path);
    })->name('ppa.create');
    Route::get('/view_ppa/{entryId}/{staffId}', fn (string $entryId, string $staffId) => $spa("performance/form/ppa/{$entryId}/{$staffId}"))
        ->name('ppa.form');
    Route::get('/midterm/midterm_review/{entryId}/{staffId}', fn (string $entryId, string $staffId) => $spa("performance/form/midterm/{$entryId}/{$staffId}"))
        ->name('midterm.form');
    Route::get('/endterm/endterm_review/{entryId}/{staffId}', fn (string $entryId, string $staffId) => $spa("performance/form/endterm/{$entryId}/{$staffId}"))
        ->name('endterm.form');
});
