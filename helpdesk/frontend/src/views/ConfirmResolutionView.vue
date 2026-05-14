<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'

const route = useRoute()
const msg = ref<string | null>(null)
const err = ref<string | null>(null)
const busy = ref(true)

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  if (!token) {
    err.value = 'Missing confirmation token.'
    busy.value = false
    return
  }
  try {
    await api.post('/api/v1/public/tickets/confirm-resolution', { token })
    msg.value = 'Thank you — your ticket is marked resolved.'
  } catch {
    err.value = 'This link is invalid or was already used.'
  } finally {
    busy.value = false
  }
})
</script>

<template>
  <div>
    <CbpPageHeading title="Confirm resolution" />
    <div class="cbp-card">
      <p v-if="busy" class="muted">Processing…</p>
      <p v-else-if="msg" class="ok">{{ msg }}</p>
      <p v-else-if="err" class="err">{{ err }}</p>
      <p class="back">
        <RouterLink to="/">Back to overview</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.back {
  margin-top: 1rem;
}
.back a {
  color: #119a48;
  font-weight: 600;
  text-decoration: none;
}
.ok {
  color: #065f2c;
  font-weight: 600;
}
.err {
  color: #b91c1c;
}
.muted {
  color: #64748b;
}
.back {
  margin-top: 1.5rem;
}
.back a {
  color: #0d7a3a;
  font-weight: 600;
}
</style>
