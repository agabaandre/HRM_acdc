<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpBadgeStrip from '../components/common/CbpBadgeStrip.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
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

const draft = ref('')
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
  draft.value = ''
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

function onSubmit(): void {
  void sendQuestion(draft.value)
}

function usePrompt(prompt: string): void {
  draft.value = prompt
  void sendQuestion(prompt)
}

function confidenceClass(level?: string): string {
  if (level === 'high') return 'hd-confidence--high'
  if (level === 'low') return 'hd-confidence--low'
  return 'hd-confidence--medium'
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
        <div ref="messagesEl" class="hd-ask-messages">
          <div v-if="messages.length === 0" class="hd-ask-empty">
            <i class="bx bx-bot" aria-hidden="true" />
            <p>
              <strong>How can we help?</strong><br />
              Ask about access, email, VPN, printers, software, or devices. Try a suggested prompt on the right.
            </p>
          </div>

          <article
            v-for="m in messages"
            :key="m.id"
            class="hd-bubble"
            :class="m.role === 'user' ? 'hd-bubble--user' : 'hd-bubble--agent'"
          >
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
                <p style="margin: 0.5rem 0 0">Related FAQs:</p>
                <RouterLink
                  v-for="a in m.relatedArticles"
                  :key="a.id"
                  class="hd-related-link"
                  to="/"
                >
                  {{ a.question }}
                </RouterLink>
              </template>
              <p v-if="m.suggestTicket" style="margin: 0.65rem 0 0">
                Still stuck?
                <RouterLink to="/tickets/new">Log a new request</RouterLink>
                for an agent.
              </p>
            </div>
          </article>

          <p v-if="sending" class="hd-ask-empty" role="status">Thinking…</p>
        </div>

        <form class="hd-ask-compose" @submit.prevent="onSubmit">
          <label class="sr-only" for="ask-input">Your question</label>
          <textarea
            id="ask-input"
            v-model="draft"
            class="hd-ask-input"
            rows="2"
            placeholder="e.g. I cannot access my email after changing my password…"
            :disabled="sending"
            @keydown.enter.exact.prevent="onSubmit"
          />
          <button type="submit" class="hd-btn hd-btn--primary hd-ask-send" :disabled="sending || draft.trim().length < 8">
            {{ sending ? '…' : 'Ask' }}
          </button>
        </form>
      </section>

      <aside class="hd-ask-side" aria-label="Ask Helpdesk tips">
        <div class="hd-side-card">
          <h3>Suggested prompts</h3>
          <button
            v-for="p in starterPrompts"
            :key="p"
            type="button"
            class="hd-prompt-chip"
            :disabled="sending"
            @click="usePrompt(p)"
          >
            {{ p }}
          </button>
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
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.hd-bubble-text {
  margin: 0;
}
</style>
