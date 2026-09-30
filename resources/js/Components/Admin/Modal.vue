<template>
  <transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0">
    <div v-if="show" class="fixed inset-0 z-50 bg-black/55 backdrop-blur-[2px] flex items-center justify-center p-2 sm:p-6" role="dialog" aria-modal="true" :aria-label="title">
      <!-- Header stays put; only the body scrolls, so sticky toolbars and the Save bar stay in view. -->
      <div :class="['admin-card w-full flex flex-col max-h-full overflow-hidden', widthClass]" style="box-shadow: var(--a-shadow-lg)">
        <div class="shrink-0 flex justify-between items-start gap-4 px-5 sm:px-6 pt-5 pb-4 border-b a-border">
          <div class="min-w-0">
            <h3 class="text-[17px] font-bold tracking-tight truncate">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs a-muted mt-0.5">{{ subtitle }}</p>
          </div>
          <button type="button" @click="$emit('close')" class="a-btn-ghost a-btn-icon -mr-2 -mt-1" aria-label="Close">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
        <div ref="body" class="modal-body flex-1 min-h-0 overflow-y-auto overscroll-contain a-scroll px-5 sm:px-6 py-6">
          <slot />
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
  show: Boolean,
  title: String,
  subtitle: String,
  width: { type: String, default: '3xl' },
});
const emit = defineEmits(['close']);
const body = ref(null);

const widthClass = computed(() => ({
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
  '5xl': 'max-w-6xl',
}[props.width] || 'max-w-3xl'));

// Esc closes the top-most modal; the page behind does not scroll while open.
function onKey(e) {
  if (e.key === 'Escape') emit('close');
}
watch(() => props.show, async (open) => {
  document.documentElement.style.overflow = open ? 'hidden' : '';
  open ? document.addEventListener('keydown', onKey) : document.removeEventListener('keydown', onKey);
  if (open) {
    await nextTick();
    if (body.value) body.value.scrollTop = 0; // every open starts at the top
  }
});
onBeforeUnmount(() => {
  document.documentElement.style.overflow = '';
  document.removeEventListener('keydown', onKey);
});
</script>
