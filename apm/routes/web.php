<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\MatrixController;
use App\Http\Middleware\CheckSessionMiddleware;
use App\Models\Matrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\GenericApprovalController;
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

            // dd($json);

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
})->name('home')->middleware(CheckSessionMiddleware::class);

// Workflow Management Routes
Route::middleware([CheckSessionMiddleware::class])->group(function () {
    Route::resource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/add-definition', [WorkflowController::class, 'addDefinition'])->name('workflows.add-definition');
    Route::post('workflows/{workflow}/store-definition', [WorkflowController::class, 'storeDefinition'])->name('workflows.store-definition');
    Route::get('workflows/{workflow}/approvers', [WorkflowController::class, 'approvers'])->name('workflows.approvers');
    Route::put('workflows/{workflow}/approvers/{approver}', [WorkflowController::class, 'updateApprover'])->name('workflows.update-approver');
    Route::post('workflows/{workflow}/approvers/bulk-assign', [WorkflowController::class, 'bulkAssignApprovers'])->name('workflows.bulk-assign-approvers');
    Route::get('workflows/{workflow}/assign-staff', [WorkflowController::class, 'assignStaff'])->name('workflows.assign-staff');
    Route::post('workflows/{workflow}/assign-staff', [WorkflowController::class, 'storeStaff'])->name('workflows.store-assigned-staff');
    Route::post('workflows/{workflow}/store-staff', [WorkflowController::class, 'ajaxStoreStaff'])->name('workflows.ajax-store-staff');
    Route::get('workflows/{workflow}/remove-staff/{approverId}', [WorkflowController::class, 'ajaxRemoveStaff'])->name('workflows.ajax-remove-staff');
  
    // Memo Management Routes
    Route::resource('memos', MemoController::class);

    // Approval Management Routes
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{memo}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{memo}', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::get('approvals/{memo}/history', [ApprovalController::class, 'history'])->name('approvals.history');
});

// Group with session check for all resource routes
// AJAX route to get budget codes by fund type and division
Route::get('/budget-codes/by-fund-type', [App\Http\Controllers\ActivityController::class, 'getBudgetCodesByFundType'])
    ->name('budget-codes.by-fund-type');

