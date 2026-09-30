<template>
  <!-- Floats at the bottom of the screen while rows are selected. -->
  <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-3" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 translate-y-3">
    <div v-if="bulk.count.value" class="fixed bottom-5 left-1/2 -translate-x-1/2 lg:ml-[7.75rem] z-40 w-[min(94vw,34rem)]">
      <div class="admin-card flex items-center gap-3 px-4 py-3" style="box-shadow: var(--a-shadow-lg)">
        <span class="w-8 h-8 rounded-full grid place-items-center a-tint-accent a-accent text-sm font-bold tabular-nums shrink-0">{{ bulk.count.value }}</span>
        <p class="text-sm flex-1 min-w-0 truncate"><b>{{ bulk.count.value }}</b> {{ bulk.names(bulk.count.value) }} selected</p>
        <slot />
        <button type="button" class="a-btn-ghost a-btn-sm" @click="bulk.clear()">Clear</button>
        <button v-if="canDelete" type="button" class="a-btn-danger a-btn-sm" :disabled="bulk.busy.value" @click="bulk.destroy()">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12.1A2 2 0 0116.1 21H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
          {{ bulk.busy.value ? 'Deleting…' : 'Delete selected' }}
        </button>
      </div>
    </div>
  </transition>
</template>

<script setup>
defineProps({ bulk: { type: Object, required: true }, canDelete: { type: Boolean, default: true } });
</script>
