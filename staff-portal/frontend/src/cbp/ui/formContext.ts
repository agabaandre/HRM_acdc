import type { ComputedRef, InjectionKey, Ref } from 'vue'
import type { FormError } from '../../types/form'

export const formErrorsKey: InjectionKey<Ref<FormError[]>> = Symbol('helpdeskFormErrors')

/** Floating label passed from UFormField to Vuetify field wrappers. */
export const fieldLabelKey: InjectionKey<ComputedRef<string | undefined>> = Symbol('helpdeskFieldLabel')

/** Required flag passed from UFormField to Vuetify field wrappers. */
export const fieldRequiredKey: InjectionKey<ComputedRef<boolean>> = Symbol('helpdeskFieldRequired')
