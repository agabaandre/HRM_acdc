export type ToastType = 'success' | 'error' | 'info' | 'warning'

export type ToastInput = {
  title?: string
  message: string
  type?: ToastType
  duration?: number
}

export type ToastRecord = Required<Pick<ToastInput, 'title' | 'message' | 'type' | 'duration'>> & {
  id: string
  visible: boolean
}
