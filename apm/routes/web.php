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
use App\Http\Controllers\Api\SessionController;

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
    // Model Workflow Assignment Routes (must be before resource routes)
    Route::get('workflows/assign-models', [WorkflowController::class, 'assignModels'])->name('workflows.assign-models');
    Route::post('workflows/assign-models', [WorkflowController::class, 'storeModelAssignments'])->name('workflows.store-model-assignments');
    
    Route::resource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/add-definition', [WorkflowController::class, 'addDefinition'])->name('workflows.add-definition');
    Route::post('workflows/{workflow}/store-definition', [WorkflowController::class, 'storeDefinition'])->name('workflows.store-definition');
    Route::get('workflows/{workflow}/edit-definition/{definition}', [WorkflowController::class, 'editDefinition'])->name('workflows.edit-definition');
    Route::put('workflows/{workflow}/update-definition/{definition}', [WorkflowController::class, 'updateDefinition'])->name('workflows.update-definition');
    Route::delete('workflows/{workflow}/delete-definition/{definition}', [WorkflowController::class, 'deleteDefinition'])->name('workflows.delete-definition');
    Route::get('workflows/{workflow}/approvers', [WorkflowController::class, 'approvers'])->name('workflows.approvers');
    Route::put('workflows/{workflow}/approvers/{approver}', [WorkflowController::class, 'updateApprover'])->name('workflows.update-approver');
    Route::post('workflows/{workflow}/approvers/bulk-assign', [WorkflowController::class, 'bulkAssignApprovers'])->name('workflows.bulk-assign-approvers');
    Route::get('workflows/{workflow}/assign-staff', [WorkflowController::class, 'assignStaff'])->name('workflows.assign-staff');
    Route::post('workflows/{workflow}/assign-staff', [WorkflowController::class, 'storeStaff'])->name('workflows.store-assigned-staff');
    Route::post('workflows/{workflow}/store-staff', [WorkflowController::class, 'ajaxStoreStaff'])->name('workflows.ajax-store-staff');
    
    // Debug route for testing workflow store-staff
    Route::post('workflows/{workflow}/store-staff-debug', function(Request $request, $workflow) {
        return response()->json([
            'success' => true,
            'message' => 'Debug endpoint reached',
            'request_data' => $request->all(),
            'workflow_id' => $workflow,
            'headers' => $request->headers->all()
        ]);
    })->name('workflows.ajax-store-staff-debug');
    
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

// AJAX route to get fund type for a specific budget code
Route::get('/budget-codes/get-fund-type', [App\Http\Controllers\ActivityController::class, 'getFundTypeByBudgetCode'])
    ->name('budget-codes.get-fund-type');

// Route for Summernote image upload
Route::post('/image/upload', [App\Http\Controllers\ImageController::class, 'upload'])
    ->name('image.upload');

