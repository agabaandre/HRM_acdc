<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Models\PortalLanguage;
use Modules\Settings\Services\PortalAiTranslationService;
use Modules\Settings\Services\PortalLanguageService;
use Modules\Settings\Services\PortalUiTranslationService;
use Modules\Settings\Support\PortalLocalesConfig;

class PortalLanguagesController extends Controller
{
    public function __construct(
        protected PortalLanguageService $languages,
        protected PortalUiTranslationService $translations,
        protected PortalAiTranslationService $aiTranslations,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $userLocale = $user instanceof PortalUser ? (string) ($user->langauge ?? '') : null;
        $cookie = (string) $request->cookie((string) PortalLocalesConfig::get('cookie', 'staff_portal_locale'), '');

        return response()->json([
            'data' => $this->languages->catalog($userLocale ?: null, $cookie !== '' ? $cookie : null),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:32'],
        ]);

        $payload = $this->languages->applyLocale($user, (string) $validated['locale']);
        $cookieName = (string) PortalLocalesConfig::get('cookie', 'staff_portal_locale');
        $minutes = (int) PortalLocalesConfig::get('cookie_minutes', 525600);

        return response()->json(['data' => $payload])
            ->cookie($cookieName, $payload['locale'], $minutes, '/', null, false, false);
    }

    public function index(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json([
            'data' => [
                'languages' => $this->languages->listForAdmin()->map(
                    fn (PortalLanguage $row) => $this->languages->toAdminArray($row)
                )->values()->all(),
                'groups' => $this->translations->groups(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $request->merge([
            'locale_code' => strtolower(trim((string) $request->input('locale_code', ''))),
        ]);

        $validated = $request->validate([
            'locale_code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z]{2}([_-][a-zA-Z0-9]+)*$/',
                Rule::unique('portal_languages', 'locale_code'),
            ],
            'name' => 'required|string|max:120',
            'google_translate_code' => 'nullable|string|max:32',
            'flag_emoji' => 'nullable|string|max:16',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'nullable|boolean',
        ]);

        $language = $this->languages->create($validated);

        return response()->json([
            'message' => 'Language added.',
            'data' => $this->languages->toAdminArray($language),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $language = PortalLanguage::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'google_translate_code' => 'nullable|string|max:32',
            'flag_emoji' => 'nullable|string|max:16',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'nullable|boolean',
        ]);

        $language = $this->languages->update($language, $validated);

        return response()->json([
            'message' => 'Language updated.',
            'data' => $this->languages->toAdminArray($language),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $language = PortalLanguage::query()->findOrFail($id);
        $this->languages->delete($language);

        return response()->json(['message' => 'Language removed.']);
    }

    public function translations(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $this->languages->seedAuLanguages();

        $locales = PortalLanguage::allLocaleCodes();
        $groups = $this->translations->groups();
        $locale = (string) $request->query('locale', $locales[0] ?? 'en');
        if (! in_array($locale, $locales, true)) {
            $locale = in_array('en', $locales, true) ? 'en' : ($locales[0] ?? 'en');
        }
        $group = (string) $request->query('group', array_key_first($groups) ?: 'nav');
        if (! array_key_exists($group, $groups)) {
            $group = array_key_first($groups) ?: 'nav';
        }

        $labels = [];
        foreach ($locales as $code) {
            $row = PortalLanguage::query()->where('locale_code', $code)->first();
            $fallback = PortalLanguage::fallbackSelectorMap()[$code] ?? null;
            $labels[$code] = [
                'name' => $row?->name ?? ($fallback['name'] ?? strtoupper($code)),
                'flag' => $row?->flag_emoji ?? ($fallback['flag'] ?? ''),
            ];
        }

        return response()->json([
            'data' => [
                'locales' => $locales,
                'locale_labels' => $labels,
                'groups' => $groups,
                'locale' => $locale,
                'group' => $group,
                'english' => $this->translations->englishGroup($group),
                'lines' => $this->translations->loadMerged($locale, $group),
            ],
        ]);
    }

    public function saveTranslations(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $groups = array_keys($this->translations->groups());
        $locales = PortalLanguage::allLocaleCodes();

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'group' => ['required', 'string', Rule::in($groups)],
            'translations' => 'required|array',
            'translations.*' => 'nullable|string|max:5000',
        ]);

        $this->translations->saveGroup(
            (string) $validated['locale'],
            (string) $validated['group'],
            $validated['translations'],
        );

        return response()->json([
            'message' => 'Translations saved.',
            'data' => [
                'locale' => $validated['locale'],
                'group' => $validated['group'],
                'lines' => $this->translations->loadMerged(
                    (string) $validated['locale'],
                    (string) $validated['group'],
                ),
            ],
        ]);
    }

    public function fillWithAi(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $groups = array_keys($this->translations->groups());
        $locales = PortalLanguage::allLocaleCodes();

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($locales)],
            'group' => ['required', 'string', Rule::in($groups)],
        ]);

        $lines = $this->aiTranslations->suggestGroup(
            (string) $validated['locale'],
            (string) $validated['group'],
        );

        return response()->json([
            'message' => 'AI suggestions filled. Review and save to keep them.',
            'data' => [
                'locale' => $validated['locale'],
                'group' => $validated['group'],
                'lines' => $lines,
            ],
        ]);
    }
}
