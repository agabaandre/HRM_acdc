import type { InjectionKey, Ref } from 'vue'
import type { FormError } from '../../types/form'

export const formErrorsKey: InjectionKey<Ref<FormError[]>> = Symbol('helpdeskFormErrors')