Route::group(['middleware' => ['web', CheckSessionMiddleware::class]], function () {
    // Resource Routes
    Route::resource('fund-types', App\Http\Controllers\FundTypeController::class)->except(['destroy']);
    
    // Fund Codes specific routes (must be before resource route)
    Route::get('fund-codes/download-template', [App\Http\Controllers\FundCodeController::class, 'downloadTemplate'])->name('fund-codes.download-template');
    Route::post('fund-codes/upload', [App\Http\Controllers\FundCodeController::class, 'upload'])->name('fund-codes.upload');
    Route::get('fund-codes/{fundCode}/transactions', [App\Http\Controllers\FundCodeController::class, 'transactions'])->name('fund-codes.transactions');
    Route::resource('fund-codes', App\Http\Controllers\FundCodeController::class)->except(['destroy']);
    
    // Funders Management
    Route::resource('funders', App\Http\Controllers\FunderController::class)->except(['destroy']);
    Route::resource('divisions', App\Http\Controllers\DivisionController::class)->only(['index', 'show']);
    Route::resource('directorates', App\Http\Controllers\DirectorateController::class);
    
    // Staff specific routes (must come before resource route)
    Route::get('staff/ajax', [App\Http\Controllers\StaffController::class, 'getStaffAjax'])->name('staff.ajax');
    Route::get('staff/export/{format}', [App\Http\Controllers\StaffController::class, 'export'])->name('staff.export');
    Route::get('/staff/{staff}/activities', [App\Http\Controllers\StaffController::class, 'getActivities'])->name('staff.activities');
    
    // Staff resource routes
    Route::resource('staff', App\Http\Controllers\StaffController::class);
    Route::resource('request-types', App\Http\Controllers\RequestTypeController::class);
    Route::resource('locations', App\Http\Controllers\LocationController::class)->except(['destroy']);
    Route::resource('cost-items', App\Http\Controllers\CostItemController::class)->except(['destroy']);
    Route::resource('non-travel-categories', App\Http\Controllers\NonTravelMemoCategoryController::class);
    
    // Jobs Management Routes
    Route::get('/jobs', [App\Http\Controllers\JobsController::class, 'index'])->name('jobs.index');
    Route::post('/jobs/execute-command', [App\Http\Controllers\JobsController::class, 'executeCommand'])->name('jobs.execute-command');
    Route::get('/jobs/env-content', [App\Http\Controllers\JobsController::class, 'getEnvContent'])->name('jobs.env-content');
    Route::post('/jobs/env-content', [App\Http\Controllers\JobsController::class, 'updateEnvContent'])->name('jobs.update-env-content');
    
    // Systemd Monitor Routes
    Route::get('/systemd-monitor', [App\Http\Controllers\SystemdMonitorController::class, 'index'])->name('systemd-monitor.index');
    Route::post('/systemd-monitor/execute', [App\Http\Controllers\SystemdMonitorController::class, 'executeCommand'])->name('systemd-monitor.execute');
    Route::get('/jobs/system-info', [App\Http\Controllers\JobsController::class, 'getSystemInfo'])->name('jobs.system-info');
    
    // Document Counter Management
    Route::get('/jobs/document-counters', [App\Http\Controllers\JobsController::class, 'getDocumentCounters'])->name('jobs.document-counters');
    Route::post('/jobs/reset-document-counters', [App\Http\Controllers\JobsController::class, 'resetDocumentCounters'])->name('jobs.reset-document-counters');
    Route::get('/jobs/document-counter-filters', [App\Http\Controllers\JobsController::class, 'getDocumentCounterFilters'])->name('jobs.document-counter-filters');
    Route::post('/jobs/reminders-schedule', [App\Http\Controllers\JobsController::class, 'executeRemindersSchedule'])->name('jobs.reminders-schedule');

    // Approver Dashboard Routes
Route::get('/approver-dashboard', [App\Http\Controllers\ApproverDashboardController::class, 'index'])->name('approver-dashboard.index');
Route::get('/api/approver-dashboard', [App\Http\Controllers\ApproverDashboardController::class, 'getDashboardData'])->name('approver-dashboard.api');
Route::get('/api/approver-dashboard/filter-options', [App\Http\Controllers\ApproverDashboardController::class, 'getFilterOptions'])->name('approver-dashboard.filter-options');
Route::get('/api/approver-dashboard/summary-stats', [App\Http\Controllers\ApproverDashboardController::class, 'getSummaryStats'])->name('approver-dashboard.summary-stats');

// Audit Logs Routes
Route::get('/audit-logs', [App\Http\Controllers\AuditLogsController::class, 'index'])->name('audit-logs.index');
Route::get('/audit-logs/cleanup-modal', [App\Http\Controllers\AuditLogsController::class, 'showCleanupModal'])->name('audit-logs.cleanup-modal');
Route::post('/audit-logs/cleanup', [App\Http\Controllers\AuditLogsController::class, 'cleanup'])->name('audit-logs.cleanup');
Route::get('/audit-logs/reversal-modal', [App\Http\Controllers\AuditLogsController::class, 'showReversalModal'])->name('audit-logs.reversal-modal');
Route::post('/audit-logs/reverse', [App\Http\Controllers\AuditLogsController::class, 'reverse'])->name('audit-logs.reverse');

// Pending Approvals Routes
Route::get('/pending-approvals', [App\Http\Controllers\PendingApprovalsController::class, 'index'])->name('pending-approvals.index');
Route::get('/api/pending-approvals', [App\Http\Controllers\PendingApprovalsController::class, 'getPendingApprovals'])->name('pending-approvals.api');
Route::get('/api/pending-approvals/category/{category}', [App\Http\Controllers\PendingApprovalsController::class, 'getByCategory'])->name('pending-approvals.by-category');
Route::get('/api/pending-approvals/summary', [App\Http\Controllers\PendingApprovalsController::class, 'getSummary'])->name('pending-approvals.summary');
Route::get('/api/pending-approvals/recent', [App\Http\Controllers\PendingApprovalsController::class, 'getRecentPending'])->name('pending-approvals.recent');
Route::post('/api/pending-approvals/mark-viewed', [App\Http\Controllers\PendingApprovalsController::class, 'markAsViewed'])->name('pending-approvals.mark-viewed');
Route::post('/api/pending-approvals/send-notification', [App\Http\Controllers\PendingApprovalsController::class, 'sendNotification'])->name('pending-approvals.send-notification');



    
    // Add matrices and activities resources inside the middleware group
    // IMPORTANT: Specific routes must come BEFORE resource routes to avoid conflicts
    Route::get('/matrices/pending-approvals', [MatrixController::class, 'pendingApprovals'])->name('matrices.pending-approvals');
    Route::match(['get', 'post'], '/matrices/request_approval/{matrix}', [MatrixController::class, 'request_approval'])->name('matrices.request_approval');
    Route::post('/matrices/{matrix}/status', [MatrixController::class, 'update_status'])->name('matrices.status');
    Route::get('/matrices/{matrix}/status', [MatrixController::class, 'status'])->name('matrices.view-status');
    
    // CSV Export Routes
    Route::get('/matrices/export/csv', [MatrixController::class, 'exportCsv'])->name('matrices.export.csv');
    Route::get('/matrices/export/division-csv', [MatrixController::class, 'exportDivisionCsv'])->name('matrices.export.division-csv');
    Route::get('/matrices/export/pending-approvals-csv', [MatrixController::class, 'exportPendingApprovalsCsv'])->name('matrices.export.pending-approvals-csv');
    Route::get('/matrices/export/approved-by-me-csv', [MatrixController::class, 'exportApprovedByMeCsv'])->name('matrices.export.approved-by-me-csv');
    
    Route::resource('matrices', MatrixController::class);
    
    Route::resource('matrices.activities', ActivityController::class);

    Route::post('/matrices/{matrix}/activities/{activity}/status', [ActivityController::class, 'update_status'])
        ->name('matrices.activities.status');
    Route::post('/matrices/activities/batch/status', [ActivityController::class, 'batch_update_status'])
        ->name('matrices.activities.batch.status');
    
    // Activity PDF Generation Route
    Route::get('/matrices/{matrix}/activities/{activity}/memo-pdf', [ActivityController::class, 'generateMemoPdf'])
        ->name('matrices.activities.memo-pdf');

    Route::get('/participant-schedules', [ActivityController::class, 'get_participant_schedules'])->name('participant-schedules');
    
    // User Schedule Route
    Route::get('/my-activities', function() {
        return view('activities.user-schedule');
    })->name('activities.user-schedule');
    
    // AJAX route for user schedule data
    Route::get('/my-activities/data', [ActivityController::class, 'get_participant_schedules'])->name('activities.user-schedule.data');
    
    // Single Memo Routes
    Route::get('/single-memos', [ActivityController::class, 'singlememos'])->name('activities.single-memos.index');
    Route::get('/single-memos/pending-approvals', [ActivityController::class, 'singleMemoPendingApprovals'])->name('activities.single-memos.pending-approvals');
    Route::get('/single-memos/create', [ActivityController::class, 'createSingleMemo'])->name('activities.single-memos.create');
    Route::post('/single-memos', [ActivityController::class, 'storeSingleMemo'])->name('activities.single-memos.store');
    
    // Specific routes must come before general routes
    Route::get('/single-memos/{matrix}/edit/{activity}', [ActivityController::class, 'editSingleMemo'])->name('activities.single-memos.edit');
    Route::put('/single-memos/{matrix}/update/{activity}', [ActivityController::class, 'updateSingleMemo'])->name('activities.single-memos.update');
    Route::get('/single-memos/{activity}/status', [ActivityController::class, 'showSingleMemoStatus'])->name('activities.single-memos.status');
    Route::get('/single-memos/{activity}/print', [ActivityController::class, 'printSingleMemo'])->name('activities.single-memos.print');
    Route::post('/single-memos/{activity}/submit-for-approval', [ActivityController::class, 'submitSingleMemoForApproval'])->name('activities.single-memos.submit-for-approval');
    Route::post('/single-memos/{activity}/update-status', [ActivityController::class, 'updateSingleMemoStatus'])->name('activities.single-memos.update-status');
    Route::post('/single-memos/{activity}/resubmit', [ActivityController::class, 'resubmitSingleMemo'])->name('activities.single-memos.resubmit');
    Route::delete('/single-memos/{activity}', [ActivityController::class, 'destroySingleMemo'])->name('activities.single-memos.destroy');
    
    // Staff activities route
    Route::get('/staff/{staff_id}/activities/matrix/{matrix}', [ActivityController::class, 'showStaffActivities'])->name('staff.activities');
    
    // Matrix division staff AJAX route
    Route::get('/matrices/{matrix}/division-staff-ajax', [MatrixController::class, 'getDivisionStaffAjax'])->name('matrices.division-staff-ajax');
    
    // Matrix activities for approvers AJAX route
        Route::get('/matrices/{matrix}/activities-for-approver', [MatrixController::class, 'getActivitiesForApprover'])->name('matrices.activities-for-approver');
        Route::get('/matrices/{matrix}/single-memos-for-approver', [MatrixController::class, 'getSingleMemosForApprover'])->name('matrices.single-memos-for-approver');
        Route::get('/matrices/{matrix}/budgets', [MatrixController::class, 'getMatrixBudgets'])->name('matrices.budgets');
        Route::get('/matrices/{matrix}/activities/{activity}/copy', [ActivityController::class, 'copy'])->name('matrices.activities.copy');
    
    // General route must come last - moved to end of file
    
    // Non-Travel Memo Routes
    // IMPORTANT: Specific routes must come BEFORE resource routes to avoid conflicts
    Route::get('non-travel/pending-approvals', [App\Http\Controllers\NonTravelMemoController::class, 'pendingApprovals'])->name('non-travel.pending-approvals');
    Route::delete('non-travel/{nonTravel}/remove-attachment', [App\Http\Controllers\NonTravelMemoController::class, 'removeAttachment'])->name('non-travel.remove-attachment');
    Route::get('non-travel/{nonTravel}/print', [App\Http\Controllers\NonTravelMemoController::class, 'print'])->name('non-travel.print');
    
    // Non-Travel Memo Export Routes
    Route::get('non-travel/export/my-submitted', [App\Http\Controllers\NonTravelMemoController::class, 'exportMySubmittedCsv'])->name('non-travel.export.my-submitted');
    Route::get('non-travel/export/all', [App\Http\Controllers\NonTravelMemoController::class, 'exportAllCsv'])->name('non-travel.export.all');
    
    Route::resource('non-travel', App\Http\Controllers\NonTravelMemoController::class);
    
    // Special Memo Routes
    // IMPORTANT: Specific routes must come BEFORE resource routes to avoid conflicts
    Route::get('special-memo/pending-approvals', [App\Http\Controllers\SpecialMemoController::class, 'pendingApprovals'])->name('special-memo.pending-approvals');
    
    // Special Memo Export Routes
    Route::get('special-memo/export/my-submitted', [App\Http\Controllers\SpecialMemoController::class, 'exportMySubmittedCsv'])->name('special-memo.export.my-submitted');
Route::get('special-memo/export/all', [App\Http\Controllers\SpecialMemoController::class, 'exportAllCsv'])->name('special-memo.export.all');
Route::get('special-memo/export/shared', [App\Http\Controllers\SpecialMemoController::class, 'exportSharedCsv'])->name('special-memo.export.shared');
    
    Route::resource('special-memo', App\Http\Controllers\SpecialMemoController::class);
    
    // Request for ARF Routes
    // ARF Export Routes  
    Route::get('request-arf/export/my-submitted', [App\Http\Controllers\RequestARFController::class, 'exportMySubmittedCsv'])->name('request-arf.export.my-submitted');
    Route::get('request-arf/export/all', [App\Http\Controllers\RequestARFController::class, 'exportAllCsv'])->name('request-arf.export.all');
    Route::get('request-arf/pending-approvals', [App\Http\Controllers\RequestARFController::class, 'pendingApprovals'])->name('request-arf.pending-approvals');
    
    Route::post('request-arf/store-from-modal', [App\Http\Controllers\RequestARFController::class, 'storeFromModal'])->name('request-arf.store-from-modal');
    Route::resource('request-arf', App\Http\Controllers\RequestARFController::class);
    Route::get('debug-arf-test', function() { return 'ARF route test successful'; });
    Route::get('debug-arf-controller', [App\Http\Controllers\RequestARFController::class, 'debugTest']);
    Route::post('debug-arf-post', function(\Illuminate\Http\Request $request) { 
        return response()->json(['message' => 'POST route working', 'data' => $request->all()]); 
    });
    Route::delete('request-arf/{requestARF}/remove-attachment', [App\Http\Controllers\RequestARFController::class, 'removeAttachment'])->name('request-arf.remove-attachment');
    Route::post('request-arf/{requestARF}/approve', [App\Http\Controllers\RequestARFController::class, 'approve'])->name('request-arf.approve');
    Route::post('request-arf/{requestARF}/submit-for-approval', [App\Http\Controllers\RequestARFController::class, 'submitForApproval'])->name('request-arf.submit-for-approval');
    Route::post('request-arf/{requestARF}/update-status', [App\Http\Controllers\RequestARFController::class, 'updateStatus'])->name('request-arf.update-status');
    Route::get('request-arf/{requestARF}/print', [App\Http\Controllers\RequestARFController::class, 'print'])->name('request-arf.print');
    
    Route::delete('special-memo/{specialMemo}/remove-attachment', [App\Http\Controllers\SpecialMemoController::class, 'removeAttachment'])->name('special-memo.remove-attachment');
    Route::get('special-memo/{specialMemo}/print', [App\Http\Controllers\SpecialMemoController::class, 'print'])->name('special-memo.print');

// Special Memo Approval Routes
Route::post('special-memo/{specialMemo}/submit-for-approval', [App\Http\Controllers\SpecialMemoController::class, 'submitForApproval'])->name('special-memo.submit-for-approval');
Route::post('special-memo/{specialMemo}/update-status', [App\Http\Controllers\SpecialMemoController::class, 'updateStatus'])->name('special-memo.update-status');
Route::post('special-memo/{specialMemo}/resubmit', [App\Http\Controllers\SpecialMemoController::class, 'resubmit'])->name('special-memo.resubmit');
Route::get('special-memo/{specialMemo}/status', [App\Http\Controllers\SpecialMemoController::class, 'status'])->name('special-memo.status');
    
    // Request for Services Routes
    // Specific routes must come before resource routes to avoid conflicts
    Route::get('service-requests/pending-approvals', [App\Http\Controllers\ServiceRequestController::class, 'pendingApprovals'])->name('service-requests.pending-approvals');
    Route::get('service-requests/export/my-submitted', [App\Http\Controllers\ServiceRequestController::class, 'exportMySubmitted'])->name('service-requests.export.my-submitted');
    Route::get('service-requests/export/all', [App\Http\Controllers\ServiceRequestController::class, 'exportAll'])->name('service-requests.export.all');
    Route::post('service-requests/get-source-data', [App\Http\Controllers\ServiceRequestController::class, 'getSourceData'])->name('service-requests.get-source-data');
    Route::post('service-requests/store-from-modal', [App\Http\Controllers\ServiceRequestController::class, 'storeFromModal'])->name('service-requests.store-from-modal');
    Route::get('service-requests/cost-items', [App\Http\Controllers\ServiceRequestController::class, 'getCostItems'])->name('service-requests.cost-items');
    
    // Resource routes
    Route::resource('service-requests', App\Http\Controllers\ServiceRequestController::class);
    Route::delete('service-requests/{serviceRequest}/remove-attachment', [App\Http\Controllers\ServiceRequestController::class, 'removeAttachment'])->name('service-requests.remove-attachment');
    Route::get('service-requests/{serviceRequest}/print', [App\Http\Controllers\ServiceRequestController::class, 'print'])->name('service-requests.print');
    
    // Change Request Routes
    Route::resource('change-requests', App\Http\Controllers\ChangeRequestController::class);
    Route::get('change-requests/pending-approvals', [App\Http\Controllers\ChangeRequestController::class, 'pendingApprovals'])->name('change-requests.pending-approvals');

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
Route::post('non-travel/{nonTravel}/resubmit', [App\Http\Controllers\NonTravelMemoController::class, 'resubmit'])->name('non-travel.resubmit');
Route::get('non-travel/{nonTravel}/status', [App\Http\Controllers\NonTravelMemoController::class, 'status'])->name('non-travel.status');

// Service Request Approval Routes
Route::post('service-requests/{serviceRequest}/submit-for-approval', [App\Http\Controllers\ServiceRequestController::class, 'submitForApproval'])->name('service-requests.submit-for-approval');
Route::post('service-requests/{serviceRequest}/update-status', [App\Http\Controllers\ServiceRequestController::class, 'updateStatus'])->name('service-requests.update-status');
Route::get('service-requests/{serviceRequest}/status', [App\Http\Controllers\ServiceRequestController::class, 'status'])->name('service-requests.status');

// Activities Routes
Route::get('/activities', [App\Http\Controllers\ActivityController::class, 'activitiesIndex'])->name('activities.index')->middleware(CheckSessionMiddleware::class);

// General single memo route - must come last to avoid conflicts
Route::get('/single-memos/{activity}', [ActivityController::class, 'show'])->name('activities.single-memos.show')->where('activity', '[0-9]+');


