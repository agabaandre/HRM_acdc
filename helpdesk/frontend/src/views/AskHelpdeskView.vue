<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '../types/form'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { minLengthError } from '../lib/helpdeskForm'
import { notifyError } from '../lib/notify'

interface ChatMessage {
  id: number
  role: 'user' | 'agent'
  text: string
  steps?: string[]
  confidence?: string
  relatedArticles?: Array<{ id: number; question: string }>
  suggestTicket?: boolean
}

interface AskResponse {
  answer: string
  steps: string[]
  related_articles: Array<{ id: number; question: string }>
  suggest_ticket: boolean
  confidence: string
  source: string
}

const form = reactive({ question: '' })
const sending = ref(false)
const messages = ref<ChatMessage[]>([])
const messagesEl = ref<HTMLElement | null>(null)
let seq = 0

const starterPrompts = [
  'I cannot sign in to the Staff portal',
  'My VPN connection keeps dropping',
  'How do I reset my email password?',
  'The printer on my floor is not working',
]

function validateAsk(state: typeof form): FormError[] {
  const errors: FormError[] = []
  const lenErr = minLengthError('question', state.question, 8, 'Enter at least 8 characters')
  if (lenErr) {
    errors.push(lenErr)
  }
  return errors
}

async function scrollToBottom(): Promise<void> {
  await nextTick()
  const el = messagesEl.value
  if (el) {
    el.scrollTop = el.scrollHeight
  }
}

async function sendQuestion(text: string): Promise<void> {
  const question = text.trim()
  if (question.length < 8 || sending.value) {
    return
  }

  messages.value.push({
    id: ++seq,
    role: 'user',
    text: question,
  })
  form.question = ''
  sending.value = true
  await scrollToBottom()

  try {
    const { data } = await api.post<{ data: AskResponse }>('/api/v1/ai/ask', { question })
    const payload = data.data
    messages.value.push({
      id: ++seq,
      role: 'agent',
      text: payload.answer,
      steps: payload.steps ?? [],
      confidence: payload.confidence,
      relatedArticles: payload.related_articles ?? [],
      suggestTicket: payload.suggest_ticket,
    })
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Ask Helpdesk could not respond. Try again or log a ticket.'))
  } finally {
    sending.value = false
    await scrollToBottom()
  }
}

async function onSubmit(_event: FormSubmitEvent<typeof form>): Promise<void> {
  await sendQuestion(form.question)
}

function usePrompt(prompt: string): void {
  form.question = prompt
  void sendQuestion(prompt)
}

function clearChat(): void {
  if (messages.value.length === 0 || sending.value) {
    return
  }
  if (!window.confirm('Clear this conversation and start a new chat?')) {
    return
  }
  messages.value = []
  form.question = ''
}

function confidenceColor(level?: string): string {
  if (level === 'high') return 'success'
  if (level === 'low') return 'default'
  return 'warning'
}

function onComposeKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    if (form.question.trim().length >= 8 && !sending.value) {
      void sendQuestion(form.question)
    }
  }
}
</script>

