<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import RagChip from '@/components/devtrack/RagChip.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import AppDataTable from '@/components/shared/AppDataTable.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate } from '@/lib/formatDate'
import { type ProjectSummary } from '@/lib/devtrack'

const items = ref<ProjectSummary[]>([])
const loading = ref(true)
const restoringId = ref<number | null>(null)
const error = ref('')
const message = ref('')
const q = ref('')
const router = useRouter()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/projects/archived', {
      params: { q: q.value || undefined },
    })
    items.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load archived projects.')
  } finally {
    loading.value = false
  }
}

async function restore(item: ProjectSummary) {
  restoringId.value = item.id
  error.value = ''
  message.value = ''
  try {
    await api.post(`/admin/projects/${item.id}/unarchive`)
    message.value = `${item.code} restored to the project register.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not restore the project.')
  } finally {
    restoringId.value = null
  }
}

function goToProject(item: ProjectSummary) {
  router.push({ name: 'project-detail', params: { id: item.id } })
}

function onProjectRow(_event: unknown, row: { item: ProjectSummary }) {
  goToProject(row.item)
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Archived Projects"
      subtitle="Projects archived by a super admin, with the reason recorded"
    >
      <template #actions>
        <v-btn variant="tonal" :to="{ name: 'projects' }">Back to projects</v-btn>
      </template>
    </PageHeader>

    <ParentCard title="Archive">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
      <v-row class="mb-2">
        <v-col cols="12" md="8">
          <v-text-field
            v-model="q"
            label="Search code, name, or reason"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            @keyup.enter="load"
            @click:clear="load"
          />
        </v-col>
        <v-col cols="12" md="4" class="d-flex align-center">
          <v-btn variant="tonal" @click="load">Filter</v-btn>
        </v-col>
      </v-row>
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code', width: 130 },
          { title: 'Project', key: 'name' },
          { title: 'Status', key: 'status_label', width: 130 },
          { title: 'RAG', key: 'rag_status', width: 100 },
          { title: 'Archived', key: 'archived_at', width: 130 },
          { title: 'Archived by', key: 'archived_by_user', width: 160 },
          { title: 'Reason', key: 'archive_reason' },
          { title: 'Actions', key: 'actions', sortable: false, width: 140 },
        ]"
        item-value="id"
        hover
        @click:row="onProjectRow"
      >
        <template #item.status_label="{ item }">
          <v-chip size="small" variant="tonal" color="secondary">{{ item.status_label || '—' }}</v-chip>
        </template>
        <template #item.rag_status="{ item }">
          <RagChip :status="item.rag_status" :label="item.rag_status" />
        </template>
        <template #item.archived_at="{ item }">{{ formatDate(item.archived_at) }}</template>
        <template #item.archived_by_user="{ item }">{{ item.archived_by_user?.name || '—' }}</template>
        <template #item.archive_reason="{ item }">
          <span class="text-body-2">{{ item.archive_reason || '—' }}</span>
        </template>
        <template #item.actions="{ item }">
          <v-btn
            size="small"
            color="primary"
            variant="tonal"
            :loading="restoringId === item.id"
            @click.stop="restore(item)"
          >
            Restore
          </v-btn>
        </template>
        <template #no-data>
          <div class="text-body-2 text-medium-emphasis pa-4">
            No archived projects yet.
          </div>
        </template>
      </AppDataTable>
    </ParentCard>
  </div>
</template>
