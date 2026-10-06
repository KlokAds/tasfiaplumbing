<template>
  <!-- Every area page, by region (footer on every page: helps people and Google find them) -->
  <div v-if="areas.length" :class="['mt-12 pt-8 border-t', c.border]">
    <div class="flex items-baseline justify-between gap-4 mb-4">
      <p :class="['text-[13px] font-bold uppercase tracking-[0.12em]', c.title]">Areas we serve</p>
      <Link href="/locations" :class="['text-[14px] font-semibold', c.all]">All {{ areas.length }} areas →</Link>
    </div>
    <div class="grid gap-3">
      <div v-for="g in groups" :key="g.region" class="grid sm:grid-cols-[8.5rem_1fr] gap-x-4 gap-y-1">
        <p :class="['text-[13px] font-semibold uppercase tracking-[0.08em] pt-0.5', c.region]">{{ g.region }}</p>
        <p :class="['text-[14.5px] leading-7', c.text]">
          <template v-for="(l, i) in g.items" :key="l.href"><Link :href="l.href" :class="[c.link, 'hover:underline']">{{ l.name }}</Link><span v-if="i < g.items.length - 1" :class="c.dot" aria-hidden="true"> · </span></template>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// tone: 'dark' for a dark footer (white text), 'theme' for a footer that uses the site's colour tokens.
const props = defineProps({ areas: { type: Array, default: () => [] }, tone: { type: String, default: 'dark' } });
const c = computed(() => (props.tone === 'theme'
  ? { border: 's-border', title: 's-heading', all: 's-heading hover:text-[var(--s-accent-text)]', region: 's-subtle', text: 's-muted', link: 'hover:text-[var(--s-heading)]', dot: 's-subtle' }
  : { border: 'border-white/10', title: 'text-white/70', all: 'text-white/80 hover:text-white', region: 'text-white/50', text: 'text-white/70', link: 'hover:text-white', dot: 'text-white/30' }));

const ORDER = ['Central', 'East', 'North', 'North-East', 'West'];
const groups = computed(() => {
  const by = {};
  for (const a of props.areas) (by[a.region || 'Singapore'] ||= []).push(a);
  return Object.keys(by)
    .sort((x, y) => (ORDER.indexOf(x) + 1 || 99) - (ORDER.indexOf(y) + 1 || 99))
    .map(region => ({ region, items: by[region].sort((a, b) => a.name.localeCompare(b.name)) }));
});
</script>
