<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskAgentController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskSlaRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminReferenceSyncController;
use App\Http\Controllers\Api\V1\Admin\HelpdeskSettingsController;
use App\Http\Controllers\Api\V1\Auth\ExchangeTokenController;
use App\Http\Controllers\Api\V1\Auth\StaffSsoController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PublicTicketResolutionController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketResolutionController;
use App\Http\Controllers\Api\V1\Webhooks\TeamsWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::post('/auth/exchange', ExchangeTokenController::class);
    Route::post('/auth/staff-sso', StaffSsoController::class);
    Route::post('/public/tickets/confirm-resolution', [PublicTicketResolutionController::class, 'confirm']);
    Route::get('/avatar/{user}', [AvatarController::class, 'show'])->middleware('throttle:300,1');
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
    Route::post('/webhooks/teams/activities', [TeamsWebhookController::class, 'activities']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', MeController::class);
        Route::get('/reference-data', [ReferenceDataController::class, 'index']);
        Route::get('/reference-data/staff', [ReferenceDataController::class, 'staff']);
        Route::apiResource('tickets', TicketController::class);
        Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store']);
        Route::post('tickets/{ticket}/submit-resolution', [TicketResolutionController::class, 'submit']);

        Route::get('/reports/agent-dashboard', [ReportController::class, 'agentDashboard']);
        Route::get('/reports/my-requester', [ReportController::class, 'myRequesterReport']);
        Route::get('/reports/admin-summary', [ReportController::class, 'adminSummary']);
        Route::get('/reports/export', [ReportController::class, 'exportExcel']);

        Route::get('/admin/settings', [HelpdeskSettingsController::class, 'show']);
        Route::put('/admin/settings', [HelpdeskSettingsController::class, 'update']);
        Route::get('/admin/agents', [AdminHelpdeskAgentController::class, 'index']);
        Route::put('/admin/agents/{user}', [AdminHelpdeskAgentController::class, 'update']);
        Route::get('/admin/categories', [AdminHelpdeskCategoryController::class, 'index']);
        Route::post('/admin/categories', [AdminHelpdeskCategoryController::class, 'store']);
        Route::put('/admin/categories/{category}', [AdminHelpdeskCategoryController::class, 'update']);
        Route::delete('/admin/categories/{category}', [AdminHelpdeskCategoryController::class, 'destroy']);
        Route::get('/admin/sla-rules', [AdminHelpdeskSlaRuleController::class, 'index']);
        Route::post('/admin/sla-rules', [AdminHelpdeskSlaRuleController::class, 'store']);
        Route::put('/admin/sla-rules/{slaRule}', [AdminHelpdeskSlaRuleController::class, 'update']);
        Route::post('/admin/reference-sync', [AdminReferenceSyncController::class, 'store']);
        Route::get('/admin/audit-logs', [AdminAuditLogController::class, 'index']);
    });
});
