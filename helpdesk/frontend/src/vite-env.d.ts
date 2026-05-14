/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_STAFF_PORTAL_HOME_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
