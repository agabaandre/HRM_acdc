<?php

namespace App\Services\WhatsApp;

use App\Models\Staff;
use App\Models\WhatsAppGroup;
use App\Models\WhatsAppGroupMember;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use Illuminate\Support\Collection;

class WhatsAppRepository
{
    /**
     * @return array<string, mixed>
     */
    public function publicStatus(): array
    {
        $session = WhatsAppSession::current();

        return [
            'reachable' => true,
            'connected' => $session->connected,
            'registered' => $session->registered,
            'error' => $session->last_error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminStats(): array
    {
        return [
            'uptime' => 0,
            'groupCount' => WhatsAppGroup::query()->count(),
            'memberCount' => WhatsAppGroupMember::query()->count(),
            'botNumber' => app(WhatsAppConfig::class)->botNumber() ?: 'Unknown',
            'disabledGlobally' => [],
        ];
    }

    /**
     * Delete APM groups whose name does not contain the sync keyword.
     * Returns how many rows were removed.
     */
    public function pruneNonMatchingGroups(?string $keyword = null): int
    {
        $keyword = trim($keyword ?? app(WhatsAppConfig::class)->groupSyncKeyword());
        if ($keyword === '') {
            return 0;
        }

        return WhatsAppGroup::query()
            ->where(function ($query) use ($keyword) {
                $query->whereNull('name')
                    ->orWhere('name', '')
                    ->orWhereRaw('LOWER(name) NOT LIKE ?', ['%'.mb_strtolower($keyword).'%']);
            })
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        $this->pruneNonMatchingGroups();

        return WhatsAppGroup::query()
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppGroup $group) => $this->groupToApiShape($group))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateGroup(string $jid, array $fields): void
    {
        $map = [
            'isBotOn' => 'is_bot_on',
            'isChatBotOn' => 'is_chat_bot_on',
            'isImgOn' => 'is_img_on',
            'is91Only' => 'is_91_only',
            'isAutoStickerOn' => 'is_auto_sticker_on',
            'isRankNotifOn' => 'is_rank_notif_on',
            'groupType' => 'group_type',
        ];

        $updates = [];
        foreach ($map as $apiKey => $dbKey) {
            if (array_key_exists($apiKey, $fields)) {
                if ($dbKey === 'group_type') {
                    $value = (string) $fields[$apiKey];
                    $updates[$dbKey] = in_array($value, WhatsAppGroup::TYPES, true)
                        ? $value
                        : WhatsAppGroup::TYPE_STANDARD;
                } else {
                    $updates[$dbKey] = (bool) $fields[$apiKey];
                }
            }
        }

        if ($updates === []) {
            return;
        }

        WhatsAppGroup::query()->updateOrCreate(['jid' => $jid], $updates);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupMembers(string $jid): array
    {
        return WhatsAppGroupMember::query()
            ->where('group_jid', $jid)
            ->orderBy('username')
            ->get()
            ->map(fn (WhatsAppGroupMember $member) => [
                'id' => $member->member_jid,
                '_id' => $member->member_jid,
                'jid' => $member->member_jid,
                'phone' => $member->phone ?: $this->phoneFromJid($member->member_jid),
                'lid' => $member->lid,
                'username' => $member->username,
                'name' => $member->username,
                'isAdmin' => $member->is_admin,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{messages: list<array<string, mixed>>, has_more: bool}
     */
    public function groupMessages(string $jid, ?int $beforeId = null, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $query = WhatsAppMessage::query()
            ->where('group_jid', $jid)
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        if ($beforeId !== null && $beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $config = app(WhatsAppConfig::class);
        $staffByTail = $this->staffNameByPhoneTail($config);

        $messages = $rows
            ->reverse()
            ->values()
            ->map(function (WhatsAppMessage $message) use ($staffByTail, $config) {
                $phone = $config->normalizePhone((string) ($message->sender_phone ?: ''));
                if ($phone === '') {
                    $phone = $this->phoneFromJid((string) $message->sender_jid);
                }
                $tail = $config->phoneTail($phone);
                $staffName = $tail !== '' ? ($staffByTail[$tail] ?? null) : null;

                return [
                    'id' => $message->id,
                    'wa_message_id' => $message->wa_message_id,
                    'sender_jid' => $message->sender_jid,
                    'sender_phone' => $phone !== '' ? $phone : null,
                    'sender_name' => $staffName ?: $message->sender_name ?: ($phone !== '' ? $phone : 'Unknown'),
                    'staff_name' => $staffName,
                    'from_me' => $message->from_me,
                    'message_type' => $message->message_type,
                    'body' => $message->body,
                    'has_media' => filled($message->media_path),
                    'media_mime' => $message->media_mime,
                    'media_url' => $message->hasPreviewableMedia()
                        ? url('whatsapp-groups/messages/'.$message->id.'/media')
                        : null,
                    'sent_at' => optional($message->sent_at)->toIso8601String(),
                ];
            })
            ->all();

        return [
            'messages' => $messages,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Shape a live WebSocket/DB row into the API message format.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function formatLiveMessageRow(array $row): array
    {
        $message = new WhatsAppMessage([
            'id' => (int) ($row['id'] ?? 0),
            'group_jid' => (string) ($row['group_jid'] ?? ''),
            'wa_message_id' => (string) ($row['wa_message_id'] ?? ''),
            'sender_jid' => $row['sender_jid'] ?? null,
            'sender_phone' => $row['sender_phone'] ?? null,
            'sender_name' => $row['sender_name'] ?? null,
            'from_me' => (bool) ($row['from_me'] ?? false),
            'message_type' => (string) ($row['message_type'] ?? 'text'),
            'body' => $row['body'] ?? null,
            'media_path' => $row['media_path'] ?? null,
            'media_mime' => $row['media_mime'] ?? null,
            'media_size' => isset($row['media_size']) ? (int) $row['media_size'] : null,
            'sent_at' => $row['sent_at'] ?? null,
        ]);
        $message->id = (int) ($row['id'] ?? 0);

        $config = app(WhatsAppConfig::class);
        $phone = $config->normalizePhone((string) ($message->sender_phone ?: ''));
        if ($phone === '') {
            $phone = $this->phoneFromJid((string) $message->sender_jid);
        }
        $tail = $config->phoneTail($phone);
        $staffByTail = $this->staffNameByPhoneTail($config);
        $staffName = $tail !== '' ? ($staffByTail[$tail] ?? null) : null;
        $sentAt = $message->sent_at;
        if (is_string($row['sent_at'] ?? null) && $sentAt === null) {
            try {
                $sentAt = \Carbon\Carbon::parse($row['sent_at']);
            } catch (\Throwable) {
                $sentAt = null;
            }
        }

        return [
            'id' => $message->id,
            'wa_message_id' => $message->wa_message_id,
            'sender_jid' => $message->sender_jid,
            'sender_phone' => $phone !== '' ? $phone : null,
            'sender_name' => $staffName ?: $message->sender_name ?: ($phone !== '' ? $phone : 'Unknown'),
            'staff_name' => $staffName,
            'from_me' => $message->from_me,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'has_media' => filled($message->media_path),
            'media_mime' => $message->media_mime,
            'media_url' => $message->hasPreviewableMedia()
                ? url('whatsapp-groups/messages/'.$message->id.'/media')
                : null,
            'sent_at' => optional($sentAt)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function staffNameByPhoneTail(WhatsAppConfig $config): array
    {
        $map = [];
        Staff::query()
            ->active()
            ->get(['fname', 'lname', 'whatsapp', 'tel_1'])
            ->each(function (Staff $staff) use (&$map, $config) {
                $name = trim($staff->fname.' '.$staff->lname);
                foreach ([$staff->whatsapp, $staff->tel_1] as $raw) {
                    $phone = $config->normalizePhone((string) $raw);
                    $tail = $config->phoneTail($phone);
                    if ($tail !== '' && $name !== '') {
                        $map[$tail] = $name;
                    }
                }
            });

        return $map;
    }

    private function phoneFromJid(string $jid): string
    {
        if ($jid === '' || str_ends_with($jid, '@lid')) {
            return '';
        }

        return preg_replace('/\D+/', '', explode('@', $jid)[0] ?? '') ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertSession(array $payload): WhatsAppSession
    {
        $session = WhatsAppSession::current();
        $session->fill([
            'phone' => $payload['phone'] ?? $session->phone,
            'connected' => (bool) ($payload['connected'] ?? $session->connected),
            'registered' => (bool) ($payload['registered'] ?? $session->registered),
            'pairing_code' => $payload['pairing_code'] ?? $session->pairing_code,
            'last_error' => $payload['last_error'] ?? null,
            'last_connected_at' => ! empty($payload['connected']) ? now() : $session->last_connected_at,
            'last_sync_at' => $payload['last_sync_at'] ?? $session->last_sync_at,
        ]);
        $session->save();

        return $session;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    public function syncGroups(array $groups): void
    {
        $seenJids = [];

        foreach ($groups as $group) {
            $jid = (string) ($group['jid'] ?? '');
            if ($jid === '') {
                continue;
            }

            $seenJids[] = $jid;

            $model = WhatsAppGroup::query()->firstOrNew(['jid' => $jid]);
            if (! $model->exists) {
                $model->fill([
                    'is_bot_on' => false,
                    'is_chat_bot_on' => false,
                    'is_img_on' => false,
                    'is_91_only' => false,
                    'is_auto_sticker_on' => false,
                    'is_rank_notif_on' => false,
                ]);
            }

            $model->fill([
                'name' => (string) ($group['name'] ?? $jid),
                'description' => (string) ($group['description'] ?? ''),
                'synced_at' => now(),
            ]);
            $model->save();

            $members = is_array($group['members'] ?? null) ? $group['members'] : [];
            $this->syncGroupMembers($jid, $members);
        }

        if ($seenJids !== []) {
            WhatsAppGroup::query()->whereNotIn('jid', $seenJids)->delete();
        }

        WhatsAppSession::current()->update(['last_sync_at' => now()]);
    }

    /**
     * @param  list<array<string, mixed>>  $members
     */
    public function syncGroupMembers(string $groupJid, array $members): void
    {
        $seen = [];

        foreach ($members as $member) {
            $memberJid = (string) ($member['jid'] ?? $member['id'] ?? '');
            if ($memberJid === '') {
                continue;
            }

            $seen[] = $memberJid;

            WhatsAppGroupMember::query()->updateOrCreate(
                ['group_jid' => $groupJid, 'member_jid' => $memberJid],
                [
                    'username' => (string) ($member['username'] ?? $member['name'] ?? ''),
                    'is_admin' => (bool) ($member['is_admin'] ?? $member['isAdmin'] ?? false),
                    'phone' => (string) ($member['phone'] ?? ''),
                    'lid' => (string) ($member['lid'] ?? ''),
                ]
            );
        }

        WhatsAppGroupMember::query()
            ->where('group_jid', $groupJid)
            ->when($seen !== [], fn ($q) => $q->whereNotIn('member_jid', $seen))
            ->when($seen === [], fn ($q) => $q)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function groupToApiShape(WhatsAppGroup $group): array
    {
        return [
            '_id' => $group->jid,
            'id' => $group->jid,
            'grpName' => $group->name,
            'name' => $group->name,
            'desc' => $group->description,
            'groupType' => $group->group_type ?? WhatsAppGroup::TYPE_STANDARD,
            'isBotOn' => $group->is_bot_on,
            'isChatBotOn' => $group->is_chat_bot_on,
            'isImgOn' => $group->is_img_on,
            'is91Only' => $group->is_91_only,
            'isAutoStickerOn' => $group->is_auto_sticker_on,
            'isRankNotifOn' => $group->is_rank_notif_on,
            'totalMsgCount' => $group->total_msg_count,
            'members' => $group->members_count ?? $group->members()->count(),
        ];
    }
}
