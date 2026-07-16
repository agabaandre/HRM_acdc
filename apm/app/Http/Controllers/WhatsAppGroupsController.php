<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\WhatsApp\WhatsAppBotClient;
use App\Services\WhatsApp\WhatsAppConfig;
use App\Services\WhatsApp\WhatsAppGroupSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WhatsAppGroupsController extends Controller
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppBotClient $client,
        private readonly WhatsAppGroupSyncService $sync,
    ) {}

    public function index(): View
    {
        $this->authorizeAccess();

        return view('whatsapp-groups.index', [
            'pageConfig' => [
                'csrf' => csrf_token(),
                'summary' => $this->config->publicSummary(),
                'routes' => [
                    'status' => route('whatsapp-groups.status'),
                    'groups' => route('whatsapp-groups.groups'),
                    'groupsBase' => url('whatsapp-groups'),
                    'setPrimary' => route('whatsapp-groups.set-primary'),
                    'settings' => route('system-configs.index', ['tab' => 'whatsapp']),
                    'staff' => route('staff.index'),
                ],
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        $this->authorizeAccess();

        $public = $this->client->publicStatus();
        $admin = null;
        $adminError = null;

        if ($this->config->isEnabled() && $this->config->isConfigured()) {
            try {
                $admin = $this->client->adminStats();
            } catch (RuntimeException $e) {
                $adminError = $e->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'config' => $this->config->publicSummary(),
                'public_status' => $public,
                'admin_stats' => $admin,
                'admin_error' => $adminError,
            ],
        ]);
    }

    public function groups(): JsonResponse
    {
        $this->authorizeAccess();

        if (! $this->config->isEnabled()) {
            return response()->json(['message' => 'WhatsApp integration is disabled in settings.'], 422);
        }

        try {
            $groups = collect($this->client->groups())
                ->map(fn (array $g) => $this->normalizeGroup($g))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return response()->json(['data' => $groups]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function updateGroup(Request $request, string $jid): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'isBotOn' => ['sometimes', 'boolean'],
            'isChatBotOn' => ['sometimes', 'boolean'],
            'isImgOn' => ['sometimes', 'boolean'],
            'is91Only' => ['sometimes', 'boolean'],
            'isAutoStickerOn' => ['sometimes', 'boolean'],
            'isRankNotifOn' => ['sometimes', 'boolean'],
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'No fields to update.'], 422);
        }

        try {
            $this->client->updateGroup($jid, $validated);

            return response()->json(['ok' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function members(string $jid): JsonResponse
    {
        $this->authorizeAccess();

        try {
            $members = $this->client->groupMembers($jid);
            $coverage = $this->sync->staffCoverage($members);

            return response()->json([
                'data' => [
                    'members' => $members,
                    'coverage' => $coverage,
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function setPrimary(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'jid' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        SystemSetting::set('whatsapp_primary_group_jid', $validated['jid'], 'whatsapp', 'text');
        SystemSetting::set('whatsapp_primary_group_name', $validated['name'] ?? '', 'whatsapp', 'text');

        return response()->json([
            'ok' => true,
            'data' => $this->config->publicSummary(),
        ]);
    }

    private function authorizeAccess(): void
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to WhatsApp group management.');
        }
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function normalizeGroup(array $group): array
    {
        $jid = (string) ($group['_id'] ?? $group['id'] ?? '');
        $name = (string) ($group['grpName'] ?? $group['name'] ?? $jid);
        $primaryJid = $this->config->primaryGroupJid();

        return [
            'jid' => $jid,
            'name' => $name,
            'is_primary' => $primaryJid !== '' && $jid === $primaryJid,
            'is_bot_on' => (bool) ($group['isBotOn'] ?? false),
            'is_chat_bot_on' => (bool) ($group['isChatBotOn'] ?? false),
            'member_count' => is_countable($group['members'] ?? null) ? count($group['members']) : null,
            'total_messages' => (int) ($group['totalMsgCount'] ?? 0),
            'raw' => $group,
        ];
    }
}
