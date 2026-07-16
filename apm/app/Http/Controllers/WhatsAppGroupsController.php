<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\SystemSetting;
use App\Models\WhatsAppGroup;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppChatTicket;
use App\Services\WhatsApp\WhatsAppConfig;
use App\Services\WhatsApp\WhatsAppGroupModerationService;
use App\Services\WhatsApp\WhatsAppGroupSyncService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WhatsAppGroupsController extends Controller
{
    public function __construct(
        private readonly WhatsAppConfig $config,
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppGroupSyncService $sync,
        private readonly WhatsAppGroupModerationService $moderation,
        private readonly WhatsAppChatTicket $chatTicket,
    ) {}

    public function index(): View
    {
        return view('whatsapp-groups.index', [
            'pageConfig' => [
                'csrf' => csrf_token(),
                'summary' => $this->config->publicSummary(false),
                'wsUrl' => $this->chatTicket->websocketUrl(),
                'routes' => [
                    'status' => route('whatsapp-groups.status'),
                    'groups' => route('whatsapp-groups.groups'),
                    'groupsBase' => url('whatsapp-groups'),
                    'setPrimary' => route('whatsapp-groups.set-primary'),
                    'sync' => route('whatsapp-groups.sync'),
                    'settings' => route('system-configs.index', ['tab' => 'whatsapp']),
                    'staff' => route('staff.index'),
                ],
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        $public = $this->whatsapp->publicStatus();
        $admin = null;
        $adminError = null;

        if ($this->config->isEnabled() && $this->config->isConfigured()) {
            try {
                $admin = $this->whatsapp->adminStats();
            } catch (RuntimeException $e) {
                Log::info('WhatsApp groups status admin check failed', ['error' => $e->getMessage()]);
                $adminError = 'Could not load platform statistics.';
            }
        }

        return response()->json([
            'data' => [
                'config' => $this->config->publicSummary(false),
                'public_status' => $public,
                'admin_stats' => $admin,
                'admin_error' => $adminError,
            ],
        ]);
    }

    public function groups(): JsonResponse
    {
        if (! $this->config->isEnabled()) {
            return response()->json(['message' => 'WhatsApp integration is disabled in settings.'], 422);
        }

        try {
            $groups = collect($this->whatsapp->groups())
                ->map(fn (array $g) => $this->normalizeGroup($g))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return response()->json([
                'data' => $groups,
                'keyword' => $this->config->groupSyncKeyword(),
            ]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp groups list failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not load WhatsApp groups.'], 502);
        }
    }

    public function syncGroups(): JsonResponse
    {
        if (! $this->config->isEnabled()) {
            return response()->json(['message' => 'WhatsApp integration is disabled in settings.'], 422);
        }

        try {
            $result = $this->whatsapp->syncGroups();
            WhatsAppAuditLogger::log('groups.synced', [
                'scope' => 'all',
                'source' => 'whatsapp-groups',
                'count' => $result['synced'] ?? null,
                'pruned' => $result['pruned'] ?? null,
            ]);

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp groups page sync failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage() ?: 'Group sync failed.'], 502);
        }
    }

    public function updateGroup(Request $request, string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        $validated = $request->validate([
            'isBotOn' => ['sometimes', 'boolean'],
            'isChatBotOn' => ['sometimes', 'boolean'],
            'isImgOn' => ['sometimes', 'boolean'],
            'is91Only' => ['sometimes', 'boolean'],
            'isAutoStickerOn' => ['sometimes', 'boolean'],
            'isRankNotifOn' => ['sometimes', 'boolean'],
            'groupType' => ['sometimes', 'string', 'in:'.implode(',', WhatsAppGroup::TYPES)],
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'No fields to update.'], 422);
        }

        try {
            $this->whatsapp->updateGroup($jid, $validated);
            WhatsAppAuditLogger::log('group.updated', ['jid' => $jid, 'fields' => array_keys($validated)]);

            return response()->json(['ok' => true]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp group update failed', ['jid' => $jid, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not update group settings.'], 502);
        }
    }

    public function members(string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        try {
            $refreshed = null;
            $refreshError = null;
            // Refresh from WhatsApp so LID participants resolve to phone numbers for staff matching.
            if ($this->config->usesNativeDriver() && $this->config->isConfigured()) {
                try {
                    $refreshed = $this->whatsapp->refreshGroupMembers($jid);
                } catch (RuntimeException $e) {
                    $refreshError = $e->getMessage();
                    Log::info('WhatsApp live member refresh skipped', ['jid' => $jid, 'error' => $refreshError]);
                }
            }

            $group = WhatsAppGroup::query()->find($jid);
            $members = $this->whatsapp->groupMembers($jid);
            $coverage = $this->sync->staffCoverage($members);
            $canModerate = $this->moderation->canModerateGroup($jid);
            $botIsAdmin = $this->moderation->botIsGroupAdmin($jid);

            $unknown = collect($coverage['unknown_in_group'] ?? [])
                ->map(function (array $row) use ($jid, $canModerate) {
                    $memberJid = (string) ($row['jid'] ?? '');

                    return array_merge($row, [
                        'can_remove' => $canModerate && $this->moderation->canRemoveMember($jid, $memberJid),
                    ]);
                })
                ->values()
                ->all();

            $inactive = collect($coverage['inactive_in_group'] ?? [])
                ->map(function (array $row) use ($jid, $canModerate) {
                    $memberJid = (string) ($row['member_jid'] ?? '');

                    return array_merge($row, [
                        'can_remove' => $canModerate
                            && $memberJid !== ''
                            && $this->moderation->canRemoveMember($jid, $memberJid),
                    ]);
                })
                ->values()
                ->all();

            $coverage['unknown_in_group'] = $unknown;
            $coverage['inactive_in_group'] = $inactive;

            return response()->json([
                'data' => [
                    'members' => $members,
                    'coverage' => $coverage,
                    'group_type' => $group?->group_type ?? WhatsAppGroup::TYPE_STANDARD,
                    'can_moderate' => $canModerate,
                    'bot_is_admin' => $botIsAdmin,
                    'bot_number' => $this->config->botNumber(),
                    'refresh' => $refreshed,
                    'refresh_error' => $refreshError,
                ],
            ]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp group members failed', ['jid' => $jid, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not load group members.'], 502);
        }
    }

    public function messages(Request $request, string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        if (! WhatsAppGroup::query()->where('jid', $jid)->exists()) {
            return response()->json(['message' => 'Group not found. Sync groups first.'], 404);
        }

        $validated = $request->validate([
            'before_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $payload = $this->whatsapp->groupMessages(
                $jid,
                isset($validated['before_id']) ? (int) $validated['before_id'] : null,
                (int) ($validated['limit'] ?? 50),
            );

            return response()->json([
                'data' => $payload,
            ]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp group messages failed', ['jid' => $jid, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage() ?: 'Could not load group messages.'], 502);
        }
    }

    public function chatTicket(string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        if (! WhatsAppGroup::query()->where('jid', $jid)->exists()) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $staffId = (int) user_session('staff_id');
        $ticket = $this->chatTicket->issue($jid, $staffId > 0 ? $staffId : 0);

        return response()->json([
            'data' => [
                'ticket' => $ticket,
                'ws_url' => $this->chatTicket->websocketUrl(),
                'expires_in' => 3600,
            ],
        ]);
    }

    public function sendMessage(Request $request, string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        if (! WhatsAppGroup::query()->where('jid', $jid)->exists()) {
            return response()->json(['message' => 'Group not found. Sync groups first.'], 404);
        }

        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:4000'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $text = trim((string) ($validated['text'] ?? ''));
        $caption = trim((string) ($validated['caption'] ?? ''));
        $imageBase64 = null;
        $imageMime = 'image/jpeg';

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageBase64 = base64_encode((string) file_get_contents($file->getRealPath()));
            $imageMime = $file->getMimeType() ?: 'image/jpeg';
        }

        if ($text === '' && $imageBase64 === null) {
            return response()->json(['message' => 'Enter a message or attach an image.'], 422);
        }

        try {
            $result = $this->whatsapp->sendGroupChatMessage($jid, $text, $imageBase64, $imageMime, $caption !== '' ? $caption : $text);
            WhatsAppAuditLogger::log('group.message_sent', [
                'jid' => $jid,
                'has_image' => $imageBase64 !== null,
            ]);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp send message failed', ['jid' => $jid, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not send message.',
            ], 502);
        }
    }

    public function messageMedia(int $id): BinaryFileResponse|Response
    {
        $message = WhatsAppMessage::query()->find($id);
        if ($message === null || ! $message->media_path) {
            abort(404);
        }

        $path = storage_path('app/'.$message->media_path);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $message->media_mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function removeMember(Request $request, string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        $validated = $request->validate([
            'member_jid' => ['required', 'string', 'max:120', 'regex:/^.+@.+$/'],
        ]);

        $memberJid = $validated['member_jid'];
        if (! $this->moderation->canRemoveMember($jid, $memberJid)) {
            return response()->json(['message' => 'You are not allowed to remove this participant.'], 403);
        }

        try {
            $this->whatsapp->removeGroupMember($jid, $memberJid);
            WhatsAppAuditLogger::log('group.member_removed', ['jid' => $jid, 'member_jid' => $memberJid]);

            return response()->json(['ok' => true]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp member removal failed', ['jid' => $jid, 'member_jid' => $memberJid, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not remove participant from the group.',
            ], 502);
        }
    }

    public function removeInactiveMembers(string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        if (! $this->moderation->canModerateGroup($jid)) {
            return response()->json(['message' => 'You are not allowed to remove members from this group.'], 403);
        }

        if (! $this->moderation->botIsGroupAdmin($jid)) {
            return response()->json([
                'message' => 'WhatsApp bot must be a group admin before inactive members can be removed.',
            ], 422);
        }

        $inactive = $this->moderation->inactiveMembersForRemoval($jid);
        if ($inactive === []) {
            return response()->json([
                'ok' => true,
                'data' => ['removed' => 0, 'failed' => []],
                'message' => 'No inactive-contract participants found in this group.',
            ]);
        }

        $removed = 0;
        $failed = [];
        foreach ($inactive as $row) {
            $memberJid = (string) ($row['member_jid'] ?? '');
            if ($memberJid === '') {
                continue;
            }
            try {
                $this->whatsapp->removeGroupMember($jid, $memberJid);
                $removed++;
            } catch (RuntimeException $e) {
                $failed[] = [
                    'name' => $row['name'] ?? $memberJid,
                    'member_jid' => $memberJid,
                    'error' => $e->getMessage(),
                ];
            }
        }

        WhatsAppAuditLogger::log('group.inactive_members_removed', [
            'jid' => $jid,
            'removed' => $removed,
            'failed' => count($failed),
        ]);

        return response()->json([
            'ok' => $failed === [],
            'data' => [
                'removed' => $removed,
                'failed' => $failed,
                'previewed' => count($inactive),
            ],
            'message' => $failed === []
                ? "Removed {$removed} participant(s) without an active contract."
                : "Removed {$removed}; ".count($failed).' could not be removed.',
        ], $failed === [] ? 200 : 207);
    }

    public function addMembers(Request $request, string $jid): JsonResponse
    {
        $this->assertValidGroupJid($jid);

        if (! $this->moderation->canModerateGroup($jid)) {
            return response()->json(['message' => 'You are not allowed to add members to this group.'], 403);
        }

        $validated = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1', 'max:100'],
            'staff_ids.*' => ['integer', 'min:1'],
        ]);

        $staffIds = array_values(array_unique(array_map('intval', $validated['staff_ids'])));

        $staff = Staff::query()
            ->active()
            ->whereIn('staff_id', $staffIds)
            ->get(['staff_id', 'whatsapp', 'tel_1', 'fname', 'lname']);

        if ($staff->count() !== count($staffIds)) {
            return response()->json(['message' => 'Only active staff can be added to WhatsApp groups.'], 422);
        }

        $memberJids = [];
        $skipped = [];
        foreach ($staff as $row) {
            $phone = $this->config->normalizePhone((string) ($row->whatsapp ?: $row->tel_1));
            if ($phone === '') {
                $skipped[] = trim($row->fname.' '.$row->lname);

                continue;
            }
            $memberJids[] = $phone.'@s.whatsapp.net';
        }

        if ($memberJids === []) {
            return response()->json([
                'message' => 'None of the selected staff have a WhatsApp/phone number on file.',
            ], 422);
        }

        try {
            $result = $this->whatsapp->addGroupMembers($jid, $memberJids);
            WhatsAppAuditLogger::log('group.members_added', [
                'jid' => $jid,
                'count' => count($memberJids),
                'staff_ids' => $staffIds,
            ]);

            return response()->json([
                'ok' => true,
                'data' => array_merge($result, [
                    'requested' => count($staffIds),
                    'queued' => count($memberJids),
                    'skipped_no_phone' => $skipped,
                ]),
            ]);
        } catch (RuntimeException $e) {
            Log::warning('WhatsApp member add failed', ['jid' => $jid, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Could not add members to the group.',
            ], 502);
        }
    }

    public function setPrimary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jid' => ['required', 'string', 'max:120', 'regex:/^\d+(-\d+)?@g\.us$/'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        if (! WhatsAppGroup::query()->where('jid', $validated['jid'])->exists()) {
            return response()->json(['message' => 'Group not found. Sync groups first.'], 422);
        }

        SystemSetting::set('whatsapp_primary_group_jid', $validated['jid'], 'whatsapp', 'text');
        SystemSetting::set('whatsapp_primary_group_name', $validated['name'] ?? '', 'whatsapp', 'text');

        WhatsAppAuditLogger::log('group.primary_set', ['jid' => $validated['jid']]);

        return response()->json([
            'ok' => true,
            'data' => $this->config->publicSummary(false),
        ]);
    }

    private function assertValidGroupJid(string $jid): void
    {
        if (! preg_match('/^\d+(-\d+)?@g\.us$/', $jid)) {
            abort(422, 'Invalid WhatsApp group identifier.');
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
        $groupType = (string) ($group['groupType'] ?? WhatsAppGroup::TYPE_STANDARD);

        return [
            'jid' => $jid,
            'name' => $name,
            'group_type' => in_array($groupType, WhatsAppGroup::TYPES, true)
                ? $groupType
                : WhatsAppGroup::TYPE_STANDARD,
            'is_primary' => $primaryJid !== '' && $jid === $primaryJid,
            'is_bot_on' => (bool) ($group['isBotOn'] ?? false),
            'is_chat_bot_on' => (bool) ($group['isChatBotOn'] ?? false),
            'member_count' => is_countable($group['members'] ?? null) ? count($group['members']) : null,
            'total_messages' => (int) ($group['totalMsgCount'] ?? 0),
        ];
    }
}
