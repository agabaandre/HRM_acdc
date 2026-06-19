import type { DefineComponent } from 'vue'

type UiComponent = DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>

declare module 'vue' {
  export interface GlobalComponents {
    UApp: UiComponent
    UAvatar: UiComponent
    UBadge: UiComponent
    UButton: UiComponent
    UCard: UiComponent
    UFormField: UiComponent
    UInput: UiComponent
    UModal: UiComponent
    UTextarea: UiComponent
  }
}

export {}
