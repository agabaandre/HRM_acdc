<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machine-readable FAQ export for Helpdesk knowledge-base ingest.
 */
class ApmFaqExportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeService($request);

        $categories = FaqCategory::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (FaqCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'sort_order' => $c->sort_order,
            ])
            ->values();

        $faqs = Faq::query()
            ->active()
            ->ordered()
            ->with('category:id,slug,name')
            ->get()
            ->map(function (Faq $faq) {
                return [
                    'external_id' => 'apm:faq:'.$faq->id,
                    'category_slug' => $faq->category?->slug,
                    'category_name' => $faq->category?->name,
                    'question' => $faq->question,
                    'answer_html' => $faq->resolved_answer,
                    'search_keywords' => $faq->search_keywords,
                    'sort_order' => $faq->sort_order,
                    'is_active' => $faq->is_active,
                    'updated_at' => optional($faq->updated_at)->toIso8601String(),
                ];
            })
            ->values();

        $publicFaqUrl = rtrim((string) config('app.url'), '/').'/faq';

        return response()->json([
            'data' => [
                'source' => 'apm',
                'source_label' => 'APM Help & FAQs',
                'source_url' => $publicFaqUrl,
                'exported_at' => now()->toIso8601String(),
                'categories' => $categories,
                'faqs' => $faqs,
            ],
        ]);
    }

    private function authorizeService(Request $request): void
    {
        $username = (string) config('services.staff_api.username');
        $password = (string) config('services.staff_api.password');
        if ($username === '' || $password === '') {
            abort(503, 'Staff API credentials are not configured on APM.');
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();
        if ($providedUser === '' || $providedPass === ''
            || ! hash_equals($username, $providedUser)
            || ! hash_equals($password, $providedPass)) {
            abort(401, 'Invalid Staff Share API credentials.');
        }
    }
}
