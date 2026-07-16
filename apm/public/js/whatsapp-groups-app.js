/**
 * WhatsApp group management — Vue 3 + Vuetify 3
 * Integrates with WhatsAppBotMultiDevice admin API via APM backend proxy.
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
                const statusLoading = ref(false);
                const groups = ref([]);
                const status = ref(null);
                const snackbar = ref({ show: false, text: '', color: 'success' });
                const membersDialog = ref(false);
                const membersLoading = ref(false);
                const selectedGroup = ref(null);
                const coverage = ref(null);
                const search = ref('');

                const config = computed(() => status.value?.config || cfg.summary || {});
                const publicStatus = computed(() => status.value?.public_status || {});
                const adminStats = computed(() => status.value?.admin_stats || null);
                const adminError = computed(() => status.value?.admin_error || null);

                const filteredGroups = computed(() => {
                    const q = search.value.trim().toLowerCase();
                    if (!q) return groups.value;
                    return groups.value.filter((g) =>
                        (g.name || '').toLowerCase().includes(q) ||
                        (g.jid || '').toLowerCase().includes(q)
                    );
                });

                const headers = [
                    { title: 'Group', key: 'name', sortable: false, minWidth: 220 },
                    { title: 'Bot active', key: 'is_bot_on', sortable: false, width: 110, align: 'center' },
                    { title: 'Messages', key: 'total_messages', sortable: false, width: 110, align: 'end' },
                    { title: 'Primary', key: 'is_primary', sortable: false, width: 100, align: 'center' },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 220 },
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

                async function openMembers(group) {
                    selectedGroup.value = group;
                    membersDialog.value = true;
                    membersLoading.value = true;
                    coverage.value = null;
                    try {
                        const res = await fetch(cfg.routes.groupsBase + '/groups/' + encodeURIComponent(group.jid) + '/members', {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Failed to load members');
                        coverage.value = json.data?.coverage || null;
                    } catch (e) {
                        notify(e.message || 'Could not load members', 'error');
                    } finally {
                        membersLoading.value = false;
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
                    search,
                    headers,
                    notify,
                    loadGroups,
                    loadStatus,
                    toggleBot,
                    setPrimary,
                    openMembers,
                    settingsUrl: cfg.routes.settings,
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
                        <v-alert v-else-if="!config.admin_password_configured" type="info" variant="tonal" class="mb-4">
                            Set the bot admin password in <a :href="settingsUrl">WhatsApp settings</a> before loading groups.
                        </v-alert>

                        <v-card>
                            <v-card-title class="d-flex flex-wrap align-center gap-3 py-4">
                                <span>WhatsApp groups</span>
                                <v-spacer />
                                <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" placeholder="Search groups" density="compact" hide-details style="max-width:260px" />
                                <v-btn color="success" variant="tonal" :loading="loading" @click="loadGroups" prepend-icon="mdi-refresh">Refresh</v-btn>
                            </v-card-title>
                            <v-data-table
                                :headers="headers"
                                :items="filteredGroups"
                                :loading="loading"
                                item-value="jid"
                                class="wg-groups-table"
                            >
                                <template #item.name="{ item }">
                                    <div class="font-weight-medium">{{ item.name }}</div>
                                    <div class="text-caption text-medium-emphasis text-truncate" style="max-width:320px">{{ item.jid }}</div>
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
                                    <v-btn size="small" variant="text" @click="openMembers(item)">Staff sync</v-btn>
                                    <v-btn size="small" variant="text" color="success" :disabled="item.is_primary" @click="setPrimary(item)">Set primary</v-btn>
                                </template>
                            </v-data-table>
                        </v-card>

                        <v-dialog v-model="membersDialog" max-width="900">
                            <v-card v-if="selectedGroup">
                                <v-card-title>{{ selectedGroup.name }} — staff coverage</v-card-title>
                                <v-card-text>
                                    <div v-if="membersLoading" class="text-center py-6">
                                        <v-progress-circular indeterminate color="success" />
                                    </div>
                                    <template v-else-if="coverage">
                                        <v-row dense class="mb-3">
                                            <v-col cols="6" md="3"><strong>In group:</strong> {{ coverage.summary.staff_in_group }}</v-col>
                                            <v-col cols="6" md="3"><strong>Missing:</strong> {{ coverage.summary.staff_missing_from_group }}</v-col>
                                            <v-col cols="6" md="3"><strong>No phone:</strong> {{ coverage.summary.staff_without_phone }}</v-col>
                                            <v-col cols="6" md="3"><strong>Unknown in WA:</strong> {{ coverage.summary.unknown_in_group }}</v-col>
                                        </v-row>
                                        <p class="text-caption text-medium-emphasis mb-3">
                                            Matches staff <code>whatsapp</code> or <code>tel_1</code> (last 9 digits) to group participants.
                                            Add missing staff with the bot <code>-add phone</code> command in the group.
                                        </p>
                                        <v-table density="compact">
                                            <thead>
                                                <tr><th>Staff</th><th>Phone</th><th>Division</th><th>Status</th></tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="row in coverage.missing_from_group" :key="'m-' + row.staff_id">
                                                    <td>{{ row.name }}</td><td>{{ row.phone || '—' }}</td><td>{{ row.division }}</td>
                                                    <td><v-chip color="warning" size="x-small">Missing from group</v-chip></td>
                                                </tr>
                                                <tr v-for="row in coverage.in_group.slice(0, 15)" :key="'i-' + row.staff_id">
                                                    <td>{{ row.name }}</td><td>{{ row.phone }}</td><td>{{ row.division }}</td>
                                                    <td><v-chip color="success" size="x-small">In group</v-chip></td>
                                                </tr>
                                            </tbody>
                                        </v-table>
                                    </template>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn variant="text" @click="membersDialog = false">Close</v-btn>
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
