<script setup>
// Small marker in admin lists: "New" for a record created in the last 30 days,
// "Edited <date>" for one changed more than an hour after it was created.
import { computed } from 'vue';

const props = defineProps({
  created: { type: String, default: null },
  updated: { type: String, default: null },
});

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const NEW_DAYS = 30;
const EDIT_GAP_MS = 60 * 60 * 1000;

const time = (v) => {
  const t = v ? new Date(v).getTime() : NaN;
  return Number.isNaN(t) ? null : t;
};
const label = (t) => {
  const d = new Date(t);
  return `${d.getDate()} ${MONTHS[d.getMonth()]}${d.getFullYear() !== new Date().getFullYear() ? ` ${d.getFullYear()}` : ''}`;
};

const badge = computed(() => {
  const c = time(props.created);
  const u = time(props.updated);
  if (c && Date.now() - c < NEW_DAYS * 86400000) return { text: 'New', cls: 'a-badge-success', title: `Added ${label(c)}` };
  if (c && u && u - c > EDIT_GAP_MS) return { text: `Edited ${label(u)}`, cls: 'a-badge-info', title: `Added ${label(c)}, last changed ${label(u)}` };
  return null;
});
</script>

<template>
  <span v-if="badge" :class="['a-badge whitespace-nowrap', badge.cls]" :title="badge.title">{{ badge.text }}</span>
</template>
