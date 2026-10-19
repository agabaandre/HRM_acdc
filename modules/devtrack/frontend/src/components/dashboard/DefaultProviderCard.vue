<script setup lang="ts">
import { useRouter } from 'vue-router'

type Provider = {
  id: number
  name: string
  driver: string
} | null

defineProps<{
  provider: Provider
  sentToday: number
  failedToday: number
  integrations: number
}>()

const router = useRouter()

const quickLinks = [
  { title: 'Send email', icon: 'mdi-email-fast-outline', to: { name: 'send-mail' } },
  { title: 'View logs', icon: 'mdi-email-search-outline', to: { name: 'logs' } },
  { title: 'Providers', icon: 'mdi-server-network', to: { name: 'providers' } },
  { title: 'Integrations', icon: 'mdi-connection', to: { name: 'integrations' } },
]
</script>

<template>
  <v-card elevation="10" class="withbg overflow-hidden h-100">
    <div class="provider-card__banner" />
    <div class="d-flex justify-center provider-card__avatar-wrap">
      <v-avatar size="88" color="surface" class="provider-card__avatar elevation-4">
        <v-icon size="42" color="primary">mdi-email-outline</v-icon>
      </v-avatar>
    </div>

    <v-card-text class="pt-2 px-6 pb-6 text-center">
      <h3 class="card-title mb-1">Mail delivery</h3>
      <p class="card-subtitle mb-4">
        <template v-if="provider">
          Default provider: <strong>{{ provider.name }}</strong>
          <v-chip size="x-small" color="primary" variant="tonal" class="ml-2 text-capitalize">
            {{ provider.driver }}
          </v-chip>
        </template>
        <template v-else>
          No default provider configured yet.
        </template>
      </p>

      <v-btn
        v-if="provider"
        color="primary"
        rounded="pill"
        variant="flat"
        class="mb-5"
        @click="router.push({ name: 'provider-edit', params: { id: provider.id } })"
      >
        Manage provider
      </v-btn>
      <v-btn
        v-else
        color="primary"
        rounded="pill"
        variant="flat"
        class="mb-5"
        @click="router.push({ name: 'providers' })"
      >
        Configure provider
      </v-btn>

      <v-row class="provider-card__stats border-t pt-4">
        <v-col cols="4">
          <div class="text-h4 font-weight-semibold">{{ sentToday }}</div>
          <div class="text-subtitle-2 text-medium-emphasis">Sent today</div>
        </v-col>
        <v-col cols="4">
          <div class="text-h4 font-weight-semibold text-error">{{ failedToday }}</div>
          <div class="text-subtitle-2 text-medium-emphasis">Failed today</div>
        </v-col>
        <v-col cols="4">
          <div class="text-h4 font-weight-semibold">{{ integrations }}</div>
          <div class="text-subtitle-2 text-medium-emphasis">Integrations</div>
        </v-col>
      </v-row>

      <v-divider class="my-5" />

      <div class="text-subtitle-2 text-medium-emphasis text-left mb-3">Quick actions</div>
      <v-row dense>
        <v-col v-for="link in quickLinks" :key="link.title" cols="6">
          <v-btn
            block
            variant="tonal"
            color="primary"
            class="justify-start text-none"
            :prepend-icon="link.icon"
            :to="link.to"
          >
            {{ link.title }}
          </v-btn>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.provider-card__banner {
  height: 96px;
  background:
    linear-gradient(
      135deg,
      color-mix(in srgb, rgb(var(--v-theme-primary)) 88%, #ffffff) 0%,
      color-mix(in srgb, rgb(var(--v-theme-secondary)) 72%, #ffffff) 100%
    );
}

.provider-card__avatar-wrap {
  margin-top: -44px;
}

.provider-card__avatar {
  border: 4px solid rgb(var(--v-theme-surface));
}

.provider-card__stats {
  margin-inline: -8px;
}
</style>
