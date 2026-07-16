/**
 * WhatsApp group management — Vue 3 + Vuetify 3
 * Uses APM native platform (MySQL + worker) or external bot via backend proxy.
 */
(function () {
    'use strict';

    const MOUNT_ID = 'whatsapp-groups-app';

    function csrfHeaders(cfg) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
        if (cfg && cfg.csrf) {
            headers['X-CSRF-TOKEN'] = cfg.csrf;
        }
        return headers;
    }

    function bootWhatsAppGroups(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, onMounted } = Vue;
        const { createVuetify } = Vuetify;

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48',
                            secondary: '#64748b',
                            success: '#25D366',
                            error: '#dc3545',
                            info: '#0ea5e9',
                            warning: '#f59e0b',
                        },
                    },
                },
            },
            defaults: {
                VCard: { rounded: 'lg', elevation: 2 },
                VBtn: { rounded: 'lg' },
                VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const loading = ref(false);
                const syncing = ref(false);
                const statusLoading = ref(false);
                const groups = ref([]);
                const status = ref(null);
                const snackbar = ref({ show: false, text: '', color: 'success' });
                const membersDialog = ref(false);
                const membersLoading = ref(false);
                const chatDialog = ref(false);
                const chatLoading = ref(false);
                const chatLoadingMore = ref(false);
                const chatSending = ref(false);
                const chatMessages = ref([]);
                const chatHasMore = ref(false);
                const chatGroup = ref(null);
                const chatDraft = ref('');
                const chatImageFile = ref(null);
                const chatImagePreview = ref('');
                const chatLiveStatus = ref('offline');
                const chatFileInput = ref(null);
                let chatSocket = null;
                let chatPingTimer = null;
                const selectedGroup = ref(null);
                const coverage = ref(null);
                const canModerate = ref(false);
                const botIsAdmin = ref(false);
                const botNumber = ref('');
                const groupType = ref('standard');
                const removingMember = ref('');
                const selectedToAdd = ref([]);
                const addingMembers = ref(false);
                const inactivePreviewDialog = ref(false);
                const removingInactive = ref(false);
                const search = ref('');
                const memberSearchIn = ref('');
                const memberSearchOut = ref('');

                const groupTypeOptions = [
                    { title: 'Standard', value: 'standard' },
                    { title: 'All staff', value: 'all_staff' },
                ];

                const groupTypeLabel = (value) => {
                    const match = groupTypeOptions.find((o) => o.value === value);
                    return match ? match.title : value;
                };

                const inGroupStaff = computed(() => {
                    const active = coverage.value?.in_group || [];
                    const inactive = (coverage.value?.inactive_in_group || []).map((r) => ({
                        ...r,
                        _inactive: true,
                    }));
                    const rows = [...active, ...inactive];
                    const q = memberSearchIn.value.trim().toLowerCase();
                    if (!q) return rows;
                    return rows.filter((r) =>
                        (r.name || '').toLowerCase().includes(q) ||
                        (r.phone || '').includes(q) ||
                        (r.division || '').toLowerCase().includes(q) ||
                        (r.status || '').toLowerCase().includes(q)
                    );
                });

                const inactiveInGroup = computed(() => coverage.value?.inactive_in_group || []);

                const inactiveRemovable = computed(() =>
                    inactiveInGroup.value.filter((r) => r.can_remove && r.member_jid)
                );

                /** WhatsApp participants with a resolved phone (for display when staff match is empty/partial). */
                const groupParticipants = computed(() => {
                    const rows = (coverage.value?.participants || []).filter((p) => p.phone || p.username || p.jid);
                    const q = memberSearchIn.value.trim().toLowerCase();
                    if (!q) return rows;
                    return rows.filter((r) =>
                        (r.username || '').toLowerCase().includes(q) ||
                        (r.phone || '').includes(q) ||
                        (r.jid || '').toLowerCase().includes(q)
                    );
                });

                const notInGroupStaff = computed(() => {
                    const rows = coverage.value?.missing_from_group || [];
                    const q = memberSearchOut.value.trim().toLowerCase();
                    if (!q) return rows;
                    return rows.filter((r) =>
                        (r.name || '').toLowerCase().includes(q) ||
                        (r.phone || '').includes(q) ||
                        (r.division || '').toLowerCase().includes(q)
                    );
                });

                const selectableMissingIds = computed(() =>
                    (coverage.value?.missing_from_group || [])
                        .filter((r) => r.phone)
                        .map((r) => r.staff_id)
                );

                const allMissingSelected = computed(() => {
                    const ids = selectableMissingIds.value;
                    return ids.length > 0 && ids.every((id) => selectedToAdd.value.includes(id));
                });

                const config = computed(() => status.value?.config || cfg.summary || {});
                const publicStatus = computed(() => status.value?.public_status || {});
                const adminStats = computed(() => status.value?.admin_stats || null);
                const adminError = computed(() => status.value?.admin_error || null);

                const filteredGroups = computed(() => {
                    const q = search.value.trim().toLowerCase();
                    const list = !q
                        ? groups.value
                        : groups.value.filter((g) =>
                            (g.name || '').toLowerCase().includes(q) ||
                            (g.jid || '').toLowerCase().includes(q)
                        );
                    return list.map((g, i) => ({ ...g, row_num: i + 1 }));
                });

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56, align: 'center' },
                    { title: 'Group', key: 'name', sortable: false, minWidth: 220 },
                    { title: 'Type', key: 'group_type', sortable: false, width: 150 },
                    { title: 'Bot active', key: 'is_bot_on', sortable: false, width: 110, align: 'center' },
                    { title: 'Messages', key: 'total_messages', sortable: false, width: 110, align: 'end' },
                    { title: 'Primary', key: 'is_primary', sortable: false, width: 100, align: 'center' },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 240 },
                ];

                function notify(text, color = 'success') {
                    snackbar.value = { show: true, text, color };
                }

                async function loadStatus() {
                    statusLoading.value = true;
                    try {
                        const res = await fetch(cfg.routes.status, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        status.value = json.data;
                    } catch (e) {
                        notify('Could not load bot status.', 'error');
                    } finally {
                        statusLoading.value = false;
                    }
                }

                async function loadGroups() {
                    loading.value = true;
                    try {
                        const res = await fetch(cfg.routes.groups, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Failed to load groups');
                        groups.value = json.data || [];
                    } catch (e) {
                        notify(e.message || 'Failed to load groups', 'error');
                        groups.value = [];
                    } finally {
                        loading.value = false;
                    }
                }

                async function syncGroups() {
                    if (!cfg.routes.sync) {
                        notify('Sync is not available.', 'error');
                        return;
                    }
                    syncing.value = true;
                    try {
                        const res = await fetch(cfg.routes.sync, {
                            method: 'POST',
                            headers: csrfHeaders(cfg),
                            body: JSON.stringify({}),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Sync failed');
                        const data = json.data || {};
                        const keyword = data.keyword || config.value.group_sync_keyword || 'keyword';
                        const pruned = Number(data.pruned || 0);
                        const synced = Number(data.synced || 0);
                        let msg = `Synced ${synced} group(s) matching “${keyword}”.`;
                        if (pruned > 0) {
                            msg += ` Removed ${pruned} non-matching group(s).`;
                        } else {
                            msg += ' Non-matching groups were removed.';
                        }
                        notify(msg);
                        await loadStatus();
                        await loadGroups();
                    } catch (e) {
                        notify(e.message || 'Could not sync groups', 'error');
                    } finally {
                        syncing.value = false;
                    }
                }

                async function toggleBot(group, value) {
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(group.jid),
                            {
                                method: 'PATCH',
                                headers: csrfHeaders(cfg),
                                body: JSON.stringify({ isBotOn: value }),
                            }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Update failed');
                        group.is_bot_on = value;
                        notify(value ? 'Bot enabled for group.' : 'Bot disabled for group.');
                    } catch (e) {
                        notify(e.message || 'Could not update group', 'error');
                        await loadGroups();
                    }
                }

                async function updateGroupType(group, value) {
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(group.jid),
                            {
                                method: 'PATCH',
                                headers: csrfHeaders(cfg),
                                body: JSON.stringify({ groupType: value }),
                            }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Update failed');
                        group.group_type = value;
                        notify(value === 'all_staff'
                            ? 'Group set to all-staff — sync will add/remove participants.'
                            : 'Group type updated.');
                    } catch (e) {
                        notify(e.message || 'Could not update group type', 'error');
                        await loadGroups();
                    }
                }

                async function setPrimary(group) {
                    try {
                        const res = await fetch(cfg.routes.setPrimary, {
                            method: 'POST',
                            headers: csrfHeaders(cfg),
                            body: JSON.stringify({ jid: group.jid, name: group.name }),
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Failed to set primary group');
                        groups.value = groups.value.map((g) => ({
                            ...g,
                            is_primary: g.jid === group.jid,
                        }));
                        if (status.value && status.value.config) {
                            status.value.config.primary_group_jid = group.jid;
                            status.value.config.primary_group_name = group.name;
                        }
                        notify('Primary staff WhatsApp group updated.');
                    } catch (e) {
                        notify(e.message || 'Could not set primary group', 'error');
                    }
                }

                async function openChat(group) {
                    closeChatSocket();
                    chatGroup.value = group;
                    chatDialog.value = true;
                    chatMessages.value = [];
                    chatHasMore.value = false;
                    chatDraft.value = '';
                    clearChatImage();
                    chatLiveStatus.value = 'connecting';
                    await loadChatMessages({ reset: true });
                    await connectChatSocket();
                    scrollChatToBottom();
                }

                function closeChat() {
                    chatDialog.value = false;
                    closeChatSocket();
                    clearChatImage();
                }

                function clearChatImage() {
                    chatImageFile.value = null;
                    if (chatImagePreview.value) {
                        URL.revokeObjectURL(chatImagePreview.value);
                    }
                    chatImagePreview.value = '';
                }

                function onChatImagePicked(event) {
                    const file = event?.target?.files?.[0] || null;
                    clearChatImage();
                    if (!file) return;
                    if (!file.type.startsWith('image/')) {
                        notify('Please choose an image file.', 'warning');
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        notify('Image must be 5MB or smaller.', 'warning');
                        return;
                    }
                    chatImageFile.value = file;
                    chatImagePreview.value = URL.createObjectURL(file);
                }

                function normalizeLiveMessage(row) {
                    const id = Number(row.id || 0);
                    const sent = row.sent_at
                        ? (typeof row.sent_at === 'string' ? row.sent_at : new Date(row.sent_at).toISOString())
                        : new Date().toISOString();
                    const hasMedia = !!(row.media_path || row.media_url);
                    const mime = row.media_mime || '';
                    const previewable = hasMedia && String(mime).startsWith('image/');
                    return {
                        id,
                        wa_message_id: row.wa_message_id || null,
                        sender_jid: row.sender_jid || null,
                        sender_phone: row.sender_phone || null,
                        sender_name: row.sender_name || row.sender_phone || 'Unknown',
                        staff_name: row.staff_name || null,
                        from_me: !!(row.from_me === true || row.from_me === 1 || row.from_me === '1'),
                        message_type: row.message_type || 'text',
                        body: row.body || '',
                        has_media: hasMedia,
                        media_mime: mime || null,
                        media_url: previewable
                            ? (row.media_url || (cfg.routes.groupsBase + '/messages/' + id + '/media'))
                            : null,
                        sent_at: sent,
                    };
                }

                function upsertChatMessage(msg) {
                    if (!msg?.id) return;
                    const idx = chatMessages.value.findIndex((m) => m.id === msg.id || (msg.wa_message_id && m.wa_message_id === msg.wa_message_id));
                    if (idx >= 0) {
                        const next = [...chatMessages.value];
                        next[idx] = { ...next[idx], ...msg };
                        chatMessages.value = next;
                    } else {
                        chatMessages.value = [...chatMessages.value, msg];
                    }
                    if (chatGroup.value) {
                        chatGroup.value.total_messages = Math.max(
                            Number(chatGroup.value.total_messages || 0),
                            chatMessages.value.length
                        );
                    }
                }

                async function connectChatSocket() {
                    if (!chatGroup.value) return;
                    closeChatSocket();
                    chatLiveStatus.value = 'connecting';
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(chatGroup.value.jid) + '/chat-ticket',
                            { headers: { Accept: 'application/json' } }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Could not open live chat');
                        const ticket = json.data?.ticket;
                        const wsBase = json.data?.ws_url || cfg.wsUrl;
                        if (!ticket || !wsBase) throw new Error('Live chat is not configured');
                        const wsUrl = wsBase + (wsBase.includes('?') ? '&' : '?') + 'ticket=' + encodeURIComponent(ticket);
                        chatSocket = new WebSocket(wsUrl);
                        chatSocket.onopen = () => {
                            chatLiveStatus.value = 'live';
                            chatPingTimer = setInterval(() => {
                                if (chatSocket && chatSocket.readyState === WebSocket.OPEN) {
                                    chatSocket.send(JSON.stringify({ type: 'ping' }));
                                }
                            }, 25000);
                        };
                        chatSocket.onmessage = (event) => {
                            try {
                                const payload = JSON.parse(event.data);
                                if (payload.type === 'message' && payload.message) {
                                    const normalized = normalizeLiveMessage(payload.message);
                                    upsertChatMessage(normalized);
                                    scrollChatToBottom();
                                }
                            } catch {
                                // ignore malformed frames
                            }
                        };
                        chatSocket.onclose = () => {
                            chatLiveStatus.value = chatDialog.value ? 'offline' : 'offline';
                            closeChatSocket(false);
                        };
                        chatSocket.onerror = () => {
                            chatLiveStatus.value = 'offline';
                        };
                    } catch (e) {
                        chatLiveStatus.value = 'offline';
                        notify(e.message || 'Live updates unavailable — use Refresh.', 'warning');
                    }
                }

                function closeChatSocket(clearStatus = true) {
                    if (chatPingTimer) {
                        clearInterval(chatPingTimer);
                        chatPingTimer = null;
                    }
                    if (chatSocket) {
                        try { chatSocket.close(); } catch (_) {}
                        chatSocket = null;
                    }
                    if (clearStatus) chatLiveStatus.value = 'offline';
                }

                function scrollChatToBottom() {
                    requestAnimationFrame(() => {
                        const el = document.querySelector('#wg-chat-scroll');
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }

                async function loadChatMessages({ reset = false } = {}) {
                    if (!chatGroup.value) return;
                    if (reset) {
                        chatLoading.value = true;
                    } else {
                        chatLoadingMore.value = true;
                    }
                    try {
                        const params = new URLSearchParams({ limit: '50' });
                        if (!reset && chatMessages.value.length) {
                            params.set('before_id', String(chatMessages.value[0].id));
                        }
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(chatGroup.value.jid) + '/messages?' + params.toString(),
                            { headers: { Accept: 'application/json' } }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Failed to load messages');
                        const batch = (json.data?.messages || []).map(normalizeLiveMessage);
                        chatHasMore.value = !!json.data?.has_more;
                        if (reset) {
                            chatMessages.value = batch;
                            scrollChatToBottom();
                        } else {
                            chatMessages.value = [...batch, ...chatMessages.value];
                        }
                    } catch (e) {
                        notify(e.message || 'Could not load messages', 'error');
                    } finally {
                        chatLoading.value = false;
                        chatLoadingMore.value = false;
                    }
                }

                async function sendChatMessage() {
                    if (!chatGroup.value || chatSending.value) return;
                    const text = chatDraft.value.trim();
                    if (!text && !chatImageFile.value) {
                        notify('Enter a message or attach an image.', 'warning');
                        return;
                    }
                    chatSending.value = true;
                    try {
                        const body = new FormData();
                        if (text) body.append('text', text);
                        if (chatImageFile.value) {
                            body.append('image', chatImageFile.value);
                            if (text) body.append('caption', text);
                        }
                        const headers = { Accept: 'application/json' };
                        if (cfg.csrf) headers['X-CSRF-TOKEN'] = cfg.csrf;
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(chatGroup.value.jid) + '/messages',
                            { method: 'POST', headers, body }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Send failed');
                        chatDraft.value = '';
                        clearChatImage();
                        // Live socket should deliver the stored copy; soft-refresh if offline.
                        if (chatLiveStatus.value !== 'live') {
                            await loadChatMessages({ reset: true });
                        }
                    } catch (e) {
                        notify(e.message || 'Could not send message', 'error');
                    } finally {
                        chatSending.value = false;
                    }
                }

                function senderInitials(name) {
                    const parts = String(name || '?').trim().split(/\s+/).slice(0, 2);
                    return parts.map((p) => (p[0] || '').toUpperCase()).join('') || '?';
                }

                function formatChatTime(iso) {
                    if (!iso) return '';
                    try {
                        const d = new Date(iso);
                        return d.toLocaleString(undefined, {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        });
                    } catch {
                        return iso;
                    }
                }

                async function openMembers(group) {
                    selectedGroup.value = group;
                    membersDialog.value = true;
                    membersLoading.value = true;
                    coverage.value = null;
                    canModerate.value = false;
                    botIsAdmin.value = false;
                    botNumber.value = '';
                    selectedToAdd.value = [];
                    memberSearchIn.value = '';
                    memberSearchOut.value = '';
                    groupType.value = group.group_type || 'standard';
                    try {
                        const res = await fetch(cfg.routes.groupsBase + '/groups/' + encodeURIComponent(group.jid) + '/members', {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Failed to load members');
                        coverage.value = json.data?.coverage || null;
                        canModerate.value = !!json.data?.can_moderate;
                        botIsAdmin.value = !!json.data?.bot_is_admin;
                        botNumber.value = json.data?.bot_number || '';
                        groupType.value = json.data?.group_type || group.group_type || 'standard';
                        if (!botIsAdmin.value && canModerate.value) {
                            notify(
                                'WhatsApp bot' + (botNumber.value ? ' (' + botNumber.value + ')' : '') +
                                ' is not a group admin. Promote it to admin in WhatsApp before adding members.',
                                'warning'
                            );
                        } else if (json.data?.refresh_error) {
                            notify('Member refresh: ' + json.data.refresh_error + ' — restart the WhatsApp worker if the route is missing.', 'warning');
                        } else if (json.data?.refresh?.phones_resolved != null) {
                            const resolved = Number(json.data.refresh.phones_resolved || 0);
                            const total = Number(json.data.refresh.members_total || 0);
                            if (total > 0 && resolved === 0) {
                                notify('Refreshed group but no phones could be resolved from LIDs yet. Ensure staff phones include country codes.', 'warning');
                            }
                        }
                    } catch (e) {
                        notify(e.message || 'Could not load members', 'error');
                    } finally {
                        membersLoading.value = false;
                    }
                }

                function toggleSelectAllMissing(checked) {
                    selectedToAdd.value = checked ? [...selectableMissingIds.value] : [];
                }

                function toggleSelectMissing(staffId, checked) {
                    if (checked) {
                        if (!selectedToAdd.value.includes(staffId)) {
                            selectedToAdd.value = [...selectedToAdd.value, staffId];
                        }
                    } else {
                        selectedToAdd.value = selectedToAdd.value.filter((id) => id !== staffId);
                    }
                }

                async function addSelectedMembers() {
                    if (!selectedGroup.value || !selectedToAdd.value.length) return;
                    addingMembers.value = true;
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(selectedGroup.value.jid) + '/members/add',
                            {
                                method: 'POST',
                                headers: csrfHeaders(cfg),
                                body: JSON.stringify({ staff_ids: selectedToAdd.value }),
                            }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Add failed');
                        const queued = json.data?.queued ?? selectedToAdd.value.length;
                        const added = json.data?.added ?? queued;
                        if (json.data?.warning) {
                            notify(`Added ${added}. Some failed: ${json.data.warning}`, 'warning');
                        } else {
                            notify(`Added ${added} active staff member(s) to the group.`);
                        }
                        selectedToAdd.value = [];
                        await openMembers(selectedGroup.value);
                        await loadGroups();
                    } catch (e) {
                        notify(e.message || 'Could not add members', 'error');
                    } finally {
                        addingMembers.value = false;
                    }
                }

                async function removeUnknownMember(row) {
                    if (!selectedGroup.value || !row?.jid) return;
                    removingMember.value = row.jid;
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(selectedGroup.value.jid) + '/members/remove',
                            {
                                method: 'POST',
                                headers: csrfHeaders(cfg),
                                body: JSON.stringify({ member_jid: row.jid }),
                            }
                        );
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Remove failed');
                        notify('Participant removed from group.');
                        await openMembers(selectedGroup.value);
                        await loadGroups();
                    } catch (e) {
                        notify(e.message || 'Could not remove participant', 'error');
                    } finally {
                        removingMember.value = '';
                    }
                }

                function openInactivePreview() {
                    if (!inactiveRemovable.value.length) {
                        notify('No inactive-contract participants are available to remove.', 'info');
                        return;
                    }
                    inactivePreviewDialog.value = true;
                }

                async function confirmRemoveInactive() {
                    if (!selectedGroup.value || !inactiveRemovable.value.length) return;
                    removingInactive.value = true;
                    try {
                        const res = await fetch(
                            cfg.routes.groupsBase + '/groups/' + encodeURIComponent(selectedGroup.value.jid) + '/members/remove-inactive',
                            {
                                method: 'POST',
                                headers: csrfHeaders(cfg),
                                body: JSON.stringify({}),
                            }
                        );
                        const json = await res.json();
                        if (!res.ok && res.status !== 207) throw new Error(json.message || 'Remove failed');
                        inactivePreviewDialog.value = false;
                        notify(json.message || 'Inactive participants removed.', res.status === 207 ? 'warning' : 'success');
                        await openMembers(selectedGroup.value);
                        await loadGroups();
                    } catch (e) {
                        notify(e.message || 'Could not remove inactive participants', 'error');
                    } finally {
                        removingInactive.value = false;
                    }
                }

                onMounted(async () => {
                    await loadStatus();
                    if (config.value.enabled) {
                        await loadGroups();
                    }
                });

                return {
                    loading,
                    syncing,
                    statusLoading,
                    groups,
                    filteredGroups,
                    status,
                    config,
                    publicStatus,
                    adminStats,
                    adminError,
                    snackbar,
                    membersDialog,
                    membersLoading,
                    selectedGroup,
                    coverage,
                    canModerate,
                    botIsAdmin,
                    botNumber,
                    groupType,
                    removingMember,
                    selectedToAdd,
                    addingMembers,
                    memberSearchIn,
                    memberSearchOut,
                    inGroupStaff,
                    inactiveInGroup,
                    inactiveRemovable,
                    groupParticipants,
                    notInGroupStaff,
                    selectableMissingIds,
                    allMissingSelected,
                    groupTypeOptions,
                    groupTypeLabel,
                    search,
                    headers,
                    notify,
                    loadGroups,
                    syncGroups,
                    loadStatus,
                    toggleBot,
                    updateGroupType,
                    setPrimary,
                    openMembers,
                    openChat,
                    closeChat,
                    loadChatMessages,
                    sendChatMessage,
                    onChatImagePicked,
                    clearChatImage,
                    formatChatTime,
                    senderInitials,
                    toggleSelectAllMissing,
                    toggleSelectMissing,
                    addSelectedMembers,
                    removeUnknownMember,
                    openInactivePreview,
                    confirmRemoveInactive,
                    inactivePreviewDialog,
                    removingInactive,
                    settingsUrl: cfg.routes.settings,
                    chatDialog,
                    chatLoading,
                    chatLoadingMore,
                    chatSending,
                    chatMessages,
                    chatHasMore,
                    chatGroup,
                    chatDraft,
                    chatImagePreview,
                    chatLiveStatus,
                    chatFileInput,
                    closeChatSocket,
                };
            },
            template: `
                <v-app class="wg-vuetify-app bg-transparent">
                    <v-container fluid class="pa-0">
                        <v-row class="mb-4" dense>
                            <v-col cols="12" md="3">
                                <v-card class="wg-stat-card pa-4" style="--wg-accent:#25D366">
                                    <div class="text-caption text-medium-emphasis">Bot connection</div>
                                    <div class="text-h6 font-weight-bold mt-1">
                                        {{ publicStatus.connected ? 'Connected' : (publicStatus.reachable ? 'Offline' : 'Unreachable') }}
                                    </div>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-card class="wg-stat-card pa-4" style="--wg-accent:#119a48">
                                    <div class="text-caption text-medium-emphasis">Configured number</div>
                                    <div class="text-h6 font-weight-bold mt-1">{{ config.bot_number || '—' }}</div>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-card class="wg-stat-card pa-4" style="--wg-accent:#0ea5e9">
                                    <div class="text-caption text-medium-emphasis">Primary group</div>
                                    <div class="text-body-1 font-weight-bold mt-1 text-truncate">
                                        {{ config.primary_group_name || 'Not set' }}
                                    </div>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-card class="wg-stat-card pa-4" style="--wg-accent:#64748b">
                                    <div class="text-caption text-medium-emphasis">Groups (bot DB)</div>
                                    <div class="text-h6 font-weight-bold mt-1">{{ adminStats?.groupCount ?? '—' }}</div>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-alert v-if="!config.enabled" type="warning" variant="tonal" class="mb-4">
                            WhatsApp integration is disabled.
                            <a :href="settingsUrl">Enable it in System configs → WhatsApp</a>.
                        </v-alert>
                        <v-alert v-else-if="adminError" type="error" variant="tonal" class="mb-4">{{ adminError }}</v-alert>
                        <v-alert v-else-if="!config.admin_password_configured && !config.uses_native" type="info" variant="tonal" class="mb-4">
                            Set the bot admin password in <a :href="settingsUrl">WhatsApp settings</a> before loading groups.
                        </v-alert>
                        <v-alert v-else-if="config.group_sync_keyword" type="info" variant="tonal" class="mb-4" density="compact">
                            Only groups whose name contains <strong>{{ config.group_sync_keyword }}</strong> are kept.
                            Sync deletes all other groups from APM by default.
                            Set type to <strong>All staff</strong> to auto add/remove participants on sync.
                        </v-alert>

                        <v-card>
                            <v-card-title class="d-flex flex-wrap align-center gap-3 py-4">
                                <span>WhatsApp groups</span>
                                <v-spacer />
                                <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" placeholder="Search groups" density="compact" hide-details style="max-width:260px" />
                                <v-btn color="primary" variant="flat" :loading="syncing" :disabled="loading" @click="syncGroups" prepend-icon="mdi-cloud-sync">
                                    Sync
                                </v-btn>
                                <v-btn color="success" variant="tonal" :loading="loading" :disabled="syncing" @click="loadGroups" prepend-icon="mdi-refresh">Refresh</v-btn>
                            </v-card-title>
                            <v-data-table
                                :headers="headers"
                                :items="filteredGroups"
                                :loading="loading"
                                item-value="jid"
                                class="wg-groups-table"
                            >
                                <template #item.row_num="{ item }">
                                    <span class="text-medium-emphasis">{{ item.row_num }}</span>
                                </template>
                                <template #item.name="{ item }">
                                    <div class="font-weight-medium">{{ item.name }}</div>
                                    <div class="text-caption text-medium-emphasis text-truncate" style="max-width:320px">{{ item.jid }}</div>
                                </template>
                                <template #item.group_type="{ item }">
                                    <v-select
                                        :model-value="item.group_type || 'standard'"
                                        :items="groupTypeOptions"
                                        item-title="title"
                                        item-value="value"
                                        density="compact"
                                        hide-details
                                        variant="outlined"
                                        style="max-width:140px"
                                        @update:model-value="updateGroupType(item, $event)"
                                    />
                                </template>
                                <template #item.is_bot_on="{ item }">
                                    <v-switch
                                        :model-value="item.is_bot_on"
                                        color="success"
                                        density="compact"
                                        hide-details
                                        @update:model-value="toggleBot(item, $event)"
                                    />
                                </template>
                                <template #item.is_primary="{ item }">
                                    <v-chip v-if="item.is_primary" color="success" size="small" variant="flat">Primary</v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <v-btn size="small" variant="text" color="info" @click="openChat(item)">Chat</v-btn>
                                    <v-btn size="small" variant="text" @click="openMembers(item)">Manage members</v-btn>
                                    <v-btn size="small" variant="text" color="success" :disabled="item.is_primary" @click="setPrimary(item)">Set primary</v-btn>
                                </template>
                            </v-data-table>
                        </v-card>

                        <v-dialog v-model="chatDialog" max-width="960" width="92vw" persistent scrollable @after-leave="closeChatSocket">
                            <v-card v-if="chatGroup" class="wg-chat-card d-flex flex-column" style="height:min(82vh,820px)">
                                <v-card-title class="wg-chat-header d-flex align-center gap-3 py-3 px-4">
                                    <div class="wg-chat-avatar">{{ senderInitials(chatGroup.name) }}</div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="text-subtitle-1 font-weight-bold text-truncate">{{ chatGroup.name }}</div>
                                        <div class="text-caption text-medium-emphasis text-truncate">{{ chatGroup.jid }}</div>
                                    </div>
                                    <v-chip
                                        size="small"
                                        :color="chatLiveStatus === 'live' ? 'success' : (chatLiveStatus === 'connecting' ? 'warning' : 'default')"
                                        variant="flat"
                                    >
                                        {{ chatLiveStatus === 'live' ? 'Live' : (chatLiveStatus === 'connecting' ? 'Connecting…' : 'Offline') }}
                                    </v-chip>
                                    <v-btn icon variant="text" size="small" @click="closeChat">
                                        <v-icon>mdi-close</v-icon>
                                    </v-btn>
                                </v-card-title>

                                <div class="wg-chat-body flex-grow-1 d-flex flex-column" style="min-height:0">
                                    <div v-if="chatLoading" class="text-center py-10">
                                        <v-progress-circular indeterminate color="success" />
                                    </div>
                                    <template v-else>
                                        <div class="text-center py-2" v-if="chatHasMore">
                                            <v-btn size="small" variant="text" :loading="chatLoadingMore" @click="loadChatMessages({ reset: false })">
                                                Load earlier messages
                                            </v-btn>
                                        </div>
                                        <div id="wg-chat-scroll" class="wg-chat-scroll flex-grow-1 px-4 pb-3">
                                            <div v-if="!chatMessages.length" class="wg-chat-empty text-center text-medium-emphasis py-12">
                                                <div class="text-h6 mb-1">No messages yet</div>
                                                <div class="text-body-2">Send a message below, or wait for new group activity while the worker is connected.</div>
                                            </div>
                                            <div
                                                v-for="msg in chatMessages"
                                                :key="msg.id"
                                                class="wg-chat-row d-flex mb-3"
                                                :class="{ 'justify-end': msg.from_me }"
                                            >
                                                <div v-if="!msg.from_me" class="wg-chat-avatar wg-chat-avatar-sm me-2 mt-1">{{ senderInitials(msg.sender_name) }}</div>
                                                <div class="wg-bubble" :class="msg.from_me ? 'wg-bubble-out' : 'wg-bubble-in'">
                                                    <div class="d-flex align-center justify-space-between gap-3 mb-1">
                                                        <span class="text-caption font-weight-bold">{{ msg.from_me ? 'You (bot)' : msg.sender_name }}</span>
                                                        <span class="text-caption wg-chat-time">{{ formatChatTime(msg.sent_at) }}</span>
                                                    </div>
                                                    <a
                                                        v-if="msg.media_url"
                                                        :href="msg.media_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="wg-chat-image-link d-block mb-2"
                                                    >
                                                        <img :src="msg.media_url" alt="Shared image" class="wg-chat-image" />
                                                    </a>
                                                    <div
                                                        v-if="msg.body"
                                                        class="text-body-2"
                                                        style="white-space:pre-wrap; word-break:break-word"
                                                    >{{ msg.body }}</div>
                                                    <div
                                                        v-else-if="!msg.media_url"
                                                        class="text-body-2 text-medium-emphasis"
                                                    >{{ msg.message_type === 'image' ? '[image]' : '—' }}</div>
                                                    <div v-if="msg.sender_phone && !msg.from_me" class="text-caption text-medium-emphasis mt-1">{{ msg.sender_phone }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="wg-chat-composer pa-3">
                                    <div v-if="chatImagePreview" class="wg-chat-attach-preview mb-2 d-flex align-center gap-2">
                                        <img :src="chatImagePreview" alt="Selected" class="wg-chat-attach-thumb" />
                                        <span class="text-caption flex-grow-1">Image ready to send</span>
                                        <v-btn size="x-small" variant="text" @click="clearChatImage">Remove</v-btn>
                                    </div>
                                    <div class="d-flex align-end gap-2">
                                        <v-btn
                                            icon
                                            variant="tonal"
                                            color="secondary"
                                            class="flex-shrink-0"
                                            @click="chatFileInput && chatFileInput.click()"
                                        >
                                            <v-icon>mdi-image-plus</v-icon>
                                        </v-btn>
                                        <input
                                            ref="chatFileInput"
                                            type="file"
                                            accept="image/*"
                                            class="d-none"
                                            @change="onChatImagePicked"
                                        />
                                        <v-textarea
                                            v-model="chatDraft"
                                            rows="1"
                                            auto-grow
                                            max-rows="4"
                                            hide-details
                                            placeholder="Type a message to share with the group…"
                                            density="comfortable"
                                            class="flex-grow-1"
                                            @keydown.enter.exact.prevent="sendChatMessage"
                                        />
                                        <v-btn
                                            color="success"
                                            size="large"
                                            class="flex-shrink-0"
                                            :loading="chatSending"
                                            :disabled="chatSending"
                                            @click="sendChatMessage"
                                        >
                                            <v-icon start>mdi-send</v-icon>
                                            Send
                                        </v-btn>
                                    </div>
                                    <div class="text-caption text-medium-emphasis mt-2">
                                        Messages are sent as the linked WhatsApp bot. Live updates use a secure WebSocket while this dialog is open.
                                    </div>
                                </div>
                            </v-card>
                        </v-dialog>

                        <v-dialog v-model="membersDialog" max-width="1100" scrollable>
                            <v-card v-if="selectedGroup">
                                <v-card-title class="d-flex flex-wrap align-center gap-2">
                                    <span>{{ selectedGroup.name }} — manage members</span>
                                    <v-chip size="small" :color="groupType === 'all_staff' ? 'success' : 'default'" variant="tonal">
                                        {{ groupTypeLabel(groupType) }}
                                    </v-chip>
                                </v-card-title>
                                <v-card-text>
                                    <div v-if="membersLoading" class="text-center py-6">
                                        <v-progress-circular indeterminate color="success" />
                                    </div>
                                    <template v-else-if="coverage">
                                        <v-row dense class="mb-3">
                                            <v-col cols="6" md="3"><strong>Active in group:</strong> {{ coverage.summary.staff_in_group }}</v-col>
                                            <v-col cols="6" md="3"><strong>Not in group:</strong> {{ coverage.summary.staff_missing_from_group }}</v-col>
                                            <v-col cols="6" md="3"><strong>Inactive contracts:</strong> {{ coverage.summary.inactive_in_group || 0 }}</v-col>
                                            <v-col cols="6" md="3"><strong>Unmatched:</strong> {{ coverage.summary.unknown_in_group }}</v-col>
                                        </v-row>
                                        <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                                            Review who is currently in this WhatsApp group versus active staff records.
                                            Participants without an active contract are highlighted so they can be reviewed and removed.
                                        </v-alert>
                                        <v-alert
                                            v-if="inactiveInGroup.length"
                                            type="warning"
                                            variant="tonal"
                                            density="compact"
                                            class="mb-4"
                                        >
                                            <div class="d-flex flex-wrap align-center gap-2">
                                                <span>
                                                    {{ inactiveInGroup.length }} participant(s) in this group do not have an active contract
                                                    (Expired, Separated, or otherwise inactive).
                                                </span>
                                                <v-spacer />
                                                <v-btn
                                                    v-if="canModerate"
                                                    size="small"
                                                    color="warning"
                                                    variant="flat"
                                                    :disabled="!botIsAdmin || !inactiveRemovable.length"
                                                    @click="openInactivePreview"
                                                >
                                                    Preview &amp; remove all
                                                </v-btn>
                                            </div>
                                        </v-alert>
                                        <v-alert
                                            v-if="canModerate && !botIsAdmin"
                                            type="warning"
                                            variant="tonal"
                                            density="compact"
                                            class="mb-4"
                                        >
                                            The WhatsApp bot{{ botNumber ? ' (' + botNumber + ')' : '' }} is a member of this group but
                                            <strong>not a group admin</strong>. Promote it to admin in the WhatsApp app, then reopen this dialog to add or remove members.
                                        </v-alert>

                                        <v-row>
                                            <v-col cols="12" md="6">
                                                <div class="d-flex align-center mb-2 gap-2">
                                                    <h6 class="text-subtitle-1 mb-0">In the group</h6>
                                                    <v-spacer />
                                                    <v-text-field v-model="memberSearchIn" density="compact" hide-details placeholder="Search…" style="max-width:160px" prepend-inner-icon="mdi-magnify" />
                                                </div>
                                                <v-sheet border rounded class="pa-0" style="max-height:420px; overflow:auto">
                                                    <v-table density="compact">
                                                        <thead>
                                                            <tr><th>#</th><th>Staff / participant</th><th>Phone</th><th>Division</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="(row, idx) in inGroupStaff"
                                                                :key="'in-' + (row.staff_id || row.member_jid || idx)"
                                                                :style="(row._inactive || row.contract_active === false) ? { background: 'rgba(245, 158, 11, 0.12)' } : null"
                                                            >
                                                                <td>{{ idx + 1 }}</td>
                                                                <td>
                                                                    {{ row.name }}
                                                                    <v-chip
                                                                        v-if="row._inactive || row.contract_active === false"
                                                                        size="x-small"
                                                                        class="ms-1"
                                                                        color="warning"
                                                                        variant="flat"
                                                                    >No active contract</v-chip>
                                                                    <span
                                                                        v-if="row.status && (row._inactive || row.contract_active === false)"
                                                                        class="text-caption text-medium-emphasis ms-1"
                                                                    >({{ row.status }})</span>
                                                                </td>
                                                                <td>{{ row.phone }}</td>
                                                                <td class="text-caption">{{ row.division }}</td>
                                                            </tr>
                                                            <template v-if="!inGroupStaff.length">
                                                                <tr v-for="(row, idx) in groupParticipants" :key="'p-' + (row.jid || idx)">
                                                                    <td>{{ idx + 1 }}</td>
                                                                    <td>
                                                                        {{ row.username || 'WhatsApp participant' }}
                                                                        <v-chip v-if="row.is_lid && !row.phone" size="x-small" class="ms-1" color="warning" variant="tonal">unresolved</v-chip>
                                                                    </td>
                                                                    <td>{{ row.phone || '—' }}</td>
                                                                    <td class="text-caption text-medium-emphasis">Not matched to staff</td>
                                                                </tr>
                                                                <tr v-if="!groupParticipants.length">
                                                                    <td colspan="4" class="text-medium-emphasis">No participants found. Sync the group first.</td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </v-table>
                                                </v-sheet>
                                                <p v-if="inGroupStaff.length" class="text-caption text-medium-emphasis mt-2 mb-0">
                                                    {{ coverage.summary.staff_in_group }} active staff
                                                    <span v-if="coverage.summary.inactive_in_group">
                                                        · {{ coverage.summary.inactive_in_group }} without active contract
                                                    </span>
                                                    <span v-if="coverage.summary.group_participants">
                                                        · {{ coverage.summary.group_participants }} WhatsApp participants
                                                    </span>
                                                </p>
                                            </v-col>

                                            <v-col cols="12" md="6">
                                                <div class="d-flex align-center mb-2 gap-2 flex-wrap">
                                                    <h6 class="text-subtitle-1 mb-0">Not in the group</h6>
                                                    <v-spacer />
                                                    <v-text-field v-model="memberSearchOut" density="compact" hide-details placeholder="Search…" style="max-width:160px" prepend-inner-icon="mdi-magnify" />
                                                </div>
                                                <div v-if="canModerate" class="d-flex align-center gap-2 mb-2 flex-wrap">
                                                    <v-checkbox
                                                        :model-value="allMissingSelected"
                                                        density="compact"
                                                        hide-details
                                                        label="Select all with phone"
                                                        :disabled="!botIsAdmin"
                                                        @update:model-value="toggleSelectAllMissing"
                                                    />
                                                    <v-btn
                                                        color="success"
                                                        size="small"
                                                        :disabled="!selectedToAdd.length || !botIsAdmin"
                                                        :loading="addingMembers"
                                                        @click="addSelectedMembers"
                                                    >
                                                        Add selected ({{ selectedToAdd.length }})
                                                    </v-btn>
                                                </div>
                                                <v-sheet border rounded class="pa-0" style="max-height:420px; overflow:auto">
                                                    <v-table density="compact">
                                                        <thead>
                                                            <tr>
                                                                <th v-if="canModerate" style="width:40px"></th>
                                                                <th>#</th>
                                                                <th>Staff</th>
                                                                <th>Phone</th>
                                                                <th>Division</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(row, idx) in notInGroupStaff" :key="'out-' + row.staff_id">
                                                                <td v-if="canModerate">
                                                                    <v-checkbox
                                                                        v-if="row.phone"
                                                                        :model-value="selectedToAdd.includes(row.staff_id)"
                                                                        density="compact"
                                                                        hide-details
                                                                        @update:model-value="toggleSelectMissing(row.staff_id, $event)"
                                                                    />
                                                                </td>
                                                                <td>{{ idx + 1 }}</td>
                                                                <td>{{ row.name }}</td>
                                                                <td>{{ row.phone || '—' }}</td>
                                                                <td class="text-caption">{{ row.division }}</td>
                                                            </tr>
                                                            <tr v-if="!notInGroupStaff.length">
                                                                <td :colspan="canModerate ? 5 : 4" class="text-medium-emphasis">All active staff with phones are already in this group.</td>
                                                            </tr>
                                                        </tbody>
                                                    </v-table>
                                                </v-sheet>
                                                <p v-if="coverage.summary.staff_without_phone" class="text-caption text-warning mt-2 mb-0">
                                                    {{ coverage.summary.staff_without_phone }} active staff have no phone on file and cannot be added.
                                                </p>
                                            </v-col>
                                        </v-row>

                                        <div v-if="coverage.unknown_in_group?.length" class="mt-4">
                                            <h6 class="text-subtitle-2 mb-2">WhatsApp participants not in staff list</h6>
                                            <v-table density="compact">
                                                <thead>
                                                    <tr><th>#</th><th>Name / phone</th><th>JID</th><th v-if="canModerate"></th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(row, idx) in coverage.unknown_in_group" :key="'u-' + row.jid">
                                                        <td>{{ idx + 1 }}</td>
                                                        <td>{{ row.username || row.phone || '—' }}</td>
                                                        <td class="text-caption">{{ row.jid }}</td>
                                                        <td v-if="canModerate">
                                                            <v-btn
                                                                v-if="row.can_remove && botIsAdmin"
                                                                size="x-small"
                                                                color="error"
                                                                variant="tonal"
                                                                :loading="removingMember === row.jid"
                                                                @click="removeUnknownMember(row)"
                                                            >Remove</v-btn>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </v-table>
                                        </div>
                                    </template>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn
                                        v-if="canModerate && selectedToAdd.length && botIsAdmin"
                                        color="success"
                                        :loading="addingMembers"
                                        @click="addSelectedMembers"
                                    >
                                        Add selected to group
                                    </v-btn>
                                    <v-btn variant="text" @click="membersDialog = false">Close</v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-dialog>

                        <v-dialog v-model="inactivePreviewDialog" max-width="640" scrollable>
                            <v-card>
                                <v-card-title>Remove inactive contracts</v-card-title>
                                <v-card-text>
                                    <p class="mb-3">
                                        The following WhatsApp participants are linked to staff without an active contract.
                                        Confirming will remove them from this group.
                                    </p>
                                    <v-table density="compact">
                                        <thead>
                                            <tr><th>#</th><th>Name</th><th>Status</th><th>Phone</th><th>Division</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, idx) in inactiveRemovable" :key="'ir-' + row.staff_id">
                                                <td>{{ idx + 1 }}</td>
                                                <td>{{ row.name }}</td>
                                                <td><v-chip size="x-small" color="warning" variant="tonal">{{ row.status || 'Inactive' }}</v-chip></td>
                                                <td>{{ row.phone }}</td>
                                                <td class="text-caption">{{ row.division || '—' }}</td>
                                            </tr>
                                        </tbody>
                                    </v-table>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn variant="text" :disabled="removingInactive" @click="inactivePreviewDialog = false">Cancel</v-btn>
                                    <v-btn color="warning" variant="flat" :loading="removingInactive" @click="confirmRemoveInactive">
                                        Remove all ({{ inactiveRemovable.length }})
                                    </v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-dialog>

                        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">{{ snackbar.text }}</v-snackbar>
                    </v-container>
                </v-app>
            `,
        });

        app.use(vuetify);
        app.mount(mountEl);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
    }

    if (typeof window.ApmVuetifyPage !== 'undefined') {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootWhatsAppGroups);
    }
})();
