<template>
  <p class="text-xs a-subtle flex items-center gap-1.5">
    <span class="w-1.5 h-1.5 rounded-full" :style="{ background: dot }"></span>{{ text }}
  </p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ autosave: { type: Object, required: true } });
const status = computed(() => props.autosave.status.value);
const text = computed(() => ({
  pending: 'Unsaved changes…',
  saving: 'Autosaving…',
  saved: 'Draft autosaved',
  offline: 'Saved on this device only (server not reachable, retrying)',
}[status.value] || 'Autosaves while you type'));
const dot = computed(() => ({
  pending: 'var(--a-warning)', saving: 'var(--a-warning)', saved: 'var(--a-success)', offline: 'var(--a-danger)',
}[status.value] || 'var(--a-border-2)'));
</script>
