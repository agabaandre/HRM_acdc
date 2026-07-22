import type { App } from 'vue'
import UApp from './UApp.vue'
import UAvatar from './UAvatar.vue'
import UBadge from './UBadge.vue'
import UButton from './UButton.vue'
import UCard from './UCard.vue'
import UCheckbox from './UCheckbox.vue'
import UColorInput from './UColorInput.vue'
import UDateInput from './UDateInput.vue'
import UForm from './UForm.vue'
import UFormField from './UFormField.vue'
import UInput from './UInput.vue'
import UModal from './UModal.vue'
import USelect from './USelect.vue'
import USelectMenu from './USelectMenu.vue'
import UStaffDirectoryPicker from './UStaffDirectoryPicker.vue'
import USwitch from './USwitch.vue'
import UTextarea from './UTextarea.vue'

const components = {
  UApp,
  UAvatar,
  UBadge,
  UButton,
  UCard,
  UCheckbox,
  UColorInput,
  UDateInput,
  UForm,
  UFormField,
  UInput,
  UModal,
  USelect,
  USelectMenu,
  UStaffDirectoryPicker,
  USwitch,
  UTextarea,
} as const

export function registerUiComponents(app: App): void {
  for (const [name, component] of Object.entries(components)) {
    app.component(name, component)
  }
}
