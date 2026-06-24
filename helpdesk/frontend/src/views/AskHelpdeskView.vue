<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '@nuxt/ui'
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

function confidenceClass(level?: string): string {
  if (level === 'high') return 'hd-confidence--high'
  if (level === 'low') return 'hd-confidence--low'
  return 'hd-confidence--medium'
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
      <section class="hd-ask-panel" aria-label="Ask Helpdesk conversation">
        <header class="hd-ask-header">
          <div class="hd-ask-header-main">
            <span class="hd-ask-header-icon" aria-hidden="true">
              <i class="bx bx-bot" />
            </span>
            <div>
              <h2 class="hd-ask-header-title">Helpdesk assistant</h2>
              <p class="hd-ask-header-sub">
                {{ messages.length ? `${messages.length} message${messages.length === 1 ? '' : 's'}` : 'Knowledge-base powered answers' }}
              </p>
            </div>
          </div>
          <UButton
            v-if="messages.length > 0"
            type="button"
            color="neutral"
            variant="ghost"
            size="sm"
            icon="i-lucide-trash-2"
            :disabled="sending"
            @click="clearChat"
          >
            Clear chat
          </UButton>
        </header>

        <div ref="messagesEl" class="hd-ask-messages">
          <div v-if="messages.length === 0" class="hd-ask-empty">
            <div class="hd-ask-empty-icon" aria-hidden="true">
              <i class="bx bx-message-dots" />
            </div>
            <h3>How can we help?</h3>
            <p>
              Ask about access, email, VPN, printers, software, or devices. Pick a suggested prompt on the right,
              or type your question below.
            </p>
            <div class="hd-ask-empty-prompts">
              <UButton
                v-for="p in starterPrompts.slice(0, 2)"
                :key="p"
                type="button"
                color="neutral"
                variant="soft"
                size="sm"
                class="hd-ask-empty-chip"
                :disabled="sending"
                @click="usePrompt(p)"
              >
                {{ p }}
              </UButton>
            </div>
          </div>

          <div
            v-for="m in messages"
            :key="m.id"
            class="hd-chat-row"
            :class="m.role === 'user' ? 'hd-chat-row--user' : 'hd-chat-row--agent'"
          >
            <span class="hd-chat-avatar" aria-hidden="true">
              <i :class="m.role === 'user' ? 'bx bx-user' : 'bx bx-bot'" />
            </span>

            <article class="hd-bubble" :class="m.role === 'user' ? 'hd-bubble--user' : 'hd-bubble--agent'">
              <span class="hd-bubble-label">{{ m.role === 'user' ? 'You' : 'Helpdesk assistant' }}</span>
              <p class="hd-bubble-text">{{ m.text }}</p>

              <ol v-if="m.steps && m.steps.length" class="hd-steps">
                <li v-for="(step, idx) in m.steps" :key="idx">{{ step }}</li>
              </ol>

              <div v-if="m.role === 'agent'" class="hd-ask-meta">
                <span v-if="m.confidence" class="hd-confidence" :class="confidenceClass(m.confidence)">
                  {{ m.confidence }} confidence
                </span>
                <template v-if="m.relatedArticles && m.relatedArticles.length">
                  <p class="hd-ask-meta-label">Related FAQs</p>
                  <RouterLink
                    v-for="a in m.relatedArticles"
                    :key="a.id"
                    class="hd-related-link"
                    to="/"
                  >
                    <i class="bx bx-link-external" aria-hidden="true" />
                    {{ a.question }}
                  </RouterLink>
                </template>
                <p v-if="m.suggestTicket" class="hd-ask-ticket-hint">
                  Still stuck?
                  <RouterLink to="/tickets/new">Log a new request</RouterLink>
                  for an agent.
                </p>
              </div>
            </article>
          </div>

          <div v-if="sending" class="hd-chat-row hd-chat-row--agent" role="status" aria-live="polite">
            <span class="hd-chat-avatar" aria-hidden="true">
              <i class="bx bx-bot" />
            </span>
            <div class="hd-typing">
              <span class="hd-typing-dot" />
              <span class="hd-typing-dot" />
              <span class="hd-typing-dot" />
              <span class="hd-typing-label">Assistant is thinking…</span>
            </div>
          </div>
        </div>

        <footer class="hd-ask-compose">
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
              <UButton
                type="submit"
                color="primary"
                size="md"
                icon="i-lucide-send"
                :loading="sending"
                :disabled="form.question.trim().length < 8"
              >
                Ask
              </UButton>
            </div>
          </UForm>
        </footer>
      </section>

      <aside class="hd-ask-side" aria-label="Ask Helpdesk tips">
        <div class="hd-side-card">
          <h3>Suggested prompts</h3>
          <UButton
            v-for="p in starterPrompts"
            :key="p"
            type="button"
            color="neutral"
            variant="outline"
            size="sm"
            class="hd-prompt-chip"
            :disabled="sending"
            @click="usePrompt(p)"
          >
            {{ p }}
          </UButton>
        </div>
        <div class="hd-side-card">
          <h3>Before you ask</h3>
          <p>Include what you were doing, any error message, and whether others are affected. Do not share passwords.</p>
        </div>
        <div class="hd-side-card">
          <h3>Need a person?</h3>
          <p>
            <RouterLink to="/tickets/new">Open a new request</RouterLink>
            and an agent will follow up during business hours.
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.hd-prompt-chip {
  width: 100%;
  justify-content: flex-start;
  text-align: left;
  white-space: normal;
  height: auto;
  margin-bottom: 0.35rem;
}
</style>
