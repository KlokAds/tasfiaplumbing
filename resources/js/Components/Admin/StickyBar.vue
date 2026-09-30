<template>
  <!--
    Keeps a block (stat cards, tabs, filters) in reach while scrolling. When the block passes
    under the top bars, a compact copy slides in right below them; the original stays in the
    page, so nothing jumps. The copy uses the same state, so filters and tabs work in both.
  -->
  <div class="sticky z-[19] h-0 -mx-4 sm:-mx-6 lg:-mx-8" :style="{ top: top + 'px' }">
    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 -translate-y-2">
      <div v-if="stuck" class="absolute inset-x-0 top-0 px-4 sm:px-6 lg:px-8 py-2 border-b a-border shadow-sm backdrop-blur-md"
        style="background: color-mix(in srgb, var(--a-bg) 92%, transparent)">
        <div class="sticky-compact max-w-[1400px] mx-auto">
          <slot :compact="true" />
        </div>
      </div>
    </transition>
  </div>
  <div ref="el">
    <slot :compact="false" />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const el = ref(null);
const stuck = ref(false);
const top = ref(64);
let observer = null;

onMounted(() => {
  if (!el.value || typeof IntersectionObserver === 'undefined') return;
  // Below the top bar, the section tabs (if any) and the page's slim title bar (if any).
  const tabs = document.querySelector('[data-admin-tabs]');
  const slim = document.querySelector('[data-page-slim]');
  top.value = 64 + (tabs ? Math.round(tabs.getBoundingClientRect().height) : 0) + (slim ? 48 : 0);
  // Only once the original has fully gone under the bars, so the two never show together.
  observer = new IntersectionObserver(([e]) => {
    stuck.value = !e.isIntersecting && e.boundingClientRect.top < top.value;
  }, { rootMargin: `-${top.value}px 0px 0px 0px`, threshold: 0 });
  observer.observe(el.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>
