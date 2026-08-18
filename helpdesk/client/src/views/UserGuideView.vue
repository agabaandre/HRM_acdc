<script setup lang="ts">
import CbpPageHeading from '../components/common/CbpPageHeading.vue'

const slides = [
  {
    num: 1,
    tag: 'Getting started',
    icon: '🔐',
    title: 'Sign in & open Helpdesk',
    lede: 'Use your existing Staff portal session — no separate password for Helpdesk.',
    steps: [
      { title: 'Go to the Staff portal', text: 'Sign in at your usual Africa CDC Staff home (same login as Finance and APM).' },
      { title: 'Open Help Desk', text: 'From the home dashboard, choose Help Desk.' },
      { title: 'Explore the home page', text: 'Browse FAQs, try Ask Help Desk (AI assistant), or jump to Create ticket.' },
    ],
    tip: 'If you see “No active session”, return to the Staff portal and open Helpdesk from there.',
  },
  {
    num: 2,
    tag: 'Log a request',
    icon: '📝',
    title: 'Create a new ticket',
    lede: 'Describe the issue clearly so the right team can help quickly.',
    steps: [
      { title: 'Click Create ticket', text: 'Available from the home hero or the Tickets page. You can also open a Software request tab when you have permission.' },
      { title: 'Choose a business unit', text: 'Pick the team that should handle the issue (e.g. IT & MIS). Use the unit description to decide. Some units allow anonymous reports.' },
      { title: 'Pick an issue category', text: 'Select a category from the radio options for that business unit. Admins may require this; otherwise AI can assign one if you leave it blank.' },
      { title: 'Describe the problem & add a screenshot', text: 'Explain what happened and when. Paste a screenshot (⌘V / Ctrl+V) or drag an image — up to 10 MB.' },
      { title: 'Submit', text: 'By default the ticket is for you. Use “another staff member” only when logging on someone else’s behalf. You can also email IT & MIS at helpdesk@africacdc.org when email intake is enabled.' },
    ],
    tip: 'A screenshot saves back-and-forth email. Try Ask Help Desk first if you only need a quick FAQ answer.',
  },
  {
    num: 3,
    tag: 'Track & follow up',
    icon: '💬',
    title: 'Follow your ticket',
    lede: 'Stay updated from the Tickets list and ticket detail page.',
    steps: [
      { title: 'Open Tickets', text: 'Search by ticket number, subject, requester, assignee, or status.' },
      { title: 'Read updates & add comments', text: 'Agents reply on the ticket thread. Add more detail anytime from the detail page.' },
      { title: 'Check status', text: 'Statuses include Open, In progress, Awaiting confirm, Resolved, and Closed.' },
      { title: 'Not fixed yet?', text: 'On a closed ticket you can comment and reopen so the assignee is notified by email.' },
    ],
    tip: 'You receive email when an agent resolves your ticket or replies to your comment.',
  },
  {
    num: 4,
    tag: 'For support agents',
    icon: '🛠️',
    title: 'Agent desk & resolution',
    lede: 'How IT staff pick up, work, and close requests.',
    steps: [
      { title: 'Agent dashboard', text: 'Open Agent desk for a greeting with your full name, assigned work, KPIs, and the kanban board.' },
      { title: 'Reply & reassign', text: 'Post public comments for the requester or internal notes for the team. Reassign when another agent should own the ticket.' },
      { title: 'Submit resolution', text: 'Document what was fixed. Optionally link a requester IT asset (serial/tag search) when the Business Unit allows it, and publish to the knowledge base if permitted.' },
      { title: 'Help Desk Modules & live screen', text: 'Use Help Desk Modules → IT Assets / Licenses / Software / Hosting / Innovations when you have access. Open Live screen → All business units for a unified TV board, or pick a unit (e.g. IT Help Desk) so queues stay separate.' },
    ],
    tip: 'Admins: Settings cover business units (mailbox intake, Allow Asset), IT Asset brands, agents, SLA, and Exchange mail.',
  },
] as const
</script>

