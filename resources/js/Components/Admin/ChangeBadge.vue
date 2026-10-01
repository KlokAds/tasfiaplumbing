<script setup>
// Small marker in admin lists:
// - "Changes waiting" while an edit waits for approval (articles),
// - "Edited <date>" once a record was changed after it was created,
// - "New" for a record added in the last 30 days and not edited since.
import { computed } from 'vue';

const props = defineProps({
  created: { type: String, default: null },
  updated: { type: String, default: null },
  pending: { type: Boolean, default: false },
});

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const NEW_DAYS = 30;
// Saves within a minute of creating belong to the creation itself.
const EDIT_GAP_MS = 60 * 1000;

const time = (v) => {
  const t = v ? new Date(v).getTime() : NaN;
  return Number.isNaN(t) ? null : t;
};
const label = (t) => {
  const d = new Date(t);
  return `${d.getDate()} ${MONTHS[d.getMonth()]} '${String(d.getFullYear()).slice(-2)}`;
};

const badge = computed(() => {
  const c = time(props.created);
  const u = time(props.updated);
  if (props.pending) return { text: 'Changes waiting', cls: 'a-badge-warning', title: 'An edit is waiting for approval; the live page is unchanged until it is approved' };
  if (c && u && u - c > EDIT_GAP_MS) return { text: `Edited ${label(u)}`, cls: 'a-badge-info', title: `Added ${label(c)}, last changed ${label(u)}` };
  if (c && Date.now() - c < NEW_DAYS * 86400000) return { text: 'New', cls: 'a-badge-success', title: `Added ${label(c)}` };
  return null;
});
</script>

<template>
  <span v-if="badge" :class="['a-badge whitespace-nowrap', badge.cls]" :title="badge.title">{{ badge.text }}</span>
</template>
