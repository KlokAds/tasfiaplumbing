<template>
  <Teleport to="body">
    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
      <div v-if="open" class="site fixed inset-0 z-[90] !bg-black/55 backdrop-blur-sm flex items-start justify-center p-3 sm:p-6 pt-[8vh]" role="dialog" aria-modal="true" aria-label="Search the website" @click.self="$emit('close')">
        <div class="w-full max-w-2xl card overflow-hidden" style="box-shadow: var(--s-shadow-lg)">
          <form @submit.prevent="goAll" class="flex items-center gap-3 px-5 border-b s-border">
            <svg class="w-5 h-5 s-subtle shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
            <label for="site-search" class="sr-only">Search services, articles, projects and areas</label>
            <input id="site-search" ref="input" v-model="q" type="search" autocomplete="off" placeholder="Search services, guides, projects, areas…" class="flex-1 py-4 bg-transparent outline-none text-[16px] s-text" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.esc="$emit('close')" />
            <button type="button" @click="$emit('close')" class="text-[13px] s-subtle border s-border rounded-md px-1.5 py-0.5">Esc</button>
          </form>

          <div class="max-h-[65vh] overflow-y-auto" style="scrollbar-width: thin">
            <p v-if="q.trim().length < 2" class="px-5 py-6 text-[15px] s-muted">
              Try <button v-for="t in tips" :key="t" type="button" @click="q = t" class="link mx-1">{{ t }}</button>
            </p>
            <p v-else-if="loading && !flat.length" class="px-5 py-6 text-[15px] s-muted">Searching…</p>
            <div v-else-if="!flat.length" class="px-5 py-8 text-center">
              <p class="font-semibold s-heading">Nothing found for “{{ q }}”</p>
              <p class="mt-1 text-[15px] s-muted">Ask us directly, we reply fast.</p>
              <WhatsAppButton :topic="q" class="mt-4" />
            </div>
            <template v-else>
              <div v-for="g in groups" :key="g.key" class="py-2 border-b s-border last:border-0">
                <p class="px-5 py-1.5 text-[12.5px] font-bold uppercase tracking-[0.12em] s-subtle">{{ g.label }}</p>
                <Link v-for="r in g.items" :key="r.url" :href="r.url" @click="$emit('close')" @mousemove="cursor = r._i"
                  :class="['flex items-center gap-3 px-5 py-2.5', cursor === r._i ? 's-surface-2' : '']">
                  <img v-if="r.image" :src="img(r.image, 96)" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" loading="lazy" />
                  <span v-else class="w-10 h-10 rounded-lg icon-box !w-10 !h-10 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.2-7-11.5A7 7 0 0112 2.5a7 7 0 017 7C19 14.8 12 21 12 21z" /></svg>
                  </span>
                  <span class="min-w-0">
                    <span class="block text-[15.5px] font-semibold s-heading truncate" v-html="mark(r.title)"></span>
                    <span v-if="r.text" class="block text-[13.5px] s-subtle truncate">{{ r.text }}</span>
                  </span>
                </Link>
              </div>
              <button type="button" @click="goAll" class="w-full px-5 py-3 text-left text-[15px] link s-surface-2">See all results for “{{ q }}” →</button>
            </template>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import { img } from '@/utils/img';

const props = defineProps({ open: Boolean });
const emit = defineEmits(['close']);

const q = ref('');
const input = ref(null);
const loading = ref(false);
const data = ref(null);
const cursor = ref(0);
const navigated = ref(false); // arrow keys used: Enter opens the highlighted result
const tips = ['water heater', 'tiling', 'HDB', 'price'];

const LABELS = { services: 'Services', articles: 'Guides', projects: 'Projects', locations: 'Areas' };
const groups = computed(() => {
  let i = 0;
  return Object.keys(LABELS)
    .filter(k => data.value?.[k]?.length)
    .map(k => ({ key: k, label: LABELS[k], items: data.value[k].map(r => ({ ...r, _i: i++ })) }));
});
const flat = computed(() => groups.value.flatMap(g => g.items));

let timer = null;
let seq = 0;
watch(q, (value) => {
  clearTimeout(timer);
  cursor.value = 0;
  navigated.value = false;
  if (value.trim().length < 2) { data.value = null; return; }
  loading.value = true;
  timer = setTimeout(async () => {
    const mine = ++seq;
    try {
      const res = await fetch('/search/suggest?q=' + encodeURIComponent(value.trim()), { headers: { Accept: 'application/json' } });
      if (mine === seq) data.value = await res.json();
    } catch (e) { /* offline */ } finally {
      if (mine === seq) loading.value = false;
    }
  }, 220);
});

watch(() => props.open, async (o) => {
  document.body.style.overflow = o ? 'hidden' : '';
  if (o) { await nextTick(); input.value?.focus(); }
});

function move(step) {
  if (!flat.value.length) return;
  cursor.value = (cursor.value + step + flat.value.length) % flat.value.length;
  navigated.value = true;
}
function goAll() {
  const hit = flat.value[cursor.value];
  if (hit && navigated.value) { router.visit(hit.url); emit('close'); return; }
  if (q.value.trim().length < 2) return;
  router.visit('/search?q=' + encodeURIComponent(q.value.trim()));
  emit('close');
}

const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
function mark(title) {
  let out = esc(title);
  q.value.trim().split(/\s+/).filter(w => w.length > 1).forEach(w => {
    out = out.replace(new RegExp(`(${w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig'), '<mark class="bg-transparent text-[var(--s-accent-text)]">$1</mark>');
  });
  return out;
}
</script>
