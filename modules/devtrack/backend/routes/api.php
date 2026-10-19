<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BlockedIpController;
use App\Http\Controllers\Api\V1\Admin\MailController;
use App\Http\Controllers\Api\V1\Admin\MigrationController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\BrandingController as AdminBrandingController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DataQualityReportController;
use App\Http\Controllers\Api\V1\Admin\DevTrackMetaController;
use App\Http\Controllers\Api\V1\Admin\ProjectsListReportController;
use App\Http\Controllers\Api\V1\Admin\TasksListReportController;
use App\Http\Controllers\Api\V1\Admin\EmailLogController;
use App\Http\Controllers\Api\V1\Admin\ProjectController;
use App\Http\Controllers\Api\V1\Admin\RaidItemController;
use App\Http\Controllers\Api\V1\Admin\SprintController;
use App\Http\Controllers\Api\V1\Admin\StaffShareController;
use App\Http\Controllers\Api\V1\Admin\StatusReportController;
use App\Http\Controllers\Api\V1\Admin\EnhancementController;
use App\Http\Controllers\Api\V1\Admin\ProjectModuleController;
use App\Http\Controllers\Api\V1\Admin\ReleaseController;
use App\Http\Controllers\Api\V1\Admin\WorkItemController;
use App\Http\Controllers\Api\V1\Admin\WorkplanIndicatorController;
use App\Http\Controllers\Api\V1\Admin\WorkRequestController;
use App\Http\Controllers\Api\V1\Admin\EmailProviderController;
use App\Http\Controllers\Api\V1\Admin\ExternalIntegrationController;
use App\Http\Controllers\Api\V1\Admin\TwoFactorController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\IntegrationAuthController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\IntegrationMailController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthenticateIntegrationJwt;
use App\Http\Middleware\EnsureTotpSetupComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\LogAdminAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/branding', [BrandingController::class, 'show']);
    Route::get('/branding/assets/{path}', [BrandingController::class, 'asset'])
        ->where('path', '.*');

    Route::prefix('admin')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');
        Route::post('/auth/sso-login', [AuthController::class, 'ssoLogin'])
            ->middleware('throttle:10,1');
        Route::get('/auth/microsoft/redirect', [AuthController::class, 'microsoftRedirect'])
            ->middleware('throttle:20,1');
        Route::post('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback'])
            ->middleware('throttle:20,1');
        Route::post('/auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1');
        Route::post('/auth/verify-2fa', [TwoFactorController::class, 'verify'])
            ->middleware('throttle:10,1');
        Route::post('/auth/resend-2fa-email', [TwoFactorController::class, 'resendEmail'])
            ->middleware('throttle:3,1');
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:5,1');
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', EnsureUserIsActive::class, EnsureTotpSetupComplete::class, LogAdminAccess::class])->group(function () {
            Route::get('/auth/me', [AuthController::class, 'me']);
            Route::post('/auth/logout', [AuthController::class, 'logout']);
            Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
                ->middleware('throttle:10,1');
            Route::get('/auth/2fa/status', [TwoFactorController::class, 'status']);
            Route::post('/auth/2fa/email/enable', [TwoFactorController::class, 'enableEmail']);
            Route::post('/auth/2fa/email/disable', [TwoFactorController::class, 'disableEmail']);
            Route::post('/auth/2fa/totp/setup', [TwoFactorController::class, 'setupTotp']);
            Route::post('/auth/2fa/totp/confirm', [TwoFactorController::class, 'confirmTotp']);
            Route::post('/auth/2fa/totp/disable', [TwoFactorController::class, 'disableTotp']);
            Route::get('/dashboard', DashboardController::class);
            Route::get('/devtrack/options', [DevTrackMetaController::class, 'options']);
            Route::get('/devtrack/team', [DevTrackMetaController::class, 'team']);
            Route::get('/devtrack/staff', [DevTrackMetaController::class, 'staff']);
            Route::get('/workplan-indicators', [WorkplanIndicatorController::class, 'index']);
            Route::get('/workplan-indicators/{workplan_indicator}', [WorkplanIndicatorController::class, 'show']);
            Route::post('/work-requests/{work_request}/acknowledge', [WorkRequestController::class, 'acknowledge']);
            Route::post('/work-requests/{work_request}/convert', [WorkRequestController::class, 'convert']);
            Route::apiResource('work-requests', WorkRequestController::class)->except(['destroy']);
            Route::get('/projects/archived', [ProjectController::class, 'archivedIndex'])
                ->middleware(EnsureUserIsAdmin::class);
            Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])
                ->middleware(EnsureUserIsAdmin::class);
            Route::post('/projects/{project}/unarchive', [ProjectController::class, 'unarchive'])
                ->middleware(EnsureUserIsAdmin::class);
            Route::post('/projects/{project}/advance-stage', [ProjectController::class, 'advanceStage']);
            Route::apiResource('projects', ProjectController::class)->except(['destroy']);
            Route::apiResource('project-modules', ProjectModuleController::class)->except(['destroy']);
            Route::apiResource('enhancements', EnhancementController::class)->except(['destroy']);
            Route::apiResource('releases', ReleaseController::class)->except(['destroy']);
            Route::apiResource('work-items', WorkItemController::class);
            Route::apiResource('sprints', SprintController::class)->except(['destroy']);
            Route::apiResource('raid-items', RaidItemController::class)->except(['show']);
            Route::get('/reports/data-quality', DataQualityReportController::class);
            Route::get('/reports/projects', [ProjectsListReportController::class, 'index']);
            Route::get('/reports/projects/export', [ProjectsListReportController::class, 'export']);
            Route::get('/reports/tasks', TasksListReportController::class);
            Route::apiResource('status-reports', StatusReportController::class)->except(['destroy']);
            Route::get('/email-logs', [EmailLogController::class, 'index']);
            Route::get('/email-logs/filter-options', [EmailLogController::class, 'filterOptions']);

            // Approved accounts may manage their own clients and pick a provider.
            Route::get('/email-providers/drivers', [EmailProviderController::class, 'drivers']);
            Route::get('/email-providers', [EmailProviderController::class, 'index']);
            Route::apiResource('external-integrations', ExternalIntegrationController::class);

            Route::middleware(EnsureUserIsAdmin::class)->group(function () {
                Route::post('/workplan-indicators', [WorkplanIndicatorController::class, 'store']);
                Route::put('/workplan-indicators/{workplan_indicator}', [WorkplanIndicatorController::class, 'update']);
                Route::patch('/workplan-indicators/{workplan_indicator}', [WorkplanIndicatorController::class, 'update']);
                Route::delete('/workplan-indicators/{workplan_indicator}', [WorkplanIndicatorController::class, 'destroy']);
                Route::post('/email-logs/retry-failed', [EmailLogController::class, 'retryFailed'])
                    ->middleware('throttle:10,1');
                Route::post('/email-logs/{email_log}/retry', [EmailLogController::class, 'retry'])
                    ->middleware('throttle:30,1');

                Route::apiResource('users', UserController::class);
                Route::post('/users/{user}/approve', [UserController::class, 'approve']);
                Route::post('/users/{user}/reject', [UserController::class, 'reject']);
                Route::get('/staff', [StaffShareController::class, 'index']);
                Route::get('/staff-share/settings', [StaffShareController::class, 'settings']);
                Route::put('/staff-share/settings', [StaffShareController::class, 'updateSettings']);
                Route::post('/staff-share/sync', [StaffShareController::class, 'sync'])
                    ->middleware('throttle:3,1');
                Route::get('/audit-logs/filter-options', [AuditLogController::class, 'filterOptions']);
                Route::get('/audit-logs/stats', [AuditLogController::class, 'stats']);
                Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
                    ->middleware('throttle:10,1');
                Route::post('/audit-logs/resolve-suspicious', [AuditLogController::class, 'resolveOpenSuspicious'])
                    ->middleware('throttle:10,1');
                Route::post('/audit-logs/{audit_log}/resolve', [AuditLogController::class, 'resolve'])
                    ->middleware('throttle:30,1');
                Route::get('/audit-logs', [AuditLogController::class, 'index']);

                Route::get('/blocked-ips', [BlockedIpController::class, 'index']);
                Route::get('/blocked-emails', [BlockedIpController::class, 'indexEmails']);
                Route::post('/blocked-ips', [BlockedIpController::class, 'store'])
                    ->middleware('throttle:30,1');
                Route::post('/blocked-ips/block-suspicious', [BlockedIpController::class, 'blockSuspicious'])
                    ->middleware('throttle:10,1');
                Route::delete('/blocked-ips/{blocked_ip}', [BlockedIpController::class, 'destroy'])
                    ->middleware('throttle:30,1');
                Route::delete('/blocked-emails/{blocked_email}', [BlockedIpController::class, 'destroyEmail'])
                    ->middleware('throttle:30,1');

                Route::post('/send-mail', [MailController::class, 'send'])
                    ->middleware('throttle:30,1');

                Route::get('/branding', [AdminBrandingController::class, 'show']);
                Route::post('/branding', [AdminBrandingController::class, 'update']);
                Route::put('/branding', [AdminBrandingController::class, 'update']);

                Route::post('/email-providers/{email_provider}/test', [EmailProviderController::class, 'test']);
                Route::post('/email-providers/{email_provider}/set-default', [EmailProviderController::class, 'setDefault']);
                Route::apiResource('email-providers', EmailProviderController::class)->except(['index', 'destroy']);

                Route::get('/migration/export', [MigrationController::class, 'export'])
                    ->middleware('throttle:20,60');
                Route::post('/migration/import', [MigrationController::class, 'import'])
                    ->middleware('throttle:5,60');
            });
        });
    });

    Route::post('/integrations/auth/token', [IntegrationAuthController::class, 'token'])
        ->middleware('throttle:30,1');

    Route::middleware(AuthenticateIntegrationJwt::class)->prefix('integrations')->group(function () {
        Route::get('/status', [IntegrationMailController::class, 'status']);
        Route::get('/logs/{logId}', [IntegrationMailController::class, 'showLog'])->whereNumber('logId');
        Route::post('/send', [IntegrationMailController::class, 'send'])->middleware('throttle:60,1');
    });
});
