<script setup lang="ts">
import { reactive, watch } from 'vue'
import CbpRichTextEditor from '../common/CbpRichTextEditor.vue'
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

function onBackdrop(e: MouseEvent) {
  if ((e.target as HTMLElement).classList.contains('kb-modal-backdrop')) {
    emit('close')
  }
}

function submit() {
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
        <button type="button" class="kb-modal-close" aria-label="Close" :disabled="busy" @click="emit('close')">
          ×
        </button>
      </header>
      <div class="kb-modal-body">
        <p v-if="error" class="kb-modal-err" role="alert">{{ error }}</p>
        <div class="kb-form-grid">
          <label>
            Category
            <select v-model.number="form.category_id" :disabled="busy">
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label>
            Sort order
            <input v-model.number="form.sort_order" type="number" min="0" :disabled="busy" />
          </label>
          <label class="kb-check">
            <input v-model="form.is_active" type="checkbox" :disabled="busy" />
            Active (visible on home knowledge base)
          </label>
        </div>
        <label class="kb-full">
          Question
          <input v-model="form.question" type="text" maxlength="255" :disabled="busy" />
        </label>
        <label class="kb-full">
          Answer
          <CbpRichTextEditor v-model="form.answer" :disabled="busy" />
        </label>
      </div>
      <footer class="kb-modal-foot">
        <button type="button" class="btn ghost" :disabled="busy" @click="emit('close')">Cancel</button>
        <button
          type="button"
          class="btn primary"
          :disabled="busy || !form.question.trim() || !hasRichTextContent(form.answer)"
          @click="submit"
        >
          {{ busy ? 'Saving…' : 'Save changes' }}
        </button>
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
  border-radius: 12px;
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

.kb-modal-close {
  border: none;
  background: transparent;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  color: #64748b;
  padding: 0.15rem 0.35rem;
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
  border-radius: 8px;
  font-size: 0.875rem;
}

.kb-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

label {
  display: flex;
  flex-direction: column;
  font-size: 0.85rem;
  font-weight: 600;
  color: #3a4452;
  gap: 0.3rem;
}

input,
select {
  padding: 0.5rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  font-family: inherit;
}

.kb-check {
  flex-direction: row;
  align-items: center;
  font-weight: 500;
}

.kb-full {
  margin-top: 0.5rem;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  border: none;
}

.btn.primary {
  background: linear-gradient(135deg, #119a48 0%, #0d7a3a 100%);
  color: #fff;
}

.btn.ghost {
  background: #f1f5f9;
  color: #334155;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
</style>
