<template>
  <Teleport to="body">
    <transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="confirmState.open" class="admin-ui fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/55 backdrop-blur-[2px]" @keydown.esc="settleConfirm(false)" @click.self="settleConfirm(false)">
        <div role="alertdialog" aria-modal="true" :aria-labelledby="'cd-title'" class="admin-card w-full max-w-md p-6 shadow-2xl">
          <div class="flex gap-4">
            <div :class="['w-10 h-10 rounded-full flex items-center justify-center shrink-0', confirmState.tone === 'danger' ? 'bg-red-500/10 a-text-danger' : 'bg-orange-500/10 a-text-warning']">
              <svg v-if="confirmState.tone === 'danger'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div class="min-w-0">
              <h3 id="cd-title" class="text-base font-bold">{{ confirmState.title }}</h3>
              <p class="mt-1.5 text-sm a-muted whitespace-pre-line">{{ confirmState.message }}</p>
            </div>
          </div>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="admin-btn-secondary" @click="settleConfirm(false)">{{ confirmState.cancelText }}</button>
            <button ref="confirmBtn" type="button" :class="confirmState.tone === 'danger' ? 'a-btn-danger' : 'admin-btn-primary'" @click="settleConfirm(true)">{{ confirmState.confirmText }}</button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { confirmState, settleConfirm } from '@/Composables/useConfirm';

const confirmBtn = ref(null);
watch(() => confirmState.open, async (open) => {
  if (open) {
    await nextTick();
    confirmBtn.value?.focus();
  }
});
</script>
