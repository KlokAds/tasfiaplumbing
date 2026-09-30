<template>
  <div v-if="autosave.restore.value" class="a-alert a-alert-accent items-center flex-col sm:flex-row">
    <svg class="w-5 h-5 a-accent shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.6m15.4 2A8 8 0 004.6 9m0 0H9m11 11v-5h-.6m0 0a8 8 0 01-15.4-2m15.4 2H15" /></svg>
    <p class="flex-1"><span class="font-semibold">Unsaved work found</span> <span class="a-muted">from {{ when }}. It was saved automatically. Restore it?</span></p>
    <div class="flex gap-2 shrink-0">
      <button type="button" @click="autosave.discard()" class="admin-btn-secondary a-btn-sm">Discard</button>
      <button type="button" @click="autosave.applyRestore()" class="admin-btn-primary a-btn-sm">Restore</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ autosave: { type: Object, required: true } });
const when = computed(() => new Date(props.autosave.restore.value?.saved_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }));
</script>
