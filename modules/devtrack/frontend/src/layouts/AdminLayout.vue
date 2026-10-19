<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router'
import AppTopbar from '@/components/layout/AppTopbar.vue'
import AppTopNav from '@/components/layout/AppTopNav.vue'

const route = useRoute()
</script>

<template>
  <v-layout class="admin-layout">
    <AppTopbar />

    <v-main class="bg-background">
      <AppTopNav />
      <v-container fluid class="page-wrapper py-6 px-4 px-md-6 px-lg-8">
        <div class="page-content">
          <RouterView :key="route.fullPath" />
        </div>
      </v-container>
    </v-main>
  </v-layout>
</template>
