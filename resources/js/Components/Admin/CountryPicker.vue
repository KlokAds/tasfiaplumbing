<template>
  <!-- Searchable multi-select: chips for the chosen countries, a filtered list to tick more. -->
  <div ref="root" class="relative">
    <div :class="['admin-input !h-auto min-h-[42px] flex flex-wrap items-center gap-1.5 !py-1.5 cursor-text', open && 'ring-2 ring-[var(--a-ring)]']" @click="focus">
      <span v-for="c in modelValue" :key="c" class="a-badge a-badge-accent !pr-1 inline-flex items-center gap-1">
        <span aria-hidden="true">{{ flag(c) }}</span>{{ countryName(c) }}
        <button type="button" class="w-4 h-4 rounded grid place-items-center hover:bg-black/10" :aria-label="`Remove ${countryName(c)}`" @click.stop="toggle(c)">×</button>
      </span>
      <input ref="input" v-model="q" type="text" class="flex-1 min-w-[10rem] bg-transparent outline-none text-sm py-0.5" :placeholder="modelValue.length ? 'Add another…' : placeholder" @focus="open = true" @keydown.enter.prevent="pickFirst" @keydown.backspace="onBackspace" @keydown.esc="open = false" />
    </div>

    <div v-if="open" class="absolute z-30 left-0 right-0 mt-1 rounded-xl border a-border a-panel shadow-xl overflow-hidden">
      <div v-if="!q" class="flex flex-wrap gap-1.5 px-3 py-2 border-b a-border">
        <span class="text-[11px] a-subtle self-center mr-1">Quick add:</span>
        <button v-for="g in groups" :key="g.label" type="button" class="admin-btn-secondary !py-1 !px-2 !text-[11px]" @mousedown.prevent="addMany(g.codes)">{{ g.label }}</button>
      </div>
      <ul class="max-h-64 overflow-y-auto a-scroll py-1" role="listbox" aria-multiselectable="true">
        <li v-for="[code, name] in results" :key="code">
          <button type="button" role="option" :aria-selected="modelValue.includes(code)" class="w-full flex items-center gap-2.5 px-3 py-1.5 text-sm text-left hover:bg-[var(--a-panel-3)]" @mousedown.prevent="toggle(code)">
            <input type="checkbox" :checked="modelValue.includes(code)" tabindex="-1" class="pointer-events-none" />
            <span aria-hidden="true">{{ flag(code) }}</span>
            <span class="flex-1">{{ name }}</span>
            <span class="a-mono text-[11px] a-subtle">{{ code }}</span>
          </button>
        </li>
        <li v-if="!results.length" class="px-3 py-3 text-sm a-muted">No country matches “{{ q }}”.</li>
      </ul>
      <div class="flex items-center justify-between px-3 py-2 border-t a-border text-xs">
        <span class="a-subtle">{{ modelValue.length }} selected</span>
        <span class="flex gap-3">
          <button v-if="modelValue.length" type="button" class="a-text-danger font-semibold" @mousedown.prevent="$emit('update:modelValue', [])">Clear all</button>
          <button type="button" class="a-accent font-semibold" @mousedown.prevent="open = false">Done</button>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { COUNTRIES, countryName, flag } from '@/utils/countries';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Search a country…' },
});
const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const input = ref(null);
const open = ref(false);
const q = ref('');

const groups = [
  { label: 'Singapore + Malaysia', codes: ['SG', 'MY'] },
  { label: 'Southeast Asia', codes: ['SG', 'MY', 'ID', 'TH', 'PH', 'VN', 'BN', 'KH', 'LA', 'MM', 'TL'] },
];

const results = computed(() => {
  const term = q.value.trim().toLowerCase();
  const list = term ? COUNTRIES.filter(([c, n]) => n.toLowerCase().includes(term) || c.toLowerCase() === term) : COUNTRIES;
  // Chosen ones first so they are easy to untick.
  return [...list].sort((a, b) => props.modelValue.includes(b[0]) - props.modelValue.includes(a[0]));
});

function toggle(code) {
  emit('update:modelValue', props.modelValue.includes(code) ? props.modelValue.filter((c) => c !== code) : [...props.modelValue, code]);
  q.value = '';
}
function addMany(codes) {
  emit('update:modelValue', [...new Set([...props.modelValue, ...codes])]);
}
function pickFirst() {
  if (results.value[0]) toggle(results.value[0][0]);
}
function onBackspace() {
  if (!q.value && props.modelValue.length) emit('update:modelValue', props.modelValue.slice(0, -1));
}
function focus() {
  open.value = true;
  input.value?.focus();
}
const outside = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };
onMounted(() => document.addEventListener('mousedown', outside));
onBeforeUnmount(() => document.removeEventListener('mousedown', outside));
</script>
