<template>
  <!--
    Drop-in replacement for <select>: keep the <option> children and v-model as they are.
    The list opens right under the field (above only when there is no room), stays compact
    on phones, scrolls when long and gets a search box from 10 options.
  -->
  <div ref="root" class="sbx relative" :class="wrapClass">
    <button ref="trigger" type="button" :id="id" :class="[$attrs.class, 'sbx-trigger']" :disabled="disabled"
      :aria-expanded="open" aria-haspopup="listbox" :aria-label="ariaLabel" @click="toggle" @keydown="onTriggerKey">
      <span :class="['sbx-value', isPlaceholder && 'sbx-placeholder']">{{ selectedLabel }}</span>
      <svg class="sbx-chevron" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" /></svg>
    </button>

    <Teleport :to="teleportTo" :disabled="!teleportTo">
      <div v-if="open" ref="panel" class="sbx-panel" :style="panelStyle" role="listbox" :aria-activedescendant="activeId" @keydown="onPanelKey">
        <div v-if="options.length >= 10" class="sbx-search-wrap">
          <input ref="search" v-model="q" type="text" class="sbx-search" placeholder="Search…" @keydown="onPanelKey" />
        </div>
        <ul ref="list" class="sbx-list">
          <li v-for="(o, i) in filtered" :key="String(o.value) + i" :id="`${uid}-${i}`" role="option" :aria-selected="isSelected(o)"
            :class="['sbx-option', isSelected(o) && 'is-selected', i === active && 'is-active', o.disabled && 'is-disabled']"
            @mousedown.prevent="choose(o)" @mousemove="active = i">
            <span class="truncate min-w-0 flex-1">{{ o.label }}</span>
            <svg v-if="isSelected(o)" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
          </li>
          <li v-if="!filtered.length" class="sbx-empty">Nothing matches “{{ q }}”</li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({
  modelValue: { default: null },
  disabled: Boolean,
  id: String,
  ariaLabel: String,
  wrapClass: { type: [String, Array, Object], default: '' },
});
const emit = defineEmits(['update:modelValue', 'change']);
const slots = useSlots();
const uid = `sbx-${Math.random().toString(36).slice(2, 8)}`;

// Read the <option> children (including v-for fragments) so templates stay unchanged.
function collect(nodes, out = []) {
  for (const n of nodes || []) {
    if (!n) continue;
    if (Array.isArray(n.children) && n.type !== 'option') collect(n.children, out);
    else if (n.type === 'option') {
      const text = typeof n.children === 'string' ? n.children : Array.isArray(n.children) ? n.children.map((c) => (typeof c === 'string' ? c : c?.children ?? '')).join('') : '';
      const value = n.props && 'value' in n.props ? n.props.value : text;
      out.push({ value, label: text.trim(), disabled: !!n.props?.disabled || n.props?.disabled === '' });
    }
  }
  return out;
}
const options = computed(() => collect(slots.default?.() || []));

const same = (a, b) => a === b || (a !== null && b !== null && a !== undefined && b !== undefined && String(a) === String(b));
const isSelected = (o) => same(o.value, props.modelValue);
const selected = computed(() => options.value.find(isSelected));
const selectedLabel = computed(() => selected.value?.label ?? options.value[0]?.label ?? '');
const isPlaceholder = computed(() => !selected.value || selected.value.value === '' || selected.value.value === null);

const open = ref(false);
const q = ref('');
const active = ref(0);
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const list = ref(null);
const search = ref(null);
const teleportTo = ref(null);
const panelStyle = ref({});
const activeId = computed(() => (open.value ? `${uid}-${active.value}` : undefined));

const filtered = computed(() => {
  const t = q.value.trim().toLowerCase();
  return t ? options.value.filter((o) => o.label.toLowerCase().includes(t)) : options.value;
});
watch(q, () => { active.value = 0; });

// Fixed position under the field; flips above only when the space below is too small.
function place() {
  if (!trigger.value) return;
  const r = trigger.value.getBoundingClientRect();
  const vh = window.innerHeight;
  const below = vh - r.bottom - 8;
  const above = r.top - 8;
  const want = Math.min(288, 44 + options.value.length * 38);
  const up = below < Math.min(want, 200) && above > below;
  const max = Math.max(140, Math.min(want, up ? above : below));
  // At least 8rem wide, so short fields (e.g. "Rows 25") never cut the option text.
  const width = Math.min(Math.max(r.width, 128), window.innerWidth - 16);
  panelStyle.value = {
    position: 'fixed',
    left: `${Math.min(Math.max(8, r.left), window.innerWidth - width - 8)}px`,
    width: `${width}px`,
    maxHeight: `${max}px`,
    ...(up ? { bottom: `${vh - r.top + 4}px` } : { top: `${r.bottom + 4}px` }),
  };
}

