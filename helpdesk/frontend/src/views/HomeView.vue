<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { useAppStore } from '../stores/app'
import { useAuthStore } from '../stores/auth'
import { staffPortalHomeUrl } from '../lib/sso'

const app = useAppStore()
const auth = useAuthStore()

const portalHref = computed(() => staffPortalHomeUrl())

const profileSap = computed(() => String(auth.me?.profile?.sap_no ?? '').trim())

onMounted(() => {
  app.fetchHealth()
  if (auth.isAuthenticated && !auth.me) {
    void auth.fetchMe().catch(() => {})
  }
})
</script>

<template>
  <div>
    <CbpBadgeStrip product="ITSM" />
    <CbpPageHeading title="IT Service Desk">
      <template #lede>
        Log and track incidents and requests for Africa CDC. You arrive here from the
        <strong>Staff portal home</strong> — the same secure session hand-off as Finance and APM.
      </template>
    </CbpPageHeading>

    <div v-if="!auth.isAuthenticated" class="cbp-card gate">
      <p class="gate-title">No active session in this app</p>
      <p class="gate-text">
        Open the Staff portal, sign in there, then choose <strong>IT Service Desk (Helpdesk)</strong> from your home dashboard. Your browser will receive a
        one-time token in the URL; this page exchanges it for an app session.
      </p>
      <a class="cbp-btn cbp-btn-primary" :href="portalHref">Go to Staff portal home</a>
    </div>

    <div v-else class="cbp-card welcome">
      <p class="welcome-line">
        Signed in as <strong>{{ auth.me?.name ?? 'Staff' }}</strong>
        <span v-if="profileSap" class="pill">SAP {{ profileSap }}</span>
        <span v-else-if="auth.me?.profile?.staff_id" class="pill">Staff ID {{ auth.me.profile.staff_id }}</span>
      </p>
      <div class="actions">
        <RouterLink class="cbp-btn cbp-btn-primary" to="/tickets">My tickets</RouterLink>
        <RouterLink class="cbp-btn cbp-btn-ghost" to="/tickets/new">New request</RouterLink>
      </div>
    </div>

    <div v-if="app.healthError" class="cbp-card banner error">{{ app.healthError }}</div>
    <div v-else-if="app.health" class="cbp-card panel">
      <p class="panel-title">Service status</p>
      <p><strong>API:</strong> {{ (app.health as { service?: string }).service }} ({{ (app.health as { version?: string }).version }})</p>
      <p><strong>Laravel:</strong> {{ (app.health as { laravel?: string }).laravel }}</p>
      <p v-if="(app.health as { branding?: { primary?: string } }).branding">
        <span class="swatch" :style="{ background: (app.health as { branding: { primary: string } }).branding.primary }" />
        Primary {{ (app.health as { branding: { primary: string } }).branding.primary }}
      </p>
    </div>
    <p v-else class="muted">Checking API…</p>
  </div>
</template>

<style scoped>
.gate {
  border-left: 4px solid #c9a227;
}
.gate-title {
  margin: 0 0 0.5rem;
  font-weight: 700;
  font-size: 1.05rem;
  color: #2c3e50;
}
.gate-text {
  margin: 0 0 1.1rem;
  color: #5c6c7c;
  line-height: 1.55;
  font-size: 0.95rem;
}
.welcome {
  border-left: 4px solid #119a48;
}
.welcome-line {
  margin: 0 0 1rem;
  color: #5c6c7c;
}
.pill {
  display: inline-block;
  margin-left: 0.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 6px;
  background: #e8f5ee;
  color: #0d7a3a;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
}
.cbp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.55rem 1.1rem;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.95rem;
  text-decoration: none;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.cbp-btn:hover {
  transform: translateY(-1px);
}
.cbp-btn-primary {
  background: linear-gradient(135deg, #119a48 0%, #0d7a3a 100%);
  color: #fff;
  box-shadow: 0 4px 14px rgba(17, 154, 72, 0.35);
}
.cbp-btn-ghost {
  background: transparent;
  color: #0d7a3a;
  border-color: rgba(17, 154, 72, 0.35);
}
.panel-title {
  margin: 0 0 0.65rem;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6c757d;
}
.panel p {
  margin: 0.35rem 0;
  font-size: 0.92rem;
}
.banner.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}
.muted {
  color: #64748b;
  margin-top: 1rem;
}
.swatch {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 4px;
  margin-right: 6px;
  vertical-align: middle;
  border: 1px solid #cbd5e1;
}
</style>
