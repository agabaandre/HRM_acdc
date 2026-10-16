import type { ComputedRef, InjectionKey, Ref } from 'vue'

export type FormError = { name?: string; message?: string }

export const formErrorsKey: InjectionKey<Ref<FormError[]>> = Symbol('helpdeskFormErrors')

/** Floating label passed from UFormField to Vuetify field wrappers. */
export const fieldLabelKey: InjectionKey<ComputedRef<string | undefined>> = Symbol('helpdeskFieldLabel')

/** Required flag passed from UFormField to Vuetify field wrappers. */
export const fieldRequiredKey: InjectionKey<ComputedRef<boolean>> = Symbol('helpdeskFieldRequired')
