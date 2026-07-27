<?php

use App\Http\Controllers\Api\V1\Tools\ItAssetController;
use App\Http\Controllers\Api\V1\Tools\LicenseController;
use App\Http\Controllers\Api\V1\Tools\SoftwareRequestController;
use App\Http\Controllers\Api\V1\Tools\HostingRequestController;
use App\Http\Controllers\Api\V1\Tools\InnovationRequestController;
use App\Http\Controllers\Api\V1\Tools\InformationSystemController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminDivisionAgentController;
use App\Http\Controllers\Api\V1\Admin\AdminFaqIngestController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskAgentController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskBusinessUnitController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskRiskMatrixController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskSupportGroupController;
use App\Http\Controllers\Api\V1\Admin\AdminKbArticleController;
use App\Http\Controllers\Api\V1\Admin\AdminHelpdeskSlaRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminEmailTicketCleanupController;
use App\Http\Controllers\Api\V1\Admin\AdminReferenceSyncController;
use App\Http\Controllers\Api\V1\Admin\AdminStaffPermissionController;
use App\Http\Controllers\Api\V1\Admin\HelpdeskSettingsController;
use App\Http\Controllers\Api\V1\Auth\ExchangeTokenController;
use App\Http\Controllers\Api\V1\Auth\StaffSsoController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CbpModulesController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\BusinessUnitController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HelpdeskAskController;
use App\Http\Controllers\Api\V1\KbArticleController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeWorkModeController;
use App\Http\Controllers\Api\V1\PublicScreenController;
use App\Http\Controllers\Api\V1\PublicTicketResolutionController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\AgentMonthlyReportController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RichTextImageController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketAttachmentDownloadController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketInlineImageController;
use App\Http\Controllers\Api\V1\TicketResolutionController;
use App\Http\Controllers\Api\V1\Webhooks\TeamsWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::post('/auth/exchange', ExchangeTokenController::class)->middleware('throttle:30,1');
    Route::post('/auth/staff-sso', StaffSsoController::class)->middleware('throttle:30,1');
    Route::post('/public/tickets/confirm-resolution', [PublicTicketResolutionController::class, 'confirm'])
        ->middleware('throttle:30,1');

    Route::get('/attachments/{attachment}/file', [TicketAttachmentDownloadController::class, 'file'])
        ->middleware('throttle:300,1');

    // Read-only TV / lobby dashboard — aggregate stats only, NEVER PII.
    Route::get('/public/screen', PublicScreenController::class)->middleware('throttle:120,1');
    Route::get('/public/screen/units', [PublicScreenController::class, 'units'])->middleware('throttle:60,1');
    Route::get('/avatar/{user}', [AvatarController::class, 'show'])->middleware('throttle:300,1');
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/business-units', [BusinessUnitController::class, 'index']);
    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
    Route::post('/webhooks/teams/activities', [TeamsWebhookController::class, 'activities']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', MeController::class);
        Route::put('/me/work-mode', [MeWorkModeController::class, 'update']);
        Route::get('/cbp-modules', CbpModulesController::class);
        Route::get('/reference-data', [ReferenceDataController::class, 'index']);
        Route::get('/reference-data/staff', [ReferenceDataController::class, 'staff']);
        Route::post('/rich-text-images', [RichTextImageController::class, 'store']);
        Route::delete('/rich-text-images', [RichTextImageController::class, 'destroy']);

        Route::post('/ai/ask', HelpdeskAskController::class)->middleware('throttle:24,1');

        // Knowledge base — readable by any signed-in helpdesk user.
        Route::get('/kb/articles', [KbArticleController::class, 'index']);
        Route::get('/kb/articles/{article}', [KbArticleController::class, 'show']);

        // KB management — admin role OR helpdesk_profiles.can_manage_kb = 1.
        Route::get('/admin/kb/articles', [AdminKbArticleController::class, 'index']);
        Route::post('/admin/kb/articles', [AdminKbArticleController::class, 'store']);
        Route::put('/admin/kb/articles/{article}', [AdminKbArticleController::class, 'update']);
        Route::delete('/admin/kb/articles/{article}', [AdminKbArticleController::class, 'destroy']);

        // Ticket reassignment (open status only; reason required; logged).
        Route::get('/tickets/{ticket}/eligible-agents', [TicketController::class, 'eligibleAgents']);
        Route::get('/tickets/{ticket}/linkable-assets', [TicketController::class, 'linkableAssets']);
        Route::get('/tickets/{ticket}/linkable-information-systems', [TicketController::class, 'linkableInformationSystems']);
        Route::post('/tickets/{ticket}/reassign', [TicketController::class, 'reassign']);

        Route::apiResource('tickets', TicketController::class);
        Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store']);
        Route::delete('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'destroy']);
        Route::post('tickets/{ticket}/inline-images', [TicketInlineImageController::class, 'store']);
        Route::delete('tickets/{ticket}/inline-images/{attachment}', [TicketInlineImageController::class, 'destroy']);
        Route::post('tickets/{ticket}/submit-resolution', [TicketResolutionController::class, 'submit']);
        Route::post('tickets/{ticket}/confirm-close', [TicketController::class, 'confirmClose']);
        Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen']);

        Route::get('/reports/agent-dashboard', [ReportController::class, 'agentDashboard']);
        Route::get('/reports/my-requester', [ReportController::class, 'myRequesterReport']);
        Route::get('/reports/admin-summary', [ReportController::class, 'adminSummary']);
        Route::get('/reports/export', [ReportController::class, 'exportExcel']);
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf']);
        Route::get('/reports/agent-monthly', [AgentMonthlyReportController::class, 'index']);
        Route::get('/reports/agent-monthly/{report}/export-pdf', [AgentMonthlyReportController::class, 'exportPdf']);
        Route::get('/reports/agent-monthly/{report}', [AgentMonthlyReportController::class, 'show']);

        Route::get('/admin/settings', [HelpdeskSettingsController::class, 'show']);
        Route::put('/admin/settings', [HelpdeskSettingsController::class, 'update']);
        Route::post('/admin/settings/test-ai', [HelpdeskSettingsController::class, 'testAi']);
        Route::get('/admin/faq-ingest', [AdminFaqIngestController::class, 'show']);
        Route::post('/admin/faq-ingest', [AdminFaqIngestController::class, 'store']);
        Route::get('/admin/agents', [AdminHelpdeskAgentController::class, 'index']);
        Route::put('/admin/agents/{user}', [AdminHelpdeskAgentController::class, 'update']);
        Route::put('/admin/agents/{user}/disabled', [AdminHelpdeskAgentController::class, 'setDisabled']);
        Route::get('/admin/support-groups', [AdminHelpdeskSupportGroupController::class, 'index']);
        Route::post('/admin/support-groups', [AdminHelpdeskSupportGroupController::class, 'store']);
        Route::put('/admin/support-groups/{group}', [AdminHelpdeskSupportGroupController::class, 'update']);
        Route::delete('/admin/support-groups/{group}', [AdminHelpdeskSupportGroupController::class, 'destroy']);
        Route::get('/admin/staff-permissions', [AdminStaffPermissionController::class, 'index']);
        Route::put('/admin/staff-permissions/{user}', [AdminStaffPermissionController::class, 'update']);

        // Division-based agent designation (Settings → General).
        Route::get('/admin/agents/division-candidates', [AdminDivisionAgentController::class, 'candidates']);
        Route::post('/admin/agents/designate', [AdminDivisionAgentController::class, 'designate']);
        Route::delete('/admin/agents/designate/{staffId}', [AdminDivisionAgentController::class, 'undesignate'])
            ->whereNumber('staffId');
        Route::get('/admin/categories', [AdminHelpdeskCategoryController::class, 'index']);
        Route::post('/admin/categories', [AdminHelpdeskCategoryController::class, 'store']);
        Route::put('/admin/categories/{category}', [AdminHelpdeskCategoryController::class, 'update']);
        Route::post('/admin/categories/{category}/remap', [AdminHelpdeskCategoryController::class, 'remap']);
        Route::delete('/admin/categories/{category}', [AdminHelpdeskCategoryController::class, 'destroy']);
        Route::get('/admin/business-units', [AdminHelpdeskBusinessUnitController::class, 'index']);
        Route::post('/admin/business-units', [AdminHelpdeskBusinessUnitController::class, 'store']);
        Route::put('/admin/business-units/{businessUnit}', [AdminHelpdeskBusinessUnitController::class, 'update']);
        Route::post('/admin/business-units/{businessUnit}/test-email-read', [AdminHelpdeskBusinessUnitController::class, 'testEmailRead']);
        Route::post('/admin/business-units/{businessUnit}/remap', [AdminHelpdeskBusinessUnitController::class, 'remap']);
        Route::delete('/admin/business-units/{businessUnit}', [AdminHelpdeskBusinessUnitController::class, 'destroy']);
        Route::get('/admin/risk-matrix', [AdminHelpdeskRiskMatrixController::class, 'index']);
        Route::post('/admin/risk-matrix', [AdminHelpdeskRiskMatrixController::class, 'store']);
        Route::post('/admin/risk-matrix/bulk', [AdminHelpdeskRiskMatrixController::class, 'bulkStore']);
        Route::put('/admin/risk-matrix/{riskMatrixEntry}', [AdminHelpdeskRiskMatrixController::class, 'update']);
        Route::delete('/admin/risk-matrix/{riskMatrixEntry}', [AdminHelpdeskRiskMatrixController::class, 'destroy']);
        Route::get('/admin/sla-rules', [AdminHelpdeskSlaRuleController::class, 'index']);
        Route::post('/admin/sla-rules', [AdminHelpdeskSlaRuleController::class, 'store']);
        Route::put('/admin/sla-rules/{slaRule}', [AdminHelpdeskSlaRuleController::class, 'update']);
        Route::post('/admin/reference-sync', [AdminReferenceSyncController::class, 'store']);
        Route::get('/admin/email-ticket-cleanup', [AdminEmailTicketCleanupController::class, 'preview']);
        Route::post('/admin/email-ticket-cleanup', [AdminEmailTicketCleanupController::class, 'destroy']);
        Route::get('/admin/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::post('/admin/audit-logs/{auditLog}/reverse', [AdminAuditLogController::class, 'reverse']);

        // Tools — IT Assets, Licenses, Software requests
        Route::get('/tools/it-assets/summary', [ItAssetController::class, 'summary']);
        Route::get('/tools/it-assets/export', [ItAssetController::class, 'export']);
        Route::get('/tools/it-assets/export-pdf', [ItAssetController::class, 'exportPdf']);
        Route::get('/tools/it-assets/categories', [ItAssetController::class, 'categories']);
        Route::post('/tools/it-assets/categories', [ItAssetController::class, 'storeCategory']);
        Route::put('/tools/it-assets/categories/{category}', [ItAssetController::class, 'updateCategory']);
        Route::delete('/tools/it-assets/categories/{category}', [ItAssetController::class, 'destroyCategory']);
        Route::get('/tools/it-assets/brands', [ItAssetController::class, 'brands']);
        Route::post('/tools/it-assets/brands', [ItAssetController::class, 'storeBrand']);
        Route::put('/tools/it-assets/brands/{brand}', [ItAssetController::class, 'updateBrand']);
        Route::delete('/tools/it-assets/brands/{brand}', [ItAssetController::class, 'destroyBrand']);
        Route::get('/tools/it-assets', [ItAssetController::class, 'index']);
        Route::post('/tools/it-assets', [ItAssetController::class, 'store']);
        Route::put('/tools/it-assets/{asset}', [ItAssetController::class, 'update']);
        Route::delete('/tools/it-assets/{asset}', [ItAssetController::class, 'destroy']);

        Route::get('/tools/licenses/summary', [LicenseController::class, 'summary']);
        Route::get('/tools/licenses/export', [LicenseController::class, 'export']);
        Route::get('/tools/licenses/export-pdf', [LicenseController::class, 'exportPdf']);
        Route::get('/tools/licenses', [LicenseController::class, 'index']);
        Route::post('/tools/licenses', [LicenseController::class, 'store']);
        Route::put('/tools/licenses/{license}', [LicenseController::class, 'update']);
        Route::delete('/tools/licenses/{license}', [LicenseController::class, 'destroy']);

        Route::get('/tools/information-systems/languages', [InformationSystemController::class, 'languages']);
        Route::post('/tools/information-systems/languages', [InformationSystemController::class, 'storeLanguage']);
        Route::get('/tools/information-systems/summary', [InformationSystemController::class, 'summary']);
        Route::get('/tools/information-systems/export', [InformationSystemController::class, 'export']);
        Route::get('/tools/information-systems/export-pdf', [InformationSystemController::class, 'exportPdf']);
        Route::get('/tools/information-systems/reports/trends', [InformationSystemController::class, 'trends']);
        Route::get('/tools/information-systems', [InformationSystemController::class, 'index']);
        Route::post('/tools/information-systems', [InformationSystemController::class, 'store']);
        Route::get('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'show']);
        Route::put('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'update']);
        Route::delete('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'destroy']);
        Route::post('/tools/information-systems/{informationSystem}/modules', [InformationSystemController::class, 'storeModule']);
        Route::put('/tools/information-systems/{informationSystem}/modules/{module}', [InformationSystemController::class, 'updateModule']);
        Route::delete('/tools/information-systems/{informationSystem}/modules/{module}', [InformationSystemController::class, 'destroyModule']);

        Route::get('/tools/software-requests/summary', [SoftwareRequestController::class, 'summary']);
        Route::get('/tools/software-requests/export', [SoftwareRequestController::class, 'export']);
        Route::get('/tools/software-requests/export-pdf', [SoftwareRequestController::class, 'exportPdf']);
        Route::get('/tools/software-requests', [SoftwareRequestController::class, 'index']);
        Route::post('/tools/software-requests', [SoftwareRequestController::class, 'store']);
        Route::get('/tools/software-requests/{softwareRequest}', [SoftwareRequestController::class, 'show']);
        Route::put('/tools/software-requests/{softwareRequest}', [SoftwareRequestController::class, 'update']);
        Route::post('/tools/software-requests/{softwareRequest}/hod-approve', [SoftwareRequestController::class, 'hodApprove']);
        Route::post('/tools/software-requests/{softwareRequest}/hod-reject', [SoftwareRequestController::class, 'hodReject']);
        Route::post('/tools/software-requests/{softwareRequest}/approve', [SoftwareRequestController::class, 'approve']);
        Route::post('/tools/software-requests/{softwareRequest}/team', [SoftwareRequestController::class, 'syncTeam']);

        Route::get('/tools/hosting-requests', [HostingRequestController::class, 'index']);
        Route::post('/tools/hosting-requests', [HostingRequestController::class, 'store']);
        Route::get('/tools/hosting-requests/{hostingRequest}', [HostingRequestController::class, 'show']);
        Route::put('/tools/hosting-requests/{hostingRequest}', [HostingRequestController::class, 'update']);
        Route::post('/tools/hosting-requests/{hostingRequest}/hod-approve', [HostingRequestController::class, 'hodApprove']);
        Route::post('/tools/hosting-requests/{hostingRequest}/hod-reject', [HostingRequestController::class, 'hodReject']);
        Route::post('/tools/hosting-requests/{hostingRequest}/process', [HostingRequestController::class, 'process']);
        Route::post('/tools/hosting-requests/{hostingRequest}/complete', [HostingRequestController::class, 'complete']);

        Route::get('/tools/innovation-requests', [InnovationRequestController::class, 'index']);
        Route::post('/tools/innovation-requests', [InnovationRequestController::class, 'store']);
        Route::get('/tools/innovation-requests/{innovationRequest}', [InnovationRequestController::class, 'show']);
        Route::put('/tools/innovation-requests/{innovationRequest}', [InnovationRequestController::class, 'update']);
        Route::post('/tools/innovation-requests/{innovationRequest}/process', [InnovationRequestController::class, 'process']);
        Route::post('/tools/innovation-requests/{innovationRequest}/complete', [InnovationRequestController::class, 'complete']);
        Route::post('/tools/innovation-requests/{innovationRequest}/reject', [InnovationRequestController::class, 'reject']);
    });
});