<template>
  <div class="guide">
    <CbpPageHeading title="User guide" back-to="/" back-label="← Overview">
      <template #lede>
        Four quick slides for staff and support agents. Print this page (Ctrl/Cmd+P) to save as PDF.
      </template>
    </CbpPageHeading>

    <div class="guide-deck">
      <article v-for="slide in slides" :key="slide.num" class="guide-slide" :class="`guide-slide--${slide.num}`">
        <div class="guide-visual">
          <p class="guide-num">Slide {{ slide.num }} of 4</p>
          <p class="guide-icon" aria-hidden="true">{{ slide.icon }}</p>
          <span class="guide-tag">{{ slide.tag }}</span>
        </div>
        <div class="guide-body">
          <h2>{{ slide.title }}</h2>
          <p class="guide-lede">{{ slide.lede }}</p>
          <ol class="guide-steps">
            <li v-for="(step, idx) in slide.steps" :key="step.title">
              <span class="step-badge">{{ idx + 1 }}</span>
              <div>
                <strong>{{ step.title }}</strong>
                <span>{{ step.text }}</span>
              </div>
            </li>
          </ol>
          <p class="guide-tip"><strong>Tip:</strong> {{ slide.tip.replace(/^Tip:\s*/i, '') }}</p>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.guide-deck {
  display: grid;
  gap: 1.25rem;
}

.guide-slide {
  display: grid;
  grid-template-columns: minmax(200px, 240px) 1fr;
  background: var(--cbp-card-bg);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: var(--cbp-card-shadow);
  border: 1px solid rgba(17, 154, 72, 0.15);
}

.guide-visual {
  padding: 1.25rem;
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 260px;
}

.guide-slide--1 .guide-visual { background: linear-gradient(160deg, #119a48 0%, #0d7a3a 100%); }
.guide-slide--2 .guide-visual { background: linear-gradient(160deg, #1d4ed8 0%, #1e40af 100%); }
.guide-slide--3 .guide-visual { background: linear-gradient(160deg, #d97706 0%, #b45309 100%); }
.guide-slide--4 .guide-visual { background: linear-gradient(160deg, #7c3aed 0%, #5b21b6 100%); }

.guide-num {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  opacity: 0.92;
}

.guide-icon {
  margin: 0.75rem 0;
  font-size: 2.75rem;
  line-height: 1;
}

.guide-tag {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.guide-body {
  padding: 1.25rem 1.5rem;
}

.guide-body h2 {
  margin: 0 0 0.35rem;
  font-size: 1.35rem;
  color: var(--cbp-card-text);
}

.guide-lede {
  margin: 0 0 1rem;
  color: #64748b;
  font-size: 0.92rem;
}

.guide-steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.65rem;
}

.guide-steps li {
  display: grid;
  grid-template-columns: 2rem 1fr;
  gap: 0.65rem;
  align-items: start;
  padding: 0.65rem 0.75rem;
  border-radius: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.guide-steps strong {
  display: block;
  margin-bottom: 0.12rem;
  font-size: 0.9rem;
  color: var(--cbp-card-text);
}

.guide-steps span {
  display: block;
  font-size: 0.84rem;
  color: #64748b;
  line-height: 1.45;
}

.step-badge {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 0.82rem;
  color: #fff;
  background: #119a48;
}

.guide-slide--2 .step-badge { background: #1d4ed8; }
.guide-slide--3 .step-badge { background: #d97706; }
.guide-slide--4 .step-badge { background: #7c3aed; }

.guide-tip {
  margin: 1rem 0 0;
  padding: 0.65rem 0.8rem;
  border-radius: 6px;
  font-size: 0.84rem;
  color: #475569;
  background: #e8f7ee;
  border: 1px solid rgba(17, 154, 72, 0.25);
}

@media (max-width: 820px) {
  .guide-slide {
    grid-template-columns: 1fr;
  }
  .guide-visual {
    min-height: 120px;
  }
}

@media print {
  .guide-slide {
    break-inside: avoid;
    page-break-inside: avoid;
  }
}
</style>