<template>
  <div>
    <CbpBadgeStrip product="ITSM · AI" />
    <CbpPageHeading title="Ask Helpdesk">
      <template #lede>
        Describe your IT issue in plain language. Our assistant searches the knowledge base and suggests
        troubleshooting steps before you open a ticket.
      </template>
    </CbpPageHeading>

    <div class="hd-ask-layout">
      <v-card class="hd-ask-panel" variant="outlined" aria-label="Ask Helpdesk conversation">
        <v-card-item class="hd-ask-header">
          <template #prepend>
            <v-avatar rounded="lg" color="primary" variant="tonal" size="40">
              <v-icon icon="mdi-robot" size="22" />
            </v-avatar>
          </template>
          <v-card-title class="hd-ask-header-title">Helpdesk assistant</v-card-title>
          <v-card-subtitle class="hd-ask-header-sub">
            {{ messages.length ? `${messages.length} message${messages.length === 1 ? '' : 's'}` : 'Knowledge-base powered answers' }}
          </v-card-subtitle>
          <template #append>
            <v-btn
              v-if="messages.length > 0"
              variant="text"
              size="small"
              prepend-icon="mdi-delete-outline"
              :disabled="sending"
              @click="clearChat"
            >
              Clear chat
            </v-btn>
          </template>
        </v-card-item>

        <v-divider />

        <div ref="messagesEl" class="hd-ask-messages">
          <div v-if="messages.length === 0" class="hd-ask-empty">
            <v-avatar rounded="lg" color="primary" variant="tonal" size="56" class="hd-ask-empty-icon">
              <v-icon icon="mdi-message-text-outline" size="28" />
            </v-avatar>
            <h3>How can we help?</h3>
            <p>
              Ask about access, email, VPN, printers, software, or devices. Pick a suggested prompt on the right,
              or type your question below.
            </p>
            <div class="hd-ask-empty-prompts">
              <v-chip
                v-for="p in starterPrompts.slice(0, 2)"
                :key="p"
                variant="tonal"
                color="primary"
                size="small"
                class="hd-ask-empty-chip"
                :disabled="sending"
                @click="usePrompt(p)"
              >
                {{ p }}
              </v-chip>
            </div>
          </div>

          <div
            v-for="m in messages"
            :key="m.id"
            class="hd-chat-row"
            :class="m.role === 'user' ? 'hd-chat-row--user' : 'hd-chat-row--agent'"
          >
            <v-avatar
              size="32"
              :color="m.role === 'user' ? 'primary' : 'primary'"
              :variant="m.role === 'user' ? 'flat' : 'tonal'"
            >
              <v-icon :icon="m.role === 'user' ? 'mdi-account' : 'mdi-robot'" size="18" />
            </v-avatar>

            <v-sheet
              class="hd-bubble"
              :class="m.role === 'user' ? 'hd-bubble--user' : 'hd-bubble--agent'"
              :color="m.role === 'user' ? 'primary' : undefined"
              :variant="m.role === 'user' ? 'flat' : 'outlined'"
              rounded="lg"
            >
              <span class="hd-bubble-label">{{ m.role === 'user' ? 'You' : 'Helpdesk assistant' }}</span>
              <p class="hd-bubble-text">{{ m.text }}</p>

              <ol v-if="m.steps && m.steps.length" class="hd-steps">
                <li v-for="(step, idx) in m.steps" :key="idx">{{ step }}</li>
              </ol>

              <div v-if="m.role === 'agent'" class="hd-ask-meta">
                <v-chip
                  v-if="m.confidence"
                  size="x-small"
                  :color="confidenceColor(m.confidence)"
                  variant="tonal"
                  class="hd-confidence-chip"
                >
                  {{ m.confidence }} confidence
                </v-chip>

                <template v-if="m.relatedArticles && m.relatedArticles.length">
                  <p class="hd-ask-meta-label">Related FAQs</p>
                  <v-list density="compact" class="hd-ask-related-list" bg-color="transparent">
                    <v-list-item
                      v-for="a in m.relatedArticles"
                      :key="a.id"
                      :to="'/'"
                      prepend-icon="mdi-book-open-page-variant"
                      rounded="sm"
                    >
                      <v-list-item-title>{{ a.question }}</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </template>

                <p v-if="m.suggestTicket" class="hd-ask-ticket-hint">
                  Still stuck?
                  <RouterLink to="/tickets/new">Log a new request</RouterLink>
                  for an agent.
                </p>
              </div>
            </v-sheet>
          </div>

          <div v-if="sending" class="hd-chat-row hd-chat-row--agent" role="status" aria-live="polite" aria-label="Assistant is thinking">
            <v-avatar size="32" color="primary" variant="tonal">
              <v-icon icon="mdi-robot" size="18" />
            </v-avatar>
            <v-sheet class="hd-bubble hd-bubble--agent hd-bubble--loading" variant="outlined" rounded="lg">
              <v-skeleton-loader type="sentences" />
            </v-sheet>
          </div>
        </div>

        <v-divider />

        <v-card-text class="hd-ask-compose">
          <UForm
            :state="form"
            :validate="validateAsk"
            class="hd-ask-compose-form"
            :disabled="sending"
            @submit="onSubmit"
          >
            <UFormField name="question" class="hd-ask-compose-field">
              <UTextarea
                id="ask-input"
                v-model="form.question"
                :rows="2"
                placeholder="Describe your issue… e.g. I cannot access my email after changing my password"
                class="w-full hd-ask-textarea"
                @keydown="onComposeKeydown"
              />
            </UFormField>
            <div class="hd-ask-compose-actions">
              <span class="hd-ask-compose-hint">Enter to send · Shift+Enter for new line</span>
              <v-btn
                type="submit"
                color="primary"
                prepend-icon="mdi-send"
                :loading="sending"
                :disabled="form.question.trim().length < 8"
              >
                Ask
              </v-btn>
            </div>
          </UForm>
        </v-card-text>
      </v-card>

      <aside class="hd-ask-side" aria-label="Ask Helpdesk tips">
        <v-card variant="outlined" class="hd-side-card">
          <v-card-title class="hd-side-card-title">Suggested prompts</v-card-title>
          <v-card-text class="hd-side-card-body">
            <v-btn
              v-for="p in starterPrompts"
              :key="p"
              variant="outlined"
              size="small"
              block
              class="hd-prompt-btn"
              :disabled="sending"
              @click="usePrompt(p)"
            >
              {{ p }}
            </v-btn>
          </v-card-text>
        </v-card>

        <v-card variant="outlined" class="hd-side-card">
          <v-card-title class="hd-side-card-title">Before you ask</v-card-title>
          <v-card-text class="hd-side-card-body">
            Include what you were doing, any error message, and whether others are affected. Do not share passwords.
          </v-card-text>
        </v-card>

        <v-card variant="outlined" class="hd-side-card">
          <v-card-title class="hd-side-card-title">Need a person?</v-card-title>
          <v-card-text class="hd-side-card-body">
            <RouterLink to="/tickets/new">Open a new request</RouterLink>
            and an agent will follow up during business hours.
          </v-card-text>
        </v-card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.hd-prompt-btn {
  justify-content: flex-start;
  text-align: left;
  white-space: normal;
  height: auto;
  min-height: 2.25rem;
  margin-bottom: 0.35rem;
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
}
</style>
