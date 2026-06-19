declare function defineAppConfig(config: Record<string, unknown>): Record<string, unknown>

declare module '@nuxt/ui/vue-plugin' {
  import type { Plugin } from 'vue'
  const plugin: Plugin
  export default plugin
}

declare module '@nuxt/ui/vite' {
  import type { Plugin } from 'vite'
  const plugin: Plugin
  export default plugin
}

declare module '@nuxt/ui' {
  export interface FormError {
    name: string
    message: string
  }

  export interface FormSubmitEvent<T = Record<string, unknown>> {
    data: T
  }
}
