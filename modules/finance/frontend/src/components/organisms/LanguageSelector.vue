<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()
const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

async function choose(code: string) {
  close()
  if (code === locale.locale) return
  try {
    await locale.setLocale(code)
  } catch {
    /* keep the current locale if the API rejects the switch */
  }
}

function onDocClick(e: MouseEvent) {
  const t = e.target as Node
  if (rootRef.value?.contains(t)) return
  close()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div
    v-if="locale.languages.length"
    ref="rootRef"
    class="cbp-lang-select"
    :class="{ 'is-open': open }"
  >
    <button
      type="button"
      class="cbp-lang-select__btn notranslate"
      :aria-expanded="open"
      aria-haspopup="listbox"
      :title="locale.t('chrome.language', 'Language')"
      :aria-label="locale.t('chrome.languages', 'Languages')"
      @click.stop="toggle"
    >
      <span class="cbp-lang-select__flag" aria-hidden="true">{{ locale.currentLanguage?.flag }}</span>
      <span class="cbp-lang-select__code">{{ (locale.currentLanguage?.code || locale.locale).toUpperCase() }}</span>
      <span class="cbp-lang-select__caret" aria-hidden="true">▼</span>
    </button>
    <ul v-show="open" class="cbp-lang-select__menu notranslate" role="listbox">
      <li v-for="lang in locale.languages" :key="lang.code">
        <button
          type="button"
          class="cbp-lang-select__item"
          :class="{ 'is-active': lang.code === locale.locale }"
          role="option"
          :aria-selected="lang.code === locale.locale"
          @click="choose(lang.code)"
        >
          <span class="cbp-lang-select__flag" aria-hidden="true">{{ lang.flag }}</span>
          <span>{{ lang.name }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
