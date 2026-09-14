export interface FormError {
  name: string
  message: string
}

export interface FormSubmitEvent<T = Record<string, unknown>> {
  data: T
}
