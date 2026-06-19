<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { FormError } from '@nuxt/ui'
import CbpRichTextEditor from '../common/CbpRichTextEditor.vue'
import { fieldError, type SelectNumberItem } from '../../lib/helpdeskForm'
import { hasRichTextContent } from '../../lib/richText'

export interface KbCat {
  id: number
  name: string
}

export interface KbArticleEditForm {
  id: number
  category_id: number
  question: string
  answer: string
  sort_order: number
  is_active: boolean
}

const props = defineProps<{
  open: boolean
  article: KbArticleEditForm | null
  categories: KbCat[]
  busy: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  save: [payload: KbArticleEditForm]
}>()

const form = reactive<KbArticleEditForm>({
  id: 0,
  category_id: 0,
  question: '',
  answer: '',
  sort_order: 0,
  is_active: true,
})

const categoryItems = computed((): SelectNumberItem[] =>
  props.categories.map((c) => ({ label: c.name, value: c.id })),
)

watch(
  () => props.article,
  (a) => {
    if (!a) {
      return
    }
    form.id = a.id
    form.category_id = a.category_id
    form.question = a.question
    form.answer = a.answer
    form.sort_order = a.sort_order
    form.is_active = a.is_active
  },
  { immediate: true },
)

function validateForm(state: typeof form): FormError[] {
  const errors: FormError[] = []
  if (!state.category_id) {
    errors.push({ name: 'category_id', message: 'Choose a category' })
  }
  const qErr = fieldError('question', state.question, 'Question is required')
  if (qErr) {
    errors.push(qErr)
  }
  if (!hasRichTextContent(state.answer)) {
    errors.push({ name: 'answer', message: 'Answer is required' })
  }
  return errors
}

function onBackdrop(e: MouseEvent) {
  if ((e.target as HTMLElement).classList.contains('kb-modal-backdrop')) {
    emit('close')
  }
}

function submitForm() {
  if (!form.question.trim() || !hasRichTextContent(form.answer) || !form.category_id) {
    return
  }
  emit('save', { ...form, question: form.question.trim() })
}
</script>

<template>
  <div
    v-if="open && article"
    class="kb-modal-backdrop"
    @click="onBackdrop"
  >
    <div class="kb-modal" role="dialog" aria-modal="true" aria-labelledby="kb-edit-title" @click.stop>
      <header class="kb-modal-head">
        <h3 id="kb-edit-title" class="kb-modal-title">Edit article</h3>
        <UButton type="button" color="neutral" variant="ghost" size="sm" aria-label="Close" :disabled="busy" @click="emit('close')">
          ×
        </UButton>
      </header>
      <div class="kb-modal-body">
        <p v-if="error" class="kb-modal-err" role="alert">{{ error }}</p>
        <UForm
          :state="form"
          :validate="validateForm"
          class="hd-form hd-form--grid"
          :disabled="busy"
          @submit="submitForm"
        >
          <UFormField label="Category" name="category_id" required>
            <USelect v-model="form.category_id" :items="categoryItems" class="w-full" />
          </UFormField>
          <UFormField label="Sort order" name="sort_order">
            <UInput v-model.number="form.sort_order" type="number" min="0" class="w-full" />
          </UFormField>
          <UFormField name="is_active" class="full">
            <UCheckbox v-model="form.is_active" label="Active (visible on home knowledge base)" />
          </UFormField>
          <UFormField label="Question" name="question" required class="full">
            <UInput v-model="form.question" type="text" maxlength="255" class="w-full" />
          </UFormField>
          <UFormField label="Answer" name="answer" required class="full hd-rich-field">
            <CbpRichTextEditor v-model="form.answer" :disabled="busy" />
          </UFormField>
        </UForm>
      </div>
      <footer class="kb-modal-foot">
        <UButton type="button" color="neutral" variant="outline" :disabled="busy" @click="emit('close')">Cancel</UButton>
        <UButton
          type="button"
          color="primary"
          :loading="busy"
          :disabled="!form.question.trim() || !hasRichTextContent(form.answer)"
          @click="submitForm"
        >
          Save changes
        </UButton>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.kb-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.kb-modal {
  width: min(640px, 100%);
  max-height: min(92vh, 720px);
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
}

.kb-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.kb-modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a1a1a;
}

.kb-modal-body {
  padding: 1rem;
  overflow-y: auto;
  flex: 1;
}

.kb-modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-top: 1px solid #e2e8f0;
}

.kb-modal-err {
  margin: 0 0 0.75rem;
  padding: 0.5rem 0.65rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 4px;
  font-size: 0.875rem;
}
</style>
