<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { OrgStructureNode } from '@/lib/settingsApi'
import OrgStructureTreeNodes from './OrgStructureTreeNodes.vue'

defineProps<{
  nodes: OrgStructureNode[]
  expanded: Record<number, boolean>
}>()

const emit = defineEmits<{
  toggle: [id: number]
  edit: [node: OrgStructureNode]
}>()

function tierLabel(tier?: string | null): string {
  const map: Record<string, string> = {
    root: 'Organization',
    dg: 'DG',
    ddg: 'DDG',
    cos: 'CoS',
    dcos: 'DCoS',
    director: 'Director',
    hod: 'HOD',
    staff: 'Staff',
  }
  return map[tier || ''] || 'Position'
}
</script>

<template>
  <li v-for="node in nodes" :key="node.id" class="org-tree__item">
    <div class="org-tree__row">
      <button
        v-if="node.children?.length"
        type="button"
        class="org-tree__toggle"
        @click="emit('toggle', node.id)"
      >
        <i :class="expanded[node.id] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" />
      </button>
      <span v-else class="org-tree__toggle org-tree__toggle--spacer" />
      <div class="org-tree__body">
        <div class="org-tree__title-line">
          <span class="org-tree__title">{{ node.title }}</span>
          <span class="org-tree__tier">{{ tierLabel(node.tier) }}</span>
          <span v-if="node.grade_code" class="org-tree__grade">{{ node.grade_code }}</span>
        </div>
        <div class="org-tree__meta">
          <span>Approved {{ node.approved_slots }}</span>
          <span>Filled {{ node.filled_slots }}</span>
          <span v-if="node.vacant_slots">Vacant {{ node.vacant_slots }}</span>
          <template v-if="node.filled_by?.length">
            <span>·</span>
            <RouterLink
              v-for="person in node.filled_by"
              :key="person.staff_id"
              :to="`/staff/${person.staff_id}`"
              class="org-tree__person"
            >
              {{ person.name }}
            </RouterLink>
          </template>
          <span v-else-if="node.node_type === 'position'" class="text-medium-emphasis">· Vacant</span>
        </div>
      </div>
      <button
        v-if="node.node_type !== 'organization'"
        type="button"
        class="org-tree__edit"
        @click="emit('edit', node)"
      >
        Edit
      </button>
    </div>
    <ul v-if="node.children?.length && expanded[node.id]" class="org-tree">
      <OrgStructureTreeNodes
        :nodes="node.children"
        :expanded="expanded"
        @toggle="emit('toggle', $event)"
        @edit="emit('edit', $event)"
      />
    </ul>
  </li>
</template>

<style scoped>
.org-tree {
  list-style: none;
  margin: 0;
  padding: 0.15rem 0 0.15rem 0.55rem;
  border-left: 1px solid rgba(58, 71, 82, 0.12);
  margin-left: 0.7rem;
}
.org-tree__item {
  margin: 0.15rem 0;
}
.org-tree__row {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  padding: 0.45rem 0.5rem;
  border-radius: 0.45rem;
}
.org-tree__row:hover {
  background: rgba(17, 154, 72, 0.05);
}
.org-tree__toggle {
  width: 1.4rem;
  height: 1.4rem;
  border: 0;
  background: transparent;
  color: #3a4752;
  cursor: pointer;
  flex-shrink: 0;
}
.org-tree__toggle--spacer {
  visibility: hidden;
}
.org-tree__body {
  flex: 1;
  min-width: 0;
}
.org-tree__title-line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}
.org-tree__title {
  font-weight: 650;
  color: #3a4752;
}
.org-tree__tier,
.org-tree__grade {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  background: #eef5f0;
  color: #065f2c;
}
.org-tree__grade {
  background: #eef2ff;
  color: #3730a3;
}
.org-tree__meta {
  font-size: 0.78rem;
  color: rgba(58, 71, 82, 0.72);
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.15rem;
  align-items: center;
}
.org-tree__person {
  color: #119a48;
  text-decoration: none;
  font-weight: 600;
}
.org-tree__person + .org-tree__person::before {
  content: ', ';
  color: rgba(58, 71, 82, 0.5);
  font-weight: 400;
}
.org-tree__edit {
  border: 1px solid rgba(58, 71, 82, 0.18);
  background: #fff;
  border-radius: 0.35rem;
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  color: #3a4752;
}
</style>
