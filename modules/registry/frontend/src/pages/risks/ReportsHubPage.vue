<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type ReportCard = {
  key: string
  title: string
  description: string
  path: string
}

const router = useRouter()
const locale = useLocaleStore()
const loading = ref(true)
const error = ref<string | null>(null)
const reports = ref<ReportCard[]>([])

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: ReportCard[] }>('/api/v1/reports')
    reports.value = data.data || []
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_reports_error', 'Could not load reports'))
  } finally {
    loading.value = false
  }
})

function openReport(path: string) {
  void router.push(path)
}
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.reports_title', 'Reports') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.reports_sub', 'Enterprise risk reports for leadership and assurance.') }}
        </p>
      </div>
    </header>

    <RrSkeleton v-if="loading" variant="cards" :rows="2" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <div v-else class="rr-report-list">
      <button
        v-for="report in reports"
        :key="report.key"
        type="button"
        class="rr-report-card"
        @click="openReport(report.path)"
      >
        <h2>{{ report.title }}</h2>
        <p>{{ report.description }}</p>
        <span class="rr-report-card__cta">{{ locale.t('rr.open_report', 'Open report →') }}</span>
      </button>
      <p v-if="reports.length === 0" class="rr-muted">
        {{ locale.t('rr.no_reports', 'No reports available.') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.rr-report-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}
.rr-report-card {
  text-align: left;
  border: 1px solid #d7e0ec;
  background: #fff;
  border-radius: 0.65rem;
  padding: 1.1rem 1.2rem;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.rr-report-card:hover {
  border-color: #3d6ea8;
  box-shadow: 0 4px 14px rgba(30, 58, 95, 0.08);
}
.rr-report-card h2 {
  margin: 0 0 0.45rem;
  font-size: 1.05rem;
  color: #1e3a5f;
}
.rr-report-card p {
  margin: 0;
  color: #475569;
  font-size: 0.9rem;
  line-height: 1.45;
}
.rr-report-card__cta {
  display: inline-block;
  margin-top: 0.85rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e3a8a;
}
</style>
