<template>
  <section class="rounded-xl border a-border overflow-hidden">
    <header class="flex items-center gap-3 px-4 py-3 border-b a-border a-panel-2">
      <div class="relative w-11 h-11 shrink-0">
        <svg viewBox="0 0 36 36" class="w-11 h-11 -rotate-90">
          <circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--a-panel-3)" stroke-width="3.5" />
          <circle cx="18" cy="18" r="15.5" fill="none" :stroke="color(result?.score)" stroke-width="3.5" stroke-linecap="round"
            :stroke-dasharray="`${(result?.score || 0) * 0.974} 100`" class="transition-all duration-500" />
        </svg>
        <span class="absolute inset-0 flex items-center justify-center text-[13px] font-bold tabular-nums">{{ result ? result.score : '–' }}</span>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold">Content quality</p>
        <p class="text-[11px] a-subtle">{{ loading ? 'Checking…' : verdict }}</p>
      </div>
    </header>

    <div v-if="result">
      <div class="grid grid-cols-4 border-b a-border">
        <button v-for="(p, key) in result.pillars" :key="key" type="button" @click="open = key"
          :class="['px-1 py-2.5 text-center border-b-2 -mb-px transition', open === key ? 'border-[var(--a-accent)] a-panel' : 'border-transparent a-hover']">
          <span class="block text-[15px] font-bold tabular-nums" :style="{ color: color(p.score) }">{{ p.score }}</span>
          <span class="block text-[10px] font-semibold uppercase tracking-wide a-subtle">{{ short[key] }}</span>
        </button>
      </div>
      <div class="p-3">
        <p class="px-1 pb-2 text-[11px] a-muted">{{ help[open] }}</p>
        <ul class="space-y-1">
          <li v-for="c in sorted(result.pillars[open].checks)" :key="c.label" class="flex gap-2 px-1 py-1 text-[12.5px] leading-snug">
            <span :class="['mt-0.5 w-4 h-4 rounded-full shrink-0 flex items-center justify-center text-[10px] font-bold', c.ok ? 'a-tint-success a-text-success' : 'a-tint-warning a-text-warning']">{{ c.ok ? '✓' : '!' }}</span>
            <span class="min-w-0">
              <span :class="c.ok ? 'a-muted' : 'font-semibold'">{{ c.label }}</span>
              <span v-if="c.tip" class="block a-subtle mt-0.5">{{ c.tip }}</span>
            </span>
          </li>
        </ul>
      </div>
    </div>
    <p v-else class="p-4 text-xs a-subtle">Start writing to see the score.</p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  payload: { type: Object, required: true },
  active: { type: Boolean, default: true },
});

const result = ref(null);
const loading = ref(false);
const open = ref('seo');
const short = { seo: 'SEO', aeo: 'AEO', geo: 'GEO', eeat: 'E-E-A-T' };
const help = {
  seo: 'Basics Google needs to understand and rank the page.',
  aeo: 'Answer engines (Google AI Overviews, featured snippets, voice) lift short, direct answers.',
  geo: 'AI assistants (ChatGPT, Perplexity, Gemini) quote pages with facts, sources and local detail.',
  eeat: 'Experience, expertise, authority and trust: who wrote it and why they can be believed.',
};

const color = s => (s == null ? 'var(--a-border-2)' : s >= 80 ? 'var(--a-success)' : s >= 55 ? 'var(--a-warning)' : 'var(--a-danger)');
const verdict = computed(() => {
  const s = result.value?.score;
  if (s == null) return '';
  return s >= 80 ? 'Strong. Ready to publish.' : s >= 55 ? 'Good start. Fix the items marked !' : 'Needs work before publishing.';
});
const sorted = list => [...list].sort((a, b) => a.ok - b.ok || b.weight - a.weight);

let timer = null;
let seq = 0;
watch(() => (props.active ? JSON.stringify(props.payload) : null), (json) => {
  if (!json) return;
  clearTimeout(timer);
  timer = setTimeout(async () => {
    const mine = ++seq;
    loading.value = true;
    try {
      const { data } = await axios.post('/admin/content-check/quality', props.payload);
      if (mine === seq) result.value = data;
    } catch (e) { /* keep last result */ } finally {
      if (mine === seq) loading.value = false;
    }
  }, 900);
}, { immediate: true });
</script>
