import type { App } from 'vue'
import UApp from './UApp.vue'
import UDateInput from './UDateInput.vue'
import UFormField from './UFormField.vue'
import UInput from './UInput.vue'
import USelect from './USelect.vue'
import USelectMenu from './USelectMenu.vue'
import UTextarea from './UTextarea.vue'

/** Risk Register UI kit — Helpdesk-aligned field wrappers. */
export function registerUiComponents(app: App): void {
  app.component('UApp', UApp)
  app.component('UDateInput', UDateInput)
  app.component('UFormField', UFormField)
  app.component('UInput', UInput)
  app.component('USelect', USelect)
  app.component('USelectMenu', USelectMenu)
  app.component('UTextarea', UTextarea)
}
