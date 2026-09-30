<template>
  <!-- Same look as <Pagination>, for lists that are paged in the browser (see usePaged). -->
  <div v-if="pager.total.value > options[0]" :class="['flex flex-col sm:flex-row items-center justify-between gap-3', padded && 'px-5 py-3 border-t a-border']">
    <div class="flex items-center gap-3 text-xs a-muted">
      <span>Showing <span class="font-semibold a-text tabular-nums">{{ pager.from.value }}–{{ pager.to.value }}</span> of <span class="font-semibold a-text tabular-nums">{{ pager.total.value.toLocaleString() }}</span></span>
      <label class="flex items-center gap-1.5">
        <span class="hidden sm:inline">Rows</span>
        <SelectBox v-model.number="pager.perPage.value" class="admin-input a-input-sm !w-auto" aria-label="Rows per page">
          <option v-for="n in options" :key="n" :value="n">{{ n }}</option>
        </SelectBox>
      </label>
    </div>
    <nav v-if="pager.lastPage.value > 1" class="flex items-center gap-1" aria-label="Pagination">
      <button type="button" :class="[btn, pager.page.value === 1 && 'opacity-40 pointer-events-none']" aria-label="Previous page" @click="go(pager.page.value - 1)">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
      </button>
      <span class="sm:hidden px-2 text-xs a-muted tabular-nums">Page {{ pager.page.value }} of {{ pager.lastPage.value }}</span>
      <template v-for="(p, i) in pages" :key="i">
        <span v-if="p === '…'" class="hidden sm:inline px-1.5 text-xs a-subtle">…</span>
        <button v-else type="button" :class="[btn, 'hidden sm:inline-flex min-w-8', p === pager.page.value && '!bg-[var(--a-accent)] !text-white !border-transparent']" :aria-current="p === pager.page.value ? 'page' : undefined" @click="go(p)">{{ p }}</button>
      </template>
      <button type="button" :class="[btn, pager.page.value === pager.lastPage.value && 'opacity-40 pointer-events-none']" aria-label="Next page" @click="go(pager.page.value + 1)">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
      </button>
    </nav>
  </div>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { computed } from 'vue';

const props = defineProps({
  pager: { type: Object, required: true },
  options: { type: Array, default: () => [10, 25, 50, 100, 500] },
  padded: { type: Boolean, default: true },
});
const btn = 'inline-flex items-center justify-center h-8 px-2.5 rounded-lg border a-border a-panel text-xs font-semibold a-muted a-hover a-hover-text transition';

const pages = computed(() => {
  const cur = props.pager.page.value;
  const last = props.pager.lastPage.value;
  const out = [];
  let prev = 0;
  for (let n = 1; n <= last; n++) {
    if (n === 1 || n === last || Math.abs(n - cur) <= 2) {
      if (n - prev > 1) out.push('…');
      out.push(n);
      prev = n;
    }
  }
  return out;
});

function go(n) {
  props.pager.page.value = Math.min(Math.max(1, n), props.pager.lastPage.value);
}
</script>
