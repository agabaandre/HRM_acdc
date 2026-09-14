<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSetting;
use App\Services\FaqIngestService;
use App\Services\HelpdeskAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFaqIngestController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function show(Request $request, FaqIngestService $ingest): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $lastRaw = HelpdeskSetting::getValue(HelpdeskSetting::KEY_FAQ_INGEST_LAST_RESULT);
        $last = null;
        if ($lastRaw !== null && $lastRaw !== '') {
            try {
                $last = json_decode($lastRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $last = null;
            }
        }

        return response()->json([
            'data' => [
                'sources' => $ingest->configuredSources(),
                'last_result' => $last,
                'export_client_configured' => app(\App\Services\FaqExportClient::class)->isConfigured(),
                'default_apm_export_url' => rtrim((string) config('helpdesk.apm_base_url'), '/').'/api/apm/v1/faqs/export',
            ],
        ]);
    }

    public function store(
        Request $request,
        FaqIngestService $ingest,
        HelpdeskAuditLogger $auditLogger,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        try {
            $result = $ingest->ingestAll($request->user());
        } catch (\Throwable $e) {
            $auditLogger->log('faq_ingest.failed', null, null, null, [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 502);
        }

        $auditLogger->log('faq_ingest.completed', null, null, null, [
            'result' => $result,
        ]);

        return response()->json(['data' => $result]);
    }
}