Route::group(['middleware' => ['web', CheckSessionMiddleware::class]], function () {
    // Resource Routes
    Route::resource('fund-types', App\Http\Controllers\FundTypeController::class);
    Route::resource('fund-codes', App\Http\Controllers\FundCodeController::class);
    Route::get('fund-codes/{fundCode}/transactions', [App\Http\Controllers\FundCodeController::class, 'transactions'])->name('fund-codes.transactions');
    Route::resource('divisions', App\Http\Controllers\DivisionController::class);
    Route::resource('directorates', App\Http\Controllers\DirectorateController::class);
    Route::resource('staff', App\Http\Controllers\StaffController::class);
    Route::resource('request-types', App\Http\Controllers\RequestTypeController::class);
    Route::resource('locations', App\Http\Controllers\LocationController::class);
    Route::resource('cost-items', App\Http\Controllers\CostItemController::class);
    Route::resource('non-travel-categories', App\Http\Controllers\NonTravelMemoCategoryController::class);
    
    // Add matrices and activities resources inside the middleware group
    Route::resource('matrices', MatrixController::class);
    Route::get('/matrices/request_approval/{matrix}', [MatrixController::class, 'request_approval'])->name('matrices.request_approval');
    Route::post('/matrices/{matrix}/status', [MatrixController::class, 'update_status'])
    ->name('matrices.status');
    

    Route::resource('matrices.activities', ActivityController::class);

    Route::post('/matrices/{matrix}/activities/{activity}/status', [ActivityController::class, 'update_status'])
    ->name('matrices.activities.status');
        Route::post('/matrices/activities/batch/status', [ActivityController::class, 'batch_update_status'])
        ->name('matrices.activities.batch.status');

    Route::get('/participant-schedules', [ActivityController::class, 'get_participant_schedules'])->name('participant-schedules');
    
    // User Schedule Route
    Route::get('/my-activities', function() {
        return view('activities.user-schedule');
    })->name('activities.user-schedule');
    
    // AJAX route for user schedule data
    Route::get('/my-activities/data', [ActivityController::class, 'get_participant_schedules'])->name('activities.user-schedule.data');
    
    // Single Memo Routes
    Route::get('/single-memos', [ActivityController::class, 'singlememos'])->name('activities.single-memos.index');
    Route::get('/single-memos/create', [ActivityController::class, 'createSingleMemo'])->name('activities.single-memos.create');
    Route::post('/single-memos', [ActivityController::class, 'storeSingleMemo'])->name('activities.single-memos.store');
    Route::get('/single-memos/{activity}', [ActivityController::class, 'showSingleMemo'])->name('activities.single-memos.show');
    Route::post('/single-memos/{activity}/submit-for-approval', [ActivityController::class, 'submitSingleMemoForApproval'])->name('activities.single-memos.submit-for-approval');
    Route::post('/single-memos/{activity}/update-status', [ActivityController::class, 'updateSingleMemoStatus'])->name('activities.single-memos.update-status');
    Route::get('/single-memos/{activity}/status', [ActivityController::class, 'showSingleMemoStatus'])->name('activities.single-memos.status');
    
    // Non-Travel Memo Routes
    Route::resource('non-travel', App\Http\Controllers\NonTravelMemoController::class);
    Route::delete('non-travel/{nonTravel}/remove-attachment', [App\Http\Controllers\NonTravelMemoController::class, 'removeAttachment'])->name('non-travel.remove-attachment');
    Route::get('non-travel/{nonTravel}/print', [App\Http\Controllers\NonTravelMemoController::class, 'print'])->name('non-travel.print');
    
    // Request for ARF Routes
    Route::resource('request-arf', App\Http\Controllers\RequestARFController::class);
    Route::delete('request-arf/{requestARF}/remove-attachment', [App\Http\Controllers\RequestARFController::class, 'removeAttachment'])->name('request-arf.remove-attachment');
    
    // Special Memo Routes
    Route::resource('special-memo', App\Http\Controllers\SpecialMemoController::class);
    Route::delete('special-memo/{specialMemo}/remove-attachment', [App\Http\Controllers\SpecialMemoController::class, 'removeAttachment'])->name('special-memo.remove-attachment');
    Route::get('special-memo/{specialMemo}/print', [App\Http\Controllers\SpecialMemoController::class, 'print'])->name('special-memo.print');

// Special Memo Approval Routes
Route::post('special-memo/{specialMemo}/submit-for-approval', [App\Http\Controllers\SpecialMemoController::class, 'submitForApproval'])->name('special-memo.submit-for-approval');
Route::post('special-memo/{specialMemo}/update-status', [App\Http\Controllers\SpecialMemoController::class, 'updateStatus'])->name('special-memo.update-status');
Route::get('special-memo/{specialMemo}/status', [App\Http\Controllers\SpecialMemoController::class, 'status'])->name('special-memo.status');
    
    // Request for Services Routes
    Route::resource('service-requests', App\Http\Controllers\ServiceRequestController::class);
    Route::delete('service-requests/{serviceRequest}/remove-attachment', [App\Http\Controllers\ServiceRequestController::class, 'removeAttachment'])->name('service-requests.remove-attachment');

    // Reports
    Route::get('reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');

    // Generic Approval Routes
    Route::post('/approve/{model}/{id}', [GenericApprovalController::class, 'updateStatus'])->name('generic.approve');
    Route::post('/submit-for-approval/{model}/{id}', [GenericApprovalController::class, 'submitForApproval'])->name('generic.submit');
    Route::post('/batch-approve', [GenericApprovalController::class, 'batchUpdateStatus'])->name('generic.batch-approve');
    Route::get('/approval-trail/{model}/{id}', [GenericApprovalController::class, 'showApprovalTrail'])->name('generic.trail');

    Route::get('/test', function(){
        $matrix = Matrix::where('id',7)->first();
        echo(send_matrix_email_notification( $matrix,  'matrix_approval'));
    });

});

// Non-Travel Memo Approval Routes
Route::post('non-travel/{nonTravel}/submit-for-approval', [App\Http\Controllers\NonTravelMemoController::class, 'submitForApproval'])->name('non-travel.submit-for-approval');
Route::post('non-travel/{nonTravel}/update-status', [App\Http\Controllers\NonTravelMemoController::class, 'updateStatus'])->name('non-travel.update-status');
Route::get('non-travel/{nonTravel}/status', [App\Http\Controllers\NonTravelMemoController::class, 'status'])->name('non-travel.status');


