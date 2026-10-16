import { toast } from '../features/toast/api'

export function notifySuccess(message: string): void {
  toast.success(message)
}

export function notifyError(message: string): void {
  toast.error(message)
}
