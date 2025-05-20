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
Route::get('workflows/{workflow}/assign-staff', [WorkflowController::class, 'assignStaff'])->name('workflows.assign-staff');
Route::post('workflows/{workflow}/assign-staff', [WorkflowController::class, 'storeStaff'])->name('workflows.store-assigned-staff');
Route::post('workflows/{workflow}/ajax-store-staff', [WorkflowController::class, 'ajaxStoreStaff'])->name('workflows.ajax-store-staff');
Route::delete('workflows/{workflow}/ajax-remove-staff/{approverId}', [WorkflowController::class, 'ajaxRemoveStaff'])->name('workflows.ajax-remove-staff');

// Memo Management Routes
Route::resource('memos', MemoController::class);

// Approval Management Routes
Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::get('approvals/{memo}', [ApprovalController::class, 'show'])->name('approvals.show');
Route::post('approvals/{memo}', [ApprovalController::class, 'approve'])->name('approvals.approve');
Route::get('approvals/{memo}/history', [ApprovalController::class, 'history'])->name('approvals.history');

// Disable authentication for all routes in the group
Route::group(['middleware' => ['web']], function () {
    // Resource Routes
    Route::resource('fund-types', App\Http\Controllers\FundTypeController::class);
    Route::resource('fund-codes', App\Http\Controllers\FundCodeController::class);
    Route::resource('divisions', App\Http\Controllers\DivisionController::class);
    Route::resource('directorates', App\Http\Controllers\DirectorateController::class);
    Route::resource('staff', App\Http\Controllers\StaffController::class);
    Route::resource('request-types', App\Http\Controllers\RequestTypeController::class);
    
    // Add matrices and activities resources inside the middleware group
    Route::resource('matrices', MatrixController::class);
    Route::resource('matrices.activities', ActivityController::class);
    
    // Non-Travel Memo Routes
    Route::resource('non-travel', App\Http\Controllers\NonTravelMemoController::class);
    Route::delete('non-travel/{nonTravel}/remove-attachment', [App\Http\Controllers\NonTravelMemoController::class, 'removeAttachment'])->name('non-travel.remove-attachment');
    
    // Request for ARF Routes
    Route::resource('request-arf', App\Http\Controllers\RequestARFController::class);
    Route::delete('request-arf/{requestARF}/remove-attachment', [App\Http\Controllers\RequestARFController::class, 'removeAttachment'])->name('request-arf.remove-attachment');
    
    // Special Memo Routes
    Route::resource('special-memo', App\Http\Controllers\SpecialMemoController::class);
    Route::delete('special-memo/{specialMemo}/remove-attachment', [App\Http\Controllers\SpecialMemoController::class, 'removeAttachment'])->name('special-memo.remove-attachment');
    
    // Request for Services Routes
    Route::resource('service-requests', App\Http\Controllers\ServiceRequestController::class);
    Route::delete('service-requests/{serviceRequest}/remove-attachment', [App\Http\Controllers\ServiceRequestController::class, 'removeAttachment'])->name('service-requests.remove-attachment');
});
