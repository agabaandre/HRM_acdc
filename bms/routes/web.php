<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\MatrixController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DivisionController;

// The root route that handles token decoding and user session management
Route::get('/', function (Request $request) {
    // Get token from query parameter
    $base64Token = $request->query('token');

    if ($base64Token) {
        try {
            // Decode the base64 token
            $decodedToken = base64_decode($base64Token);

            // Parse the JSON data
            $json = json_decode($decodedToken, true);

            if (!$json) {
                throw new Exception('Invalid token format');
            }

            // Save the decoded token to session as user data
            session(['user' => $json, 'base_url' => $json['base_url'] ?? '', 'permissions' => $json['permissions'] ?? []]);

        } catch (\Exception $e) {
            // Just redirect to home without error message since login functionality is removed
            return redirect('/home');
        }
    }

    // Redirect to home page with or without session data
    return redirect('/home');
});

// Home route
Route::get('/home', function () {
    return view('home', [
        'user' => session('user', []),
        'permissions' => session('permissions', []),
        'base_url' => session('base_url', ''),
    ]);
})->name('home');

// Workflow Management Routes
Route::resource('workflows', WorkflowController::class);
Route::get('workflows/{workflow}/add-definition', [WorkflowController::class, 'addDefinition'])->name('workflows.add-definition');
Route::post('workflows/{workflow}/store-definition', [WorkflowController::class, 'storeDefinition'])->name('workflows.store-definition');

// Division Management Routes
Route::resource('divisions', DivisionController::class);

// Memo Management Routes
Route::resource('memos', MemoController::class);

// Approval Management Routes
Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::get('approvals/{memo}', [ApprovalController::class, 'show'])->name('approvals.show');
Route::post('approvals/{memo}', [ApprovalController::class, 'approve'])->name('approvals.approve');
Route::get('approvals/{memo}/history', [ApprovalController::class, 'history'])->name('approvals.history');
// Settings-related routes
Route::middleware(['auth'])->group(function () {
    Route::resource('fundtypes', App\Http\Controllers\FundTypeController::class);
    Route::resource('fundcodes', App\Http\Controllers\FundCodeController::class);
    Route::resource('divisions', App\Http\Controllers\DivisionController::class);
    Route::resource('directorates', App\Http\Controllers\DirectorateController::class);
    Route::resource('staff', App\Http\Controllers\StaffController::class);
    Route::resource('requesttypes', App\Http\Controllers\RequestTypeController::class);
});

Route::resource('matrices', MatrixController::class);
Route::resource('matrices.activities', ActivityController::class);
