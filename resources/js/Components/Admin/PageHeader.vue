<template>
  <!--
    Page title + actions. Once the header scrolls under the top bar, a slim copy of the title
    and the same action buttons slides in and stays there, so actions are always one click away.
    The slim bar is zero-height in the page flow, so nothing jumps while scrolling.
  -->
  <div v-if="$slots.default" data-page-slim class="sticky z-20 h-0 -mx-4 sm:-mx-6 lg:-mx-8" :style="{ top: offset + 'px' }">
    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 -translate-y-2">
      <div v-if="stuck" class="absolute inset-x-0 top-0 h-12 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3 border-b a-border shadow-sm backdrop-blur-md overflow-hidden"
        style="background: color-mix(in srgb, var(--a-bg) 88%, transparent)">
        <!-- On phones the top bar already shows the page name, so the buttons get the whole row. -->
        <p class="hidden sm:block text-[15px] font-semibold a-display truncate min-w-0">{{ title }}</p>
        <!-- Buttons that do not fit scroll sideways inside this row instead of pushing the page wider. -->
        <div class="flex items-center gap-2 min-w-0 max-w-full ml-auto overflow-x-auto a-scroll page-header-compact [&>*]:shrink-0 [&>*]:whitespace-nowrap" style="scrollbar-width: none">
          <slot />
        </div>
      </div>
    </transition>
  </div>

  <div ref="el" class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
    <div class="min-w-0">
      <h2 class="text-[1.45rem] sm:text-[1.75rem] font-semibold a-display">{{ title }}</h2>
      <p v-if="description" class="mt-1.5 text-[14.5px] a-muted max-w-3xl">{{ description }}</p>
      <slot name="meta" />
    </div>
    <div v-if="$slots.default" class="flex flex-wrap items-center gap-2 shrink-0">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ title: String, description: String });

const el = ref(null);
const stuck = ref(false);
const offset = ref(64);
let observer = null;

onMounted(() => {
  if (!el.value || typeof IntersectionObserver === 'undefined') return;
  // Sits under the top bar (64px) and, on pages that have them, under the section tabs.
  const tabs = document.querySelector('[data-admin-tabs]');
  offset.value = 64 + (tabs ? Math.round(tabs.getBoundingClientRect().height) : 0);
  observer = new IntersectionObserver(([entry]) => { stuck.value = !entry.isIntersecting && entry.boundingClientRect.top < offset.value; }, { rootMargin: `-${offset.value}px 0px 0px 0px`, threshold: 0 });
  observer.observe(el.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
/* Buttons in the slim bar are a size smaller so it stays one line. */
.page-header-compact :deep(.admin-btn-primary),
.page-header-compact :deep(.admin-btn-secondary) { padding-top: 0.35rem; padding-bottom: 0.35rem; font-size: 0.8rem; white-space: nowrap; }
</style>