async function openMenu() {
  if (props.disabled) return;
  open.value = true;
  q.value = '';
  active.value = Math.max(0, filtered.value.findIndex(isSelected));
  place();
  await nextTick();
  place();
  (search.value || panel.value)?.focus?.();
  scrollActive();
}
function close(refocus = false) {
  open.value = false;
  if (refocus) trigger.value?.focus();
}
const toggle = () => (open.value ? close() : openMenu());

function choose(o) {
  if (o.disabled) return;
  emit('update:modelValue', o.value);
  emit('change', o.value);
  close(true);
}
function scrollActive() {
  nextTick(() => list.value?.children[active.value]?.scrollIntoView({ block: 'nearest' }));
}
function move(d) {
  const n = filtered.value.length;
  if (!n) return;
  active.value = (active.value + d + n) % n;
  scrollActive();
}
function onTriggerKey(e) {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); openMenu(); }
}
function onPanelKey(e) {
  if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
  else if (e.key === 'Enter') { e.preventDefault(); if (filtered.value[active.value]) choose(filtered.value[active.value]); }
  else if (e.key === 'Escape' || e.key === 'Tab') { close(e.key === 'Escape'); }
}

const outside = (e) => {
  if (!open.value) return;
  if (root.value?.contains(e.target) || panel.value?.contains(e.target)) return;
  close();
};
const reposition = (e) => {
  if (!open.value) return;
  if (panel.value && e?.target && panel.value.contains(e.target)) return; // scrolling the list itself
  place();
};
onMounted(() => {
  // Stay inside the themed area (.admin-ui / .site) so colours and fonts apply.
  teleportTo.value = root.value?.closest('.admin-ui, .site') || null;
  document.addEventListener('mousedown', outside);
  document.addEventListener('touchstart', outside, { passive: true });
  window.addEventListener('resize', reposition);
  window.addEventListener('scroll', reposition, true);
});
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', outside);
  document.removeEventListener('touchstart', outside);
  window.removeEventListener('resize', reposition);
  window.removeEventListener('scroll', reposition, true);
});
</script>

<style scoped>
.sbx-trigger { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%; text-align: left; cursor: pointer; background-image: none !important; }
.sbx-trigger:disabled { cursor: not-allowed; opacity: 0.6; }
.sbx-value { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.sbx-placeholder { opacity: 0.6; }
.sbx-chevron { width: 1rem; height: 1rem; flex-shrink: 0; opacity: 0.55; transition: transform 0.15s; }

.sbx-panel {
  z-index: 70; display: flex; flex-direction: column; overflow: hidden;
  border-radius: 12px; border: 1px solid var(--a-border, var(--s-border, #e5e7eb));
  background: var(--a-panel, var(--s-surface, #fff)); color: var(--a-text, var(--s-text, #0f172a));
  box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.35); outline: none;
  animation: sbx-in 0.12s ease-out;
}
@keyframes sbx-in { from { opacity: 0; transform: translateY(-4px); } }
.sbx-search-wrap { padding: 6px; border-bottom: 1px solid var(--a-border, var(--s-border, #e5e7eb)); }
.sbx-search {
  width: 100%; font-size: 13px; padding: 7px 10px; border-radius: 8px; outline: none;
  background: var(--a-panel-2, var(--s-surface-2, #f8fafc)); color: inherit;
  border: 1px solid var(--a-border, var(--s-border, #e5e7eb));
}
.sbx-list { overflow-y: auto; overscroll-behavior: contain; padding: 4px; margin: 0; list-style: none; flex: 1; }
.sbx-option {
  display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
  padding: 8px 10px; border-radius: 8px; font-size: 14px; line-height: 1.3; cursor: pointer;
}
.sbx-option.is-active { background: var(--a-panel-3, var(--s-surface-2, #f1f5f9)); }
.sbx-option.is-selected { font-weight: 600; color: var(--a-accent-text, var(--s-accent-text, #1452b0)); }
.sbx-option.is-disabled { opacity: 0.45; cursor: not-allowed; }
.sbx-empty { padding: 10px; font-size: 13px; opacity: 0.6; }
</style>
