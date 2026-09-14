/**
 * Participant Groups management — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const cfg = window.ParticipantGroupsPageConfig;
    if (!cfg || typeof Vue === 'undefined' || typeof Vuetify === 'undefined') {
        return;
    }

    const { createApp, ref, computed, watch } = Vue;
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
                        success: '#119a48',
                        error: '#dc3545',
                        info: '#0ea5e9',
                        warning: '#f59e0b',
                        surface: '#ffffff',
                        background: '#f8fafc',
                    },
                },
            },
        },
        defaults: {
            VDialog: { scrollable: true, maxWidth: 720 },
            VCard: { rounded: 'lg', elevation: 2 },
            VBtn: { rounded: 'lg' },
            VTextField: { variant: 'outlined', density: 'comfortable' },
            VAutocomplete: { variant: 'outlined', density: 'comfortable' },
        },
    });

    createApp({
        setup() {
            const groups = ref([...(cfg.groups || [])]);
            const staffOptions = ref(cfg.staffOptions || []);
            const snackbar = ref({ show: false, text: '', color: 'success' });
            const loading = ref(false);

            const groupDialog = ref(false);
            const importDialog = ref(false);
            const deleteDialog = ref(false);
            const editId = ref(null);
            const form = ref({ name: '', description: '', staff_ids: [] });
            const importMemos = ref([]);
            const importLoading = ref(false);
            const selectedMemo = ref(null);
            const importGroupName = ref('');
            const deleteTarget = ref(null);

            const dialogTitle = computed(() =>
                editId.value ? 'Edit participant group' : 'New participant group'
            );

            const staffFilter = (value, query, item) => {
                const q = (query || '').toLowerCase();
                if (!q) return true;
                const raw = item?.raw || {};
                const hay = [
                    raw.title,
                    raw.subtitle,
                    raw.division,
                    String(raw.value),
                ].join(' ').toLowerCase();
                return hay.includes(q);
            };

            function notify(text, color = 'success') {
                snackbar.value = { show: true, text, color };
            }

            function openCreate() {
                editId.value = null;
                form.value = { name: '', description: '', staff_ids: [] };
                groupDialog.value = true;
            }

            async function openEdit(group) {
                editId.value = group.id;
                form.value = {
                    name: group.name,
                    description: group.description || '',
                    staff_ids: [],
                };
                groupDialog.value = true;
                loading.value = true;
                try {
                    const res = await fetch(`${cfg.routes.show}/${group.id}`);
                    const data = await res.json();
                    form.value.staff_ids = (data.staff_ids || []).map(Number);
                } catch (e) {
                    notify('Could not load group members.', 'error');
                } finally {
                    loading.value = false;
                }
            }

            async function saveGroup() {
                if (!form.value.name.trim()) {
                    notify('Group name is required.', 'warning');
                    return;
                }
                if (!form.value.staff_ids.length) {
                    notify('Select at least one participant.', 'warning');
                    return;
                }

                loading.value = true;
                const isEdit = !!editId.value;
                const url = isEdit
                    ? `${cfg.routes.update}/${editId.value}`
                    : cfg.routes.store;
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrf,
                        },
                        body: JSON.stringify({
                            name: form.value.name.trim(),
                            description: form.value.description.trim() || null,
                            staff_ids: form.value.staff_ids,
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Save failed');
                    }
                    groupDialog.value = false;
                    notify(isEdit ? 'Group updated.' : 'Group created.');
                    window.location.reload();
                } catch (e) {
                    notify(e.message || 'Could not save group.', 'error');
                } finally {
                    loading.value = false;
                }
            }

            function confirmDelete(group) {
                deleteTarget.value = group;
                deleteDialog.value = true;
            }

            async function deleteGroup() {
                if (!deleteTarget.value) return;
                loading.value = true;
                try {
                    const res = await fetch(
                        `${cfg.routes.destroy}/${deleteTarget.value.id}`,
                        {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                            },
                        }
                    );
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Delete failed');
                    }
                    deleteDialog.value = false;
                    notify('Group deleted.');
                    window.location.reload();
                } catch (e) {
                    notify(e.message || 'Could not delete group.', 'error');
                } finally {
                    loading.value = false;
                }
            }

            async function openImport() {
                importDialog.value = true;
                selectedMemo.value = null;
                importGroupName.value = '';
                importLoading.value = true;
                importMemos.value = [];
                try {
                    const res = await fetch(cfg.routes.memosForImport);
                    const data = await res.json();
                    importMemos.value = data.memos || [];
                } catch (e) {
                    notify('Could not load memos.', 'error');
                } finally {
                    importLoading.value = false;
                }
            }

            function pickMemo(memo) {
                selectedMemo.value = memo;
                importGroupName.value = (memo.title || 'Untitled') + ' participants';
            }

            async function saveFromMemo() {
                if (!selectedMemo.value || !importGroupName.value.trim()) {
                    notify('Select a memo and enter a group name.', 'warning');
                    return;
                }
                loading.value = true;
                try {
                    const res = await fetch(cfg.routes.storeFromMemo, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrf,
                        },
                        body: JSON.stringify({
                            memo_type: selectedMemo.value.type,
                            memo_id: selectedMemo.value.id,
                            name: importGroupName.value.trim(),
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Import failed');
                    }
                    importDialog.value = false;
                    notify('Group created from memo.');
                    window.location.reload();
                } catch (e) {
                    notify(e.message || 'Could not create group.', 'error');
                } finally {
                    loading.value = false;
                }
            }

            const tableHeaders = computed(() => {
                const h = [
                    { title: 'Name', key: 'name', sortable: true },
                    { title: 'Members', key: 'members_count', sortable: true, width: '100px' },
                    { title: 'Created by', key: 'created_by_name', sortable: true },
                ];
                if (cfg.canManage) {
                    h.push({ title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '120px' });
                }
                return h;
            });

            return {
                cfg,
                groups,
                staffOptions,
                snackbar,
                loading,
                groupDialog,
                importDialog,
                deleteDialog,
                form,
                editId,
                dialogTitle,
                importMemos,
                importLoading,
                selectedMemo,
                importGroupName,
                deleteTarget,
                tableHeaders,
                staffFilter,
                openCreate,
                openEdit,
                saveGroup,
                confirmDelete,
                deleteGroup,
                openImport,
                pickMemo,
                saveFromMemo,
            };
        },
        template: `
<v-app class="pg-vuetify-app">
  <v-container fluid class="pa-0">
    <v-row class="g-4">
      <v-col cols="12" lg="4">
        <v-card color="success" variant="tonal" class="h-100">
          <v-card-title class="d-flex align-center">
            <v-icon icon="mdi-account-group" class="me-2"></v-icon>
            Division Members
          </v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">
              Virtual group — always includes every active staff member in your division.
              Use it on activity and special memo forms for one-click participant selection.
            </p>
            <v-chip color="success" variant="flat" prepend-icon="mdi-account-multiple">
              {{ cfg.divisionStaffCount }} staff in your division
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="8">
        <v-card>
          <v-card-title class="d-flex flex-wrap align-center justify-space-between ga-2 py-4">
            <div>
              <div class="text-h6">Saved Groups</div>
              <div class="text-caption text-medium-emphasis">Reusable mixed-participant sets for your division</div>
            </div>
            <div v-if="cfg.canManage" class="d-flex ga-2">
              <v-btn variant="outlined" color="primary" prepend-icon="mdi-file-import" @click="openImport">
                From memo
              </v-btn>
              <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">
                New group
              </v-btn>
            </div>
          </v-card-title>
          <v-divider></v-divider>
          <v-data-table
            :headers="tableHeaders"
            :items="groups"
            :items-per-page="10"
            class="pg-groups-table"
            no-data-text="No saved groups yet. Create one or import from a memo."
          >
            <template #item.name="{ item }">
              <div class="font-weight-medium">{{ item.name }}</div>
              <div v-if="item.description" class="text-caption text-medium-emphasis">{{ item.description }}</div>
            </template>
            <template #item.created_by_name="{ item }">
              {{ item.created_by_name || '—' }}
            </template>
            <template #item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="small" variant="text" color="primary" @click="openEdit(item)"></v-btn>
              <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)"></v-btn>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <!-- Create / Edit dialog — centered by Vuetify -->
    <v-dialog v-model="groupDialog" max-width="720" persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span>{{ dialogTitle }}</span>
          <v-btn icon="mdi-close" variant="text" @click="groupDialog = false" :disabled="loading"></v-btn>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pt-4">
          <v-text-field
            v-model="form.name"
            label="Group name"
            placeholder="e.g. Q3 training cohort"
            maxlength="150"
            counter="150"
            :disabled="loading"
            class="mb-2"
          ></v-text-field>
          <v-text-field
            v-model="form.description"
            label="Description"
            placeholder="Optional note"
            maxlength="500"
            counter="500"
            :disabled="loading"
            class="mb-4"
          ></v-text-field>
          <v-autocomplete
            v-model="form.staff_ids"
            :items="staffOptions"
            item-title="title"
            item-value="value"
            label="Participants"
            placeholder="Search by name, job title, or division…"
            multiple
            chips
            closable-chips
            :custom-filter="staffFilter"
            :disabled="loading"
            hint="Mix staff from any division — type to search"
            persistent-hint
            prepend-inner-icon="mdi-magnify"
          >
            <template #chip="{ props, item }">
              <v-chip v-bind="props" :text="item.raw.title" size="small"></v-chip>
            </template>
            <template #item="{ props, item }">
              <v-list-item v-bind="props" :subtitle="item.raw.subtitle" :title="item.raw.title"></v-list-item>
            </template>
          </v-autocomplete>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="groupDialog = false" :disabled="loading">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="loading" @click="saveGroup">Save group</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import from memo -->
    <v-dialog v-model="importDialog" max-width="720" persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Create group from memo</span>
          <v-btn icon="mdi-close" variant="text" @click="importDialog = false" :disabled="loading"></v-btn>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pt-4">
          <p class="text-body-2 text-medium-emphasis mb-3">
            Activities and special memos in your division with at least {{ cfg.minMemoParticipants }} internal participants.
          </p>
          <v-progress-linear v-if="importLoading" indeterminate color="primary" class="mb-3"></v-progress-linear>
          <v-list v-else-if="importMemos.length" density="compact" class="pg-memo-list mb-4" max-height="280">
            <v-list-item
              v-for="memo in importMemos"
              :key="memo.type + '-' + memo.id"
              :active="selectedMemo && selectedMemo.id === memo.id && selectedMemo.type === memo.type"
              @click="pickMemo(memo)"
              rounded="lg"
              class="mb-1"
            >
              <v-list-item-title>{{ memo.title || 'Untitled' }}</v-list-item-title>
              <v-list-item-subtitle>
                {{ memo.type === 'special_memo' ? 'Special memo' : 'Activity' }}
                · {{ memo.participant_count }} participants
                · {{ memo.document_number || 'No doc #' }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
          <v-alert v-else type="info" variant="tonal" density="compact">No qualifying memos found.</v-alert>
          <v-text-field
            v-model="importGroupName"
            label="Group name"
            :disabled="!selectedMemo || loading"
            maxlength="150"
          ></v-text-field>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="importDialog = false" :disabled="loading">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="loading" :disabled="!selectedMemo" @click="saveFromMemo">
            Save group
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete confirm -->
    <v-dialog v-model="deleteDialog" max-width="440">
      <v-card>
        <v-card-title>Delete group?</v-card-title>
        <v-card-text>
          Remove <strong>{{ deleteTarget?.name }}</strong>? This cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="loading" @click="deleteGroup">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
        `,
    })
        .use(vuetify)
        .mount('#participant-groups-app');
})();
