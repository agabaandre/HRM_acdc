declare module '@nuxt/ui/vite' {
  import type { Plugin } from 'vite'

  function ui(): Plugin | Plugin[]
  export default ui
}
