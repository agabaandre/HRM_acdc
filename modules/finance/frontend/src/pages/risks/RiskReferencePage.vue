<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRiskLookups } from '@/composables/useRiskLookups'
import type { RiskLookups } from '@/lib/riskApi'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()
const lookups = ref<RiskLookups | null>(null)
const error = ref<string | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    lookups.value = await loadLookups()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_reference_error', 'Could not load reference data'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.reference_title', 'Reference & methodology') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.reference_sub', 'How Africa CDC scores inherent and residual risk. Use these scales when capturing or reviewing register entries.') }}
        </p>
      </div>
    </header>

    <section class="rr-card">
      <h2>{{ locale.t('rr.residual_formula', 'Residual risk formula') }}</h2>
      <ul>
        <li>{{ locale.t('rr.formula_1', 'Inherent score = Likelihood × Impact (1–25)') }}</li>
        <li>{{ locale.t('rr.formula_2', 'When mitigation effectiveness is assessed: Residual L = max(1, L − reduction); Residual I = max(1, I − ⌊reduction / 2⌋)') }}</li>
        <li>{{ locale.t('rr.formula_3', 'Until effectiveness is assessed (Not Assessed), residual equals inherent') }}</li>
        <li>{{ locale.t('rr.formula_4', 'Bands follow the published rating-key version (see Settings). Defaults: Low 1–4 · Medium 5–9 · High 10–15 · Critical 16–25') }}</li>
      </ul>
    </section>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <RrSkeleton v-if="loading" variant="cards" :rows="4" />
    <template v-else-if="lookups">
      <div class="rr-card-grid rr-card-grid--2">
        <section class="rr-card">
          <h2>{{ locale.t('rr.likelihood_heading', 'Likelihood') }}</h2>
          <table class="rr-table"><thead><tr><th>{{ locale.t('rr.label', 'Label') }}</th><th>{{ locale.t('rr.col_score', 'Score') }}</th></tr></thead>
            <tbody><tr v-for="r in lookups.likelihoods" :key="r.id"><td>{{ r.label }}</td><td>{{ r.score }}</td></tr></tbody>
          </table>
        </section>
        <section class="rr-card">
          <h2>{{ locale.t('rr.impact_heading', 'Impact') }}</h2>
          <table class="rr-table"><thead><tr><th>{{ locale.t('rr.label', 'Label') }}</th><th>{{ locale.t('rr.col_score', 'Score') }}</th></tr></thead>
            <tbody><tr v-for="r in lookups.impacts" :key="r.id"><td>{{ r.label }}</td><td>{{ r.score }}</td></tr></tbody>
          </table>
        </section>
      </div>
      <section class="rr-card">
        <h2>{{ locale.t('rr.mitigation_eff_heading', 'Mitigation effectiveness') }}</h2>
        <table class="rr-table"><thead><tr><th>{{ locale.t('rr.col_name', 'Name') }}</th><th>{{ locale.t('rr.col_l_reduction', 'L reduction') }}</th><th>{{ locale.t('rr.col_assessed', 'Assessed') }}</th></tr></thead>
          <tbody>
            <tr v-for="r in lookups.mitigation_effectiveness" :key="r.id">
              <td>{{ r.name }}</td>
              <td>{{ r.likelihood_reduction }}</td>
              <td>{{ r.is_assessed ? locale.t('rr.yes', 'Yes') : locale.t('rr.no', 'No') }}</td>
            </tr>
          </tbody>
        </table>
      </section>
      <section class="rr-card">
        <h2>{{ locale.t('rr.rating_bands_heading', 'Rating bands') }}</h2>
        <p class="rr-muted">{{ locale.t('rr.rating_bands_note', 'Manage themes, risk types, and statuses under Risk settings. Band colours follow the active rating-key version.') }}</p>
        <table class="rr-table">
          <thead><tr><th>{{ locale.t('rr.key', 'Key') }}</th><th>{{ locale.t('rr.label', 'Label') }}</th><th>{{ locale.t('rr.min', 'Min') }}</th><th>{{ locale.t('rr.max', 'Max') }}</th><th>{{ locale.t('rr.col_colour', 'Colour') }}</th></tr></thead>
          <tbody>
            <tr v-for="(r, idx) in lookups.rating_bands" :key="r.band_key || idx">
              <td>{{ r.band_key }}</td>
              <td>{{ r.rating }}</td>
              <td>{{ r.min_score }}</td>
              <td>{{ r.max_score }}</td>
              <td>
                <span
                  class="rr-band-swatch"
                  :style="{ background: r.fill_color || '#94A3B8', color: r.text_color || '#0F172A' }"
                >{{ r.rating }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-band-swatch {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  font-weight: 600;
  font-size: 0.85rem;
}
</style>
