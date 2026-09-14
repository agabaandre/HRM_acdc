import type { App } from 'vue'
import UApp from './UApp.vue'
import UDateInput from './UDateInput.vue'

/** Staff Portal UI kit — only components used by this app (vendored from CBP shared set). */
export function registerUiComponents(app: App): void {
  app.component('UApp', UApp)
  app.component('UDateInput', UDateInput)
}
