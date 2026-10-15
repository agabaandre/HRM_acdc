/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_STAFF_PORTAL_API_BASE_URL?: string
  readonly VITE_STAFF_PORTAL_BASE_PATH?: string
  readonly VITE_STAFF_PORTAL_API_PROXY_TARGET?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
