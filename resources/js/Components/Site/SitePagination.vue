<template>
  <nav v-if="meta && meta.last_page > 1" class="mt-12 flex items-center justify-center gap-1.5" aria-label="Pagination">
    <Link v-if="meta.prev_page_url" :href="meta.prev_page_url" class="btn btn-secondary btn-sm" rel="prev">← Previous</Link>
    <template v-for="(p, i) in pages" :key="i">
      <span v-if="p.gap" class="hidden sm:inline px-2 s-subtle">…</span>
      <Link v-else :href="p.url" :class="['hidden sm:inline-flex btn btn-sm min-w-9', p.active ? 'btn-primary' : 'btn-ghost']" :aria-current="p.active ? 'page' : undefined">{{ p.n }}</Link>
    </template>
    <span class="sm:hidden px-3 text-[15px] s-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
    <Link v-if="meta.next_page_url" :href="meta.next_page_url" class="btn btn-secondary btn-sm" rel="next">Next →</Link>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ meta: Object });

function urlFor(n) {
  const u = new URL(props.meta.first_page_url || props.meta.path, window.location.origin);
  u.searchParams.set('page', n);
  if (n === 1) u.searchParams.delete('page');
  return u.pathname + u.search;
}
const pages = computed(() => {
  const cur = props.meta.current_page;
  const last = props.meta.last_page;
  const out = [];
  let prev = 0;
  for (let n = 1; n <= last; n++) {
    if (n === 1 || n === last || Math.abs(n - cur) <= 1) {
      if (n - prev > 1) out.push({ gap: true });
      out.push({ n, url: urlFor(n), active: n === cur });
      prev = n;
    }
  }
  return out;
});
</script>
