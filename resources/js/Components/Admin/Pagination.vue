<template>
  <div v-if="meta && meta.total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4">
    <div class="flex items-center gap-3 text-xs a-muted">
      <span>Showing <span class="font-semibold a-text tabular-nums">{{ meta.from }}–{{ meta.to }}</span> of <span class="font-semibold a-text tabular-nums">{{ meta.total.toLocaleString() }}</span></span>
      <label v-if="meta.total > 10" class="flex items-center gap-1.5">
        <span class="hidden sm:inline">Rows</span>
        <SelectBox :model-value="meta.per_page" @update:model-value="v => setPerPage(v)" class="admin-input a-input-sm !w-auto" aria-label="Rows per page">
          <option v-for="n in options" :key="n" :value="n">{{ n }}</option>
        </SelectBox>
      </label>
    </div>

    <nav v-if="meta.last_page > 1" class="flex items-center gap-1" aria-label="Pagination">
      <component :is="prev ? Link : 'span'" :href="prev" preserve-scroll preserve-state :class="[btn, 'inline-flex', !prev && 'opacity-40 pointer-events-none']" aria-label="Previous page">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      </component>
      <span class="sm:hidden px-2 text-xs a-muted tabular-nums">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <template v-for="(p, i) in pages" :key="i">
        <span v-if="p.gap" class="hidden sm:inline px-1.5 text-xs a-subtle">…</span>
        <Link v-else class="hidden sm:inline-flex" :href="p.url" preserve-scroll preserve-state :class="[btn, 'min-w-8', p.active ? '!bg-[var(--a-accent)] !text-white !border-transparent' : '']" :aria-current="p.active ? 'page' : undefined">{{ p.label }}</Link>
      </template>
      <component :is="next ? Link : 'span'" :href="next" preserve-scroll preserve-state :class="[btn, 'inline-flex', !next && 'opacity-40 pointer-events-none']" aria-label="Next page">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
      </component>
    </nav>
  </div>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  meta: Object,
  options: { type: Array, default: () => [10, 25, 50, 100, 500] },
});

const btn = 'items-center justify-center h-8 px-2.5 rounded-lg border a-border a-panel text-xs font-semibold a-muted a-hover a-hover-text transition';

const prev = computed(() => props.meta?.prev_page_url || null);
const next = computed(() => props.meta?.next_page_url || null);

// Build the page list ourselves: first, last, and two either side of the current page.
function urlFor(n) {
  const base = props.meta.first_page_url || props.meta.path;
  const u = new URL(base, window.location.origin);
  const key = [...u.searchParams.keys()].find(k => k === 'page' || (k.endsWith('_page') && k !== 'per_page')) || 'page';
  u.searchParams.set(key, n);
  return u.pathname + u.search;
}
const pages = computed(() => {
  const cur = props.meta.current_page;
  const last = props.meta.last_page;
  const out = [];
  let prevN = 0;
  for (let n = 1; n <= last; n++) {
    if (n === 1 || n === last || Math.abs(n - cur) <= 2) {
      if (n - prevN > 1) out.push({ gap: true });
      out.push({ url: urlFor(n), label: n, active: n === cur });
      prevN = n;
    }
  }
  return out;
});

function setPerPage(value) {
  const url = new URL(window.location.href);
  url.searchParams.set('per_page', value);
  [...url.searchParams.keys()].filter(k => k === 'page' || (k.endsWith('_page') && k !== 'per_page')).forEach(k => url.searchParams.delete(k));
  router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true, replace: true });
}
</script>
